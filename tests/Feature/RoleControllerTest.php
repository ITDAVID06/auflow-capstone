<?php

namespace Tests\Feature;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // ── index ──────────────────────────────────────────────────────────────────

    public function test_roles_index_is_accessible_with_roles_manage_permission(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $this->actingAs($actor)
            ->get('/user-management/roles')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('UserManagement/Roles/Index'));
    }

    public function test_roles_index_is_accessible_with_users_manage_bypass(): void
    {
        $usersManage = $this->createPermission('users.manage', 'Manage Users', 'users', 'manage');
        $actor = $this->createUserWithPermissions([$usersManage->id]);

        $this->actingAs($actor)
            ->get('/user-management/roles')
            ->assertOk();
    }

    public function test_roles_index_is_forbidden_without_permission(): void
    {
        $actor = $this->createUserWithPermissions([]);

        $this->actingAs($actor)
            ->get('/user-management/roles')
            ->assertForbidden();
    }

    public function test_roles_index_is_forbidden_with_only_organizations_manage(): void
    {
        $orgsManage = $this->createPermission('organizations.manage', 'Manage Organizations', 'organizations', 'manage');
        $actor = $this->createUserWithPermissions([$orgsManage->id]);

        $this->actingAs($actor)
            ->get('/user-management/roles')
            ->assertForbidden();
    }

    // ── create / store ─────────────────────────────────────────────────────────

    public function test_roles_create_page_renders(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $this->actingAs($actor)
            ->get('/user-management/roles/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('UserManagement/Roles/Create'));
    }

    public function test_store_creates_role_and_redirects_to_roles_index(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $formsView = $this->createPermission('forms.view', 'View Forms', 'forms', 'view');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'New Test Role',
                'description' => 'A test role',
                'is_active' => true,
                'permission_ids' => [$formsView->id],
            ])
            ->assertRedirect(route('user-management.roles.index'));

        $this->assertDatabaseHas('tbl_role', ['role_name' => 'New Test Role']);
    }

    public function test_store_is_forbidden_without_roles_manage(): void
    {
        $actor = $this->createUserWithPermissions([]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'Blocked',
                'description' => '',
                'is_active' => true,
                'permission_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_store_is_forbidden_for_users_manage_without_roles_manage(): void
    {
        // users.manage triggers the before() bypass in RolePolicy, so a user with
        // only users.manage should NOT be blocked by RolePolicy::create().
        // However, a user with neither roles.manage nor users.manage must be forbidden.
        $formsView = $this->createPermission('forms.view', 'View Forms', 'forms', 'view');
        $actor = $this->createUserWithPermissions([$formsView->id]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'Blocked Role',
                'description' => '',
                'is_active' => true,
                'permission_ids' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tbl_role', ['role_name' => 'Blocked Role']);
    }

    public function test_store_prevents_escalation_to_global_permission(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $usersManage = $this->createPermission('users.manage', 'Manage Users', 'users', 'manage');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'Escalation Role',
                'description' => '',
                'is_active' => true,
                'permission_ids' => [$usersManage->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tbl_role', ['role_name' => 'Escalation Role']);
    }

    public function test_store_prevents_escalation_to_roles_manage(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'Roles Escalation Role',
                'description' => '',
                'is_active' => true,
                'permission_ids' => [$rolesManage->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tbl_role', ['role_name' => 'Roles Escalation Role']);
    }

    public function test_store_prevents_escalation_to_submissions_override(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $override = $this->createPermission('submissions.override', 'Override Submissions', 'submissions', 'override');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'Override Escalation Role',
                'description' => '',
                'is_active' => true,
                'permission_ids' => [$override->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tbl_role', ['role_name' => 'Override Escalation Role']);
    }

    public function test_store_prevents_escalation_to_submissions_view(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $subsView = $this->createPermission('submissions.view', 'View Submissions', 'submissions', 'view');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'View Escalation Role',
                'description' => '',
                'is_active' => true,
                'permission_ids' => [$subsView->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tbl_role', ['role_name' => 'View Escalation Role']);
    }

    public function test_store_authorization_fires_before_any_db_write(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $usersManage = $this->createPermission('users.manage', 'Manage Users', 'users', 'manage');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $roleInserted = false;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$roleInserted) {
            if (str_contains($query->sql, 'insert into `tbl_role`')) {
                $roleInserted = true;
            }
        });

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'Auth Order Role',
                'description' => '',
                'is_active' => true,
                'permission_ids' => [$usersManage->id],
            ])
            ->assertForbidden();

        $this->assertFalse($roleInserted, 'tbl_role must not be written before the authorization check runs.');
    }

    public function test_update_authorization_fires_before_any_db_write(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $usersManage = $this->createPermission('users.manage', 'Manage Users', 'users', 'manage');
        $formsView = $this->createPermission('forms.view', 'View Forms', 'forms', 'view');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $role = Role::create([
            'role_name' => 'Existing Role',
            'description' => '',
            'is_active' => true,
        ]);

        $roleUpdated = false;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$roleUpdated, $role) {
            if (str_contains($query->sql, 'update `tbl_role`') &&
                str_contains($query->sql, '`id` = ?') ||
                (str_contains($query->sql, 'update `tbl_role`') && in_array($role->id, $query->bindings))) {
                $roleUpdated = true;
            }
        });

        $this->actingAs($actor)
            ->put("/user-management/roles/{$role->id}", [
                'role_name' => 'Updated Role',
                'description' => '',
                'is_active' => true,
                'permission_ids' => [$usersManage->id],
            ])
            ->assertForbidden();

        $this->assertFalse($roleUpdated, 'tbl_role must not be updated before the authorization check runs.');
    }

    // ── edit / update ──────────────────────────────────────────────────────────

    public function test_roles_edit_page_renders(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $role = Role::create([
            'role_name' => 'Editable Role',
            'description' => 'A role',
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->get("/user-management/roles/{$role->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('UserManagement/Roles/Edit'));
    }

    public function test_update_role_redirects_to_roles_index(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $formsView = $this->createPermission('forms.view', 'View Forms', 'forms', 'view');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $role = Role::create([
            'role_name' => 'Updatable Role',
            'description' => '',
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->put("/user-management/roles/{$role->id}", [
                'role_name' => 'Renamed Role',
                'description' => 'Updated',
                'is_active' => true,
                'permission_ids' => [$formsView->id],
            ])
            ->assertRedirect(route('user-management.roles.index'));

        $this->assertDatabaseHas('tbl_role', ['id' => $role->id, 'role_name' => 'Renamed Role']);
    }

    // ── destroy ────────────────────────────────────────────────────────────────

    public function test_destroy_deletes_unassigned_role(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $role = Role::create([
            'role_name' => 'Deletable Role',
            'description' => '',
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->delete("/user-management/roles/{$role->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('tbl_role', ['id' => $role->id]);
    }

    public function test_destroy_rejects_role_assigned_to_users(): void
    {
        $rolesManage = $this->createPermission('roles.manage', 'Manage Roles', 'roles', 'manage');
        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $role = Role::create([
            'role_name' => 'In-Use Role',
            'description' => '',
            'is_active' => true,
        ]);

        // Assign role to actor so it is "in use"
        UserRole::create([
            'account_id' => $actor->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active' => true,
            'assigned_by' => $actor->account_id,
        ]);

        $this->actingAs($actor)
            ->delete("/user-management/roles/{$role->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('tbl_role', ['id' => $role->id]);
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private function createPermission(string $slug, string $name, string $resource, string $action): Permission
    {
        return Permission::create([
            'permission_name' => $name,
            'slug' => $slug,
            'description' => $name,
            'resource' => $resource,
            'action' => $action,
        ]);
    }

    private function createUserWithPermissions(array $permissionIds): User
    {
        $role = Role::create([
            'role_name' => 'Actor Role '.uniqid(),
            'description' => 'Test role',
            'is_active' => true,
        ]);

        $role->permissions()->sync($permissionIds);

        $user = User::create([
            'username' => 'actor_'.uniqid(),
            'email' => 'actor_'.uniqid().'@test.com',
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
