<?php

namespace Tests\Feature\ErrorReports;

use App\Modules\ErrorReports\Models\ErrorReport;
use App\Modules\UserManagement\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ErrorReportAdminIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $permId = DB::table('tbl_permission')->insertGetId([
            'permission_name' => 'Manage Error Reports',
            'slug' => 'error-reports.manage',
            'resource' => 'error-reports',
            'action' => 'manage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('tbl_role')->insertGetId([
            'role_name' => 'Admin',
            'description' => 'Administrator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tbl_role_permission')->insert([
            'role_id' => $roleId,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->adminUser = User::create([
            'username' => 'admin_user',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        DB::table('tbl_user_role')->insert([
            'account_id' => $this->adminUser->account_id,
            'role_id' => $roleId,
            'is_active' => 1,
            'assigned_date' => now(),
            'assigned_by' => $this->adminUser->account_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeReport(array $overrides = []): ErrorReport
    {
        return ErrorReport::create(array_merge([
            'message' => 'Test error',
            'stack' => 'Stack trace',
            'url' => 'https://auflow.example.com/',
            'user_agent' => 'Mozilla/5.0',
            'status' => 'new',
        ], $overrides));
    }

    /** @test */
    public function test_admin_index_returns_inertia_page_with_reports(): void
    {
        config(['app.env' => 'testing']);
        $this->makeReport();

        $response = $this->actingAs($this->adminUser)
            ->withSession(['_token' => csrf_token()])
            ->get('/admin/error-reports', ['X-Inertia' => 'true']);

        $response->assertOk();
        $inertia = $response->json();
        $this->assertNotEmpty($inertia['props']['reports']);
    }

    /** @test */
    public function test_reporter_name_is_included_when_user_is_linked(): void
    {
        config(['app.env' => 'testing']);

        $reporter = User::create([
            'username' => 'reporteruser',
            'email' => 'reporter@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $this->makeReport(['user_id' => $reporter->account_id]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['_token' => csrf_token()])
            ->get('/admin/error-reports', ['X-Inertia' => 'true']);

        $response->assertOk();
        $reports = $response->json('props.reports');
        $this->assertNotNull($reports[0]['reporter_name']);
        $this->assertEquals('reporteruser', $reports[0]['reporter_name']);
    }

    /** @test */
    public function test_reporter_name_is_null_for_guest_report(): void
    {
        config(['app.env' => 'testing']);
        $this->makeReport(['user_id' => null]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['_token' => csrf_token()])
            ->get('/admin/error-reports', ['X-Inertia' => 'true']);

        $response->assertOk();
        $reports = $response->json('props.reports');
        $this->assertNull($reports[0]['reporter_name']);
    }

    /** @test */
    public function test_status_filter_returns_only_matching_reports(): void
    {
        config(['app.env' => 'testing']);
        $this->makeReport(['status' => 'new']);
        $this->makeReport(['status' => 'resolved']);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['_token' => csrf_token()])
            ->get('/admin/error-reports?status=resolved', ['X-Inertia' => 'true']);

        $response->assertOk();
        $reports = $response->json('props.reports');
        $this->assertCount(1, $reports);
        $this->assertEquals('resolved', $reports[0]['status']);
    }

    /** @test */
    public function test_invalid_status_filter_returns_all_reports(): void
    {
        config(['app.env' => 'testing']);
        $this->makeReport(['status' => 'new']);
        $this->makeReport(['status' => 'resolved']);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['_token' => csrf_token()])
            ->get('/admin/error-reports?status=invalid', ['X-Inertia' => 'true']);

        $response->assertOk();
        $reports = $response->json('props.reports');
        $this->assertCount(2, $reports);
    }

    /** @test */
    public function test_filters_prop_reflects_active_status_filter(): void
    {
        config(['app.env' => 'testing']);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['_token' => csrf_token()])
            ->get('/admin/error-reports?status=reviewed', ['X-Inertia' => 'true']);

        $response->assertOk();
        $this->assertEquals('reviewed', $response->json('props.filters.status'));
    }
}
