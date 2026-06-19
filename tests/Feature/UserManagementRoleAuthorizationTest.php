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

class UserManagementRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_org_manager_cannot_create_role_with_global_permission(): void
    {
        $rolesManage = Permission::create([
            'permission_name' => 'Manage Roles',
            'slug' => 'roles.manage',
            'description' => 'Can manage roles',
            'resource' => 'roles',
            'action' => 'manage',
        ]);

        $globalUsersManage = Permission::create([
            'permission_name' => 'Manage Users',
            'slug' => 'users.manage',
            'description' => 'Global user management',
            'resource' => 'users',
            'action' => 'manage',
        ]);

        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'Org Escalation Attempt',
                'description' => 'Should be forbidden',
                'is_active' => true,
                'permission_ids' => [$globalUsersManage->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tbl_role', ['role_name' => 'Org Escalation Attempt']);
    }

    public function test_org_manager_can_create_role_with_non_global_permissions(): void
    {
        $rolesManage = Permission::create([
            'permission_name' => 'Manage Roles',
            'slug' => 'roles.manage',
            'description' => 'Can manage roles',
            'resource' => 'roles',
            'action' => 'manage',
        ]);

        $formsView = Permission::create([
            'permission_name' => 'View Forms',
            'slug' => 'forms.view',
            'description' => 'Can view forms',
            'resource' => 'forms',
            'action' => 'view',
        ]);

        $actor = $this->createUserWithPermissions([$rolesManage->id]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'Org Scoped Role',
                'description' => 'Allowed role',
                'is_active' => true,
                'permission_ids' => [$formsView->id],
            ])
            ->assertRedirect(route('user-management.roles.index'));

        $this->assertDatabaseHas('tbl_role', ['role_name' => 'Org Scoped Role']);

        $createdRole = Role::where('role_name', 'Org Scoped Role')->firstOrFail();
        $this->assertDatabaseHas('tbl_role_permission', [
            'role_id' => $createdRole->id,
            'permission_id' => $formsView->id,
        ]);
    }

    public function test_user_without_required_permissions_is_blocked_by_middleware(): void
    {
        $user = $this->createUserWithPermissions([]);

        $this->actingAs($user)
            ->post('/user-management/roles', [
                'role_name' => 'Blocked By Middleware',
                'description' => 'Should not pass middleware',
                'is_active' => true,
                'permission_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_global_users_manage_permission_bypasses_role_policy_restrictions(): void
    {
        $globalUsersManage = Permission::create([
            'permission_name' => 'Manage Users',
            'slug' => 'users.manage',
            'description' => 'Global user management',
            'resource' => 'users',
            'action' => 'manage',
        ]);

        $actor = $this->createUserWithPermissions([$globalUsersManage->id]);

        $this->actingAs($actor)
            ->post('/user-management/roles', [
                'role_name' => 'Global Allowed Role',
                'description' => 'Allowed via users.manage policy before()',
                'is_active' => true,
                'permission_ids' => [$globalUsersManage->id],
            ])
            ->assertRedirect(route('user-management.roles.index'));

        $this->assertDatabaseHas('tbl_role', ['role_name' => 'Global Allowed Role']);
    }

    public function test_role_sync_permissions_invalidates_user_permission_cache(): void
    {
        $formsView = Permission::create([
            'permission_name' => 'View Forms',
            'slug' => 'forms.view',
            'description' => 'Can view forms',
            'resource' => 'forms',
            'action' => 'view',
        ]);

        $formsEdit = Permission::create([
            'permission_name' => 'Edit Forms',
            'slug' => 'forms.edit',
            'description' => 'Can edit forms',
            'resource' => 'forms',
            'action' => 'edit',
        ]);

        $actor = $this->createUserWithPermissions([$formsView->id]);

        $initialPermissions = $actor->allPermissions();
        $this->assertContains('forms.view', $initialPermissions);
        $this->assertNotContains('forms.edit', $initialPermissions);

        $role = $actor->directRoles()->firstOrFail();
        $role->syncPermissions([$formsEdit->id]);

        $updatedPermissions = $actor->fresh()->allPermissions();
        $this->assertContains('forms.edit', $updatedPermissions);
        $this->assertNotContains('forms.view', $updatedPermissions);
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
