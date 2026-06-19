<?php

namespace Tests\Feature\ErrorReports;

use App\Modules\ErrorReports\Models\ErrorReport;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Tests\TestCase;

class AdminErrorReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Inertia::version(fn () => null);

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createAdminUser(string $suffix): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'error-reports.manage'],
            [
                'permission_name' => 'Manage Error Reports',
                'description' => 'Can view and triage bug reports',
                'resource' => 'error-reports',
                'action' => 'manage',
            ]
        );

        $role = Role::create([
            'role_name' => 'Admin_'.$suffix,
            'description' => 'Admin role',
            'is_active' => true,
        ]);
        $role->permissions()->sync([$permission->id]);

        $user = User::create([
            'username' => 'admin_'.$suffix,
            'email' => 'admin_'.$suffix.'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        UserRole::create([
            'account_id' => $user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'expiry_date' => null,
            'is_active' => true,
            'assigned_by' => $user->account_id,
        ]);

        return $user;
    }

    private function createPlainUser(string $suffix): User
    {
        return User::create([
            'username' => 'plain_'.$suffix,
            'email' => 'plain_'.$suffix.'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
    }

    public function test_guest_cannot_access_admin_error_reports_index(): void
    {
        $response = $this->get('/admin/error-reports');

        $response->assertRedirect('/login');
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = $this->createPlainUser('noperm');

        $response = $this->actingAs($user)
            ->withSession(['_token' => csrf_token()])
            ->get('/admin/error-reports');

        $response->assertForbidden();
    }

    public function test_admin_can_view_error_reports_index(): void
    {
        $admin = $this->createAdminUser('view');

        ErrorReport::create([
            'message' => 'Test error',
            'stack' => 'at Component (app.tsx:42)',
            'url' => 'https://example.com/page',
            'user_agent' => 'Mozilla/5.0',
            'status' => 'new',
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('X-Inertia', 'true')
            ->get('/admin/error-reports');

        $response->assertOk();
        $response->assertJson(['component' => 'ErrorReports/Index']);
    }

    public function test_admin_can_update_error_report_status_to_reviewed(): void
    {
        $admin = $this->createAdminUser('update1');

        $report = ErrorReport::create([
            'message' => 'Bug to review',
            'stack' => 'at Component (app.tsx:10)',
            'url' => 'https://example.com',
            'user_agent' => 'Mozilla/5.0',
            'status' => 'new',
        ]);

        $response = $this->actingAs($admin)
            ->patch("/admin/error-reports/{$report->id}", ['status' => 'reviewed']);

        $response->assertRedirect();
        $this->assertDatabaseHas('tbl_error_reports', [
            'id' => $report->id,
            'status' => 'reviewed',
        ]);
    }

    public function test_admin_can_update_error_report_status_to_resolved(): void
    {
        $admin = $this->createAdminUser('update2');

        $report = ErrorReport::create([
            'message' => 'Bug to resolve',
            'stack' => 'at Component (app.tsx:20)',
            'url' => 'https://example.com',
            'user_agent' => 'Mozilla/5.0',
            'status' => 'reviewed',
        ]);

        $response = $this->actingAs($admin)
            ->patch("/admin/error-reports/{$report->id}", ['status' => 'resolved']);

        $response->assertRedirect();
        $this->assertDatabaseHas('tbl_error_reports', [
            'id' => $report->id,
            'status' => 'resolved',
        ]);
    }

    public function test_admin_can_update_error_report_status_to_in_progress(): void
    {
        $admin = $this->createAdminUser('update3');

        $report = ErrorReport::create([
            'message' => 'Bug being investigated',
            'stack' => 'at Component (app.tsx:30)',
            'url' => 'https://example.com',
            'user_agent' => 'Mozilla/5.0',
            'status' => 'reviewed',
        ]);

        $response = $this->actingAs($admin)
            ->patch("/admin/error-reports/{$report->id}", ['status' => 'in_progress']);

        $response->assertRedirect();
        $this->assertDatabaseHas('tbl_error_reports', [
            'id' => $report->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_admin_can_update_error_report_status_to_dismissed(): void
    {
        $admin = $this->createAdminUser('update4');

        $report = ErrorReport::create([
            'message' => 'Not a real bug',
            'stack' => 'at Component (app.tsx:40)',
            'url' => 'https://example.com',
            'user_agent' => 'Mozilla/5.0',
            'status' => 'new',
        ]);

        $response = $this->actingAs($admin)
            ->patch("/admin/error-reports/{$report->id}", ['status' => 'dismissed']);

        $response->assertRedirect();
        $this->assertDatabaseHas('tbl_error_reports', [
            'id' => $report->id,
            'status' => 'dismissed',
        ]);
    }

    public function test_invalid_status_value_returns_422(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->createAdminUser('invalid');

        $report = ErrorReport::create([
            'message' => 'Bug',
            'stack' => 'at Component (app.tsx:1)',
            'url' => 'https://example.com',
            'user_agent' => 'Mozilla/5.0',
            'status' => 'new',
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/admin/error-reports/{$report->id}", ['status' => 'hacked']);

        $response->assertUnprocessable();
    }
}
