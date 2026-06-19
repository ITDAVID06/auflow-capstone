<?php

namespace Tests\Feature\ErrorReports;

use App\Modules\UserManagement\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreErrorReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'message' => 'TypeError: Cannot read properties of undefined',
            'stack' => "TypeError: Cannot read properties of undefined\n  at Component (app.tsx:42)",
            'url' => 'https://auflow.example.com/forms/123',
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
        ], $overrides);
    }

    public function test_guest_can_submit_error_report(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/api/error-reports', $this->validPayload());

        $response->assertCreated();
        $response->assertJson(['ok' => true]);
    }

    public function test_report_is_persisted_in_database(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->postJson('/api/error-reports', $this->validPayload([
            'message' => 'ReferenceError: foo is not defined',
        ]));

        $this->assertDatabaseHas('tbl_error_reports', [
            'message' => 'ReferenceError: foo is not defined',
            'status' => 'new',
        ]);
    }

    public function test_authenticated_user_account_id_is_stored_automatically(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = User::create([
            'username' => 'err_user',
            'email' => 'err@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $this->actingAs($user)
            ->postJson('/api/error-reports', $this->validPayload());

        $this->assertDatabaseHas('tbl_error_reports', [
            'user_id' => $user->account_id,
        ]);
    }

    public function test_guest_report_stores_null_user_id(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/api/error-reports', $this->validPayload());

        $response->assertCreated();
        $this->assertDatabaseHas('tbl_error_reports', [
            'user_id' => null,
            'status' => 'new',
        ]);
    }

    public function test_optional_comment_is_stored(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->postJson('/api/error-reports', $this->validPayload(['comment' => 'I was clicking the submit button']));

        $this->assertDatabaseHas('tbl_error_reports', [
            'comment' => 'I was clicking the submit button',
        ]);
    }

    public function test_missing_required_fields_returns_422(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/api/error-reports', [
            'message' => 'Some error',
            // missing stack, url, user_agent
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['stack', 'url', 'user_agent']);
    }

    public function test_message_max_length_is_enforced(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/api/error-reports', $this->validPayload([
            'message' => str_repeat('x', 2001),
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_comment_max_length_is_enforced(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/api/error-reports', $this->validPayload([
            'comment' => str_repeat('c', 1001),
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['comment']);
    }

    public function test_rate_limit_blocks_after_ten_requests(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/error-reports', $this->validPayload())->assertCreated();
        }

        $this->postJson('/api/error-reports', $this->validPayload())->assertStatus(429);
    }
}
