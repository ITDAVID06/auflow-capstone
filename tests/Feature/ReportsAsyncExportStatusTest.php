<?php

namespace Tests\Feature;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportsAsyncExportStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_reports_async_export_status_endpoint_returns_payload(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $exportId = (string) fake()->uuid();

        Cache::put('reports.exports.'.$exportId, [
            'status' => 'processing',
            'requested_by' => $viewer->account_id,
            'filename' => null,
            'file_path' => null,
            'error' => null,
        ], now()->addHour());

        $this->actingAs($viewer)
            ->getJson(route('reports.exports.status', ['exportId' => $exportId]))
            ->assertOk()
            ->assertJsonPath('status', 'processing')
            ->assertJsonPath('export_id', $exportId);
    }

    public function test_reports_async_export_download_endpoint_returns_file_when_completed(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $exportId = (string) fake()->uuid();
        $filePath = 'exports/report-export-'.$exportId.'.csv';

        Storage::disk('local')->put($filePath, "col1,col2\nA,B\n");

        Cache::put('reports.exports.'.$exportId, [
            'status' => 'completed',
            'requested_by' => $viewer->account_id,
            'filename' => 'report-export-'.$exportId.'.csv',
            'file_path' => $filePath,
            'error' => null,
        ], now()->addHour());

        $response = $this->actingAs($viewer)
            ->get(route('reports.exports.download', ['exportId' => $exportId]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
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
