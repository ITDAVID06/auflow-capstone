<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies the GET /reports/forms endpoint returns the list of forms
 * available for reporting, gated by the same permissions as the reports module.
 */
class ReportsFormsListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_forms_list_returns_json_for_authorized_user(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $this->createActiveLockedForm($user);

        $response = $this->actingAs($user)->getJson(route('reports.forms'));

        $response->assertOk()
            ->assertJsonIsArray()
            ->assertJsonStructure([['id', 'form_name', 'form_code', 'status']]);
    }

    public function test_forms_list_returns_only_active_locked_forms(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);

        $this->createActiveLockedForm($user, 'Active Locked Form');
        $this->createForm($user, 'Inactive Form', status: 'Inactive', locked: true);
        $this->createForm($user, 'Unlocked Form', status: 'Active', locked: false);

        $response = $this->actingAs($user)->getJson(route('reports.forms'));

        $response->assertOk();

        $forms = $response->json();
        $formNames = array_column($forms, 'form_name');

        $this->assertContains('Active Locked Form', $formNames);
        $this->assertNotContains('Inactive Form', $formNames);
        $this->assertNotContains('Unlocked Form', $formNames);
    }

    public function test_forms_list_is_forbidden_for_unauthenticated_requests(): void
    {
        $this->getJson(route('reports.forms'))->assertUnauthorized();
    }

    public function test_forms_list_is_forbidden_without_reports_permission(): void
    {
        $user = $this->createUserWithPermissions(['forms.manage']); // wrong permission

        $this->actingAs($user)->getJson(route('reports.forms'))->assertForbidden();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

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
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
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

    private function createActiveLockedForm(User $creator, string $name = 'Test Report Form'): Form
    {
        return $this->createForm($creator, $name, status: 'Active', locked: true);
    }

    private function createForm(User $creator, string $name, string $status, bool $locked): Form
    {
        return Form::create([
            'form_name' => $name,
            'form_code' => 'FRM'.uniqid(),
            'version' => 1,
            'status' => $status,
            'is_locked' => $locked,
            'created_by' => $creator->account_id,
        ]);
    }
}
