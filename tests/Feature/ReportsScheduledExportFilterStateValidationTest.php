<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\Reports\Models\ScheduledExport;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportsScheduledExportFilterStateValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->user = $this->createUserWithPermissions(['submissions.view']);
        $this->form = $this->createFormWithTextField($this->user);
    }

    public function test_store_accepts_null_filter_state(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('reports.scheduled-exports.store'), [
                'form_id' => $this->form->id,
                'recipient_email' => 'test@example.com',
                'frequency' => 'daily',
                'export_type' => 'csv',
                'filter_state' => null,
            ])
            ->assertStatus(201);
    }

    public function test_store_accepts_valid_filter_state_with_leaf_filter(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('reports.scheduled-exports.store'), [
                'form_id' => $this->form->id,
                'recipient_email' => 'test@example.com',
                'frequency' => 'daily',
                'export_type' => 'csv',
                'filter_state' => [
                    'filters' => [
                        ['column' => 'field_text', 'operator' => 'contains', 'value' => 'hello'],
                    ],
                ],
            ])
            ->assertStatus(201);
    }

    public function test_store_rejects_filter_state_with_unknown_column(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('reports.scheduled-exports.store'), [
                'form_id' => $this->form->id,
                'recipient_email' => 'test@example.com',
                'frequency' => 'daily',
                'export_type' => 'csv',
                'filter_state' => [
                    'filters' => [
                        ['column' => 'nonexistent_column', 'operator' => 'eq', 'value' => 'x'],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter_state.filters.0.column']);
    }

    public function test_store_rejects_triple_nested_groups(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('reports.scheduled-exports.store'), [
                'form_id' => $this->form->id,
                'recipient_email' => 'test@example.com',
                'frequency' => 'daily',
                'export_type' => 'csv',
                'filter_state' => [
                    'filters' => [
                        [
                            'logic' => 'and',
                            'filters' => [
                                [
                                    'logic' => 'or',  // group inside group = exceeds depth
                                    'filters' => [
                                        ['column' => 'field_text', 'operator' => 'eq', 'value' => 'x'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter_state']);
    }

    public function test_update_rejects_filter_state_with_invalid_operator(): void
    {
        $export = ScheduledExport::create([
            'form_id' => $this->form->id,
            'recipient_email' => 'test@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'is_active' => true,
            'created_by' => $this->user->account_id,
            'filter_state' => [],
        ]);

        $this->actingAs($this->user)
            ->putJson(route('reports.scheduled-exports.update', $export->id), [
                'filter_state' => [
                    'filters' => [
                        ['column' => 'field_text', 'operator' => 'not_a_real_operator', 'value' => 'x'],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter_state.filters.0.operator']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

        $role = Role::create(['role_name' => 'Role ' . uniqid(), 'description' => 'Test', 'is_active' => true]);
        $role->permissions()->sync($permissionIds);

        $user = User::create([
            'username' => 'user_' . uniqid(),
            'email' => 'user_' . uniqid() . '@test.com',
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

    private function createFormWithTextField(User $creator): Form
    {
        $form = Form::create([
            'form_name' => 'Test Form ' . uniqid(),
            'form_code' => 'TF' . uniqid(),
            'description' => 'Test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text',
            'label' => 'Text Field',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        return $form;
    }
}
