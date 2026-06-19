<?php

namespace Tests\Feature;

use App\Modules\AuditTrail\Models\AuditLog;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditTrailHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_audit_trail_routes_require_users_manage_permission(): void
    {
        $withPermission = $this->createUserWithPermissions(['users.manage']);
        $withoutPermission = $this->createUserWithPermissions(['forms.view']);

        $this->actingAs($withoutPermission)
            ->get(route('audit.index'))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->getJson(route('audit.data'))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->get(route('audit.export'))
            ->assertForbidden();

        $this->actingAs($withPermission)
            ->get(route('audit.index'))
            ->assertOk();

        $this->actingAs($withPermission)
            ->getJson(route('audit.data'))
            ->assertOk();
    }

    public function test_data_endpoint_enforces_per_page_cap_validation(): void
    {
        $user = $this->createUserWithPermissions(['users.manage']);

        $this->actingAs($user)
            ->getJson(route('audit.data', ['per_page' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_export_uses_same_filters_as_data_endpoint(): void
    {
        $user = $this->createUserWithPermissions(['users.manage']);

        AuditLog::create([
            'category' => 'security',
            'action' => 'login_success',
            'status' => 'Success',
            'description' => 'Alpha match event',
            'actor_name' => 'Alice',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::create([
            'category' => 'security',
            'action' => 'login_failed',
            'status' => 'Warning',
            'description' => 'Should be filtered out by status',
            'actor_name' => 'Bob',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        AuditLog::create([
            'category' => 'user_action',
            'action' => 'user_updated',
            'status' => 'Success',
            'description' => 'Should be filtered out by category',
            'actor_name' => 'Charlie',
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        $params = [
            'category' => 'security',
            'status' => 'Success',
            'search' => 'Alpha',
            'per_page' => 50,
        ];

        $dataResponse = $this->actingAs($user)
            ->getJson(route('audit.data', $params))
            ->assertOk();

        $dataPayload = $dataResponse->json('data');
        $this->assertCount(1, $dataPayload);
        $this->assertSame('Alpha match event', $dataPayload[0]['description']);

        $exportResponse = $this->actingAs($user)
            ->get(route('audit.export', $params))
            ->assertOk();

        $csv = $exportResponse->streamedContent();

        $this->assertStringContainsString('Alpha match event', $csv);
        $this->assertStringNotContainsString('Should be filtered out by status', $csv);
        $this->assertStringNotContainsString('Should be filtered out by category', $csv);
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
