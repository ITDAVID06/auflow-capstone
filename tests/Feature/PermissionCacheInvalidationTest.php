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

class PermissionCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_permission_updates_invalidate_permission_cache(): void
    {
        $permission = Permission::create([
            'permission_name' => 'Workflow Manage',
            'slug' => 'workflows.manage',
            'description' => 'Manage workflows',
            'resource' => 'workflows',
            'action' => 'manage',
        ]);

        $role = Role::create([
            'role_name' => 'Workflow Admin',
            'description' => 'Workflow admin role',
            'is_active' => true,
        ]);
        $role->permissions()->sync([$permission->id]);

        $user = User::create([
            'username' => 'cache_user',
            'email' => 'cache_user@test.com',
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

        $first = $user->allPermissions();
        $this->assertContains('workflows.manage', $first);

        $permission->update([
            'slug' => 'workflows.edit',
            'action' => 'edit',
        ]);

        $second = $user->fresh()->allPermissions();
        $this->assertNotContains('workflows.manage', $second);
        $this->assertContains('workflows.edit', $second);
    }
}
