<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowConfigFieldsContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_fields_endpoint_returns_fields_envelope_for_selected_form(): void
    {
        $user = $this->createUserWithPermissions(['workflows.manage']);

        $form = Form::create([
            'form_name' => 'Workflow Config Form',
            'form_code' => 'WF-CONTRACT',
            'description' => 'Contract test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'email_notifications' => false,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'event_date',
            'label' => 'Event Date',
            'data_type' => 'date',
            'is_required' => true,
            'field_order' => 0,
        ]);

        $this->actingAs($user)
            ->get("/workflow-config/forms/{$form->id}/fields")
            ->assertOk()
            ->assertJsonStructure([
                'fields' => [
                    [
                        'id',
                        'field_name',
                        'label',
                        'data_type',
                    ],
                ],
            ]);
    }

    private function createUserWithPermissions(array $permissionSlugs): User
    {
        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'permission_name' => ucwords(str_replace(['.', '-'], ' ', $slug)),
                    'description' => 'Test permission',
                    'resource' => explode('.', $slug)[0] ?? 'test',
                    'action' => explode('.', $slug)[1] ?? 'access',
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role = Role::create([
            'role_name' => 'Role '.uniqid(),
            'description' => 'Test role',
            'is_active' => true,
        ]);
        $role->permissions()->sync($permissionIds);

        $user = User::create([
            'username' => 'workflow_cfg_'.uniqid(),
            'email' => 'workflow_cfg_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        UserRole::create([
            'account_id' => $user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active' => true,
            'assigned_by' => $user->account_id,
        ]);

        return $user;
    }
}
