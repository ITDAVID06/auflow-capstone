<?php

namespace Tests\Feature;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowAssignableUsersPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_get_assignable_users_is_permission_driven_not_role_name_driven(): void
    {
        $approvePermission = Permission::create([
            'permission_name' => 'Approve Requests',
            'slug' => 'requests.approve',
            'description' => 'Can approve requests',
            'resource' => 'requests',
            'action' => 'approve',
        ]);

        $viewOnlyPermission = Permission::create([
            'permission_name' => 'View Requests',
            'slug' => 'requests.view',
            'description' => 'Can view requests',
            'resource' => 'requests',
            'action' => 'view',
        ]);

        $nonApproverNamedRole = Role::create([
            'role_name' => 'Requester',
            'description' => 'Role name is not approver/admin',
            'is_active' => true,
        ]);
        $nonApproverNamedRole->permissions()->sync([$approvePermission->id]);

        $approverNamedRoleWithoutPermission = Role::create([
            'role_name' => 'Approver',
            'description' => 'Role name says approver but lacks approve permission',
            'is_active' => true,
        ]);
        $approverNamedRoleWithoutPermission->permissions()->sync([$viewOnlyPermission->id]);

        $permissionDrivenApprover = $this->createUserWithRole($nonApproverNamedRole, 'perm_driven');
        $nameOnlyApprover = $this->createUserWithRole($approverNamedRoleWithoutPermission, 'name_only');

        $users = app(WorkflowService::class)->getAssignableUsers();
        $ids = collect($users)->pluck('id')->all();

        $this->assertContains($permissionDrivenApprover->account_id, $ids);
        $this->assertNotContains($nameOnlyApprover->account_id, $ids);
    }

    public function test_get_assignable_users_excludes_expired_role_assignments(): void
    {
        $approvePermission = Permission::create([
            'permission_name' => 'Approve Requests',
            'slug' => 'requests.approve',
            'description' => 'Can approve requests',
            'resource' => 'requests',
            'action' => 'approve',
        ]);

        $approverRole = Role::create([
            'role_name' => 'Approver',
            'description' => 'Approver role',
            'is_active' => true,
        ]);
        $approverRole->permissions()->sync([$approvePermission->id]);

        $activeUser = $this->createUserWithRole($approverRole, 'active_user', null);
        $expiredUser = $this->createUserWithRole($approverRole, 'expired_user', now()->subDay()->toDateString());

        $users = app(WorkflowService::class)->getAssignableUsers();
        $ids = collect($users)->pluck('id')->all();

        $this->assertContains($activeUser->account_id, $ids);
        $this->assertNotContains($expiredUser->account_id, $ids);
    }

    private function createUserWithRole(Role $role, string $suffix, ?string $expiryDate = null): User
    {
        $user = User::create([
            'username' => 'workflow_'.$suffix,
            'email' => 'workflow_'.$suffix.'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $user->profile()->create([
            'account_id' => $user->account_id,
            'first_name' => 'Workflow',
            'last_name' => ucfirst($suffix),
        ]);

        UserRole::create([
            'account_id' => $user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'expiry_date' => $expiryDate,
            'is_active' => true,
            'assigned_by' => $user->account_id,
        ]);

        return $user;
    }
}
