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

class StudentDashboardRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_student_dashboard_routes_require_dashboard_student_permission(): void
    {
        $withoutPermission = $this->createUserWithPermissions(['forms.view']);

        $this->actingAs($withoutPermission)
            ->get(route('student-dashboard.index'))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->getJson(route('student-dashboard.submissions'))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->getJson(route('student-dashboard.metrics'))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->get(route('student-dashboard.forms.index'))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->get(route('student-dashboard.progress-attachments.download', ['id' => 1]))
            ->assertForbidden();
    }

    public function test_student_dashboard_routes_are_accessible_with_dashboard_student_permission(): void
    {
        $withPermission = $this->createUserWithPermissions(['dashboard.student']);

        $this->actingAs($withPermission)
            ->get(route('student-dashboard.index'))
            ->assertOk();

        $this->actingAs($withPermission)
            ->getJson(route('student-dashboard.submissions'))
            ->assertOk();

        $this->actingAs($withPermission)
            ->getJson(route('student-dashboard.metrics'))
            ->assertOk();

        $this->actingAs($withPermission)
            ->get(route('student-dashboard.forms.index'))
            ->assertOk();
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
}
