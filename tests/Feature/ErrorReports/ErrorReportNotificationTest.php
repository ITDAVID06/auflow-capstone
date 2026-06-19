<?php

namespace Tests\Feature\ErrorReports;

use App\Modules\UserManagement\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ErrorReportNotificationTest extends TestCase
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
            'description' => 'Can view and triage bug reports',
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'message' => 'TypeError: Cannot read properties of undefined',
            'stack' => "TypeError\n  at Component (app.tsx:42)",
            'url' => 'https://auflow.example.com/forms/1',
            'user_agent' => 'Mozilla/5.0',
        ], $overrides);
    }

    public function test_notification_is_sent_to_error_reports_manage_users_on_submit(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->postJson('/api/error-reports', $this->validPayload());

        $this->assertDatabaseHas('tbl_notification', [
            'account_id' => $this->adminUser->account_id,
            'type' => 'error_report_submitted',
        ]);
    }

    public function test_no_notification_sent_when_no_users_have_the_permission(): void
    {
        DB::table('tbl_role_permission')->truncate();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->postJson('/api/error-reports', $this->validPayload());

        $this->assertDatabaseEmpty('tbl_notification');
    }

    public function test_report_is_still_stored_even_with_empty_recipient_list(): void
    {
        DB::table('tbl_role_permission')->truncate();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/api/error-reports', $this->validPayload([
            'message' => 'TypeError: Something went wrong',
        ]));

        $response->assertCreated();
        $this->assertDatabaseHas('tbl_error_reports', ['message' => 'TypeError: Something went wrong']);
    }
}
