<?php

namespace Tests\Feature;

use App\Modules\UserManagement\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ErrorReportSecurityTest extends TestCase
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
            'user_agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
        ], $overrides);
    }

    // B1: guest cannot spoof user_id via request body

    public function test_guest_submitted_user_id_is_not_stored(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Create a real user so FK doesn't block the spoofed insert
        $victim = User::create([
            'username' => 'sec_victim',
            'email' => 'victim@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        // Guest POSTs with a real user's account_id — this is the spoof
        $this->postJson('/api/error-reports', $this->validPayload(['user_id' => $victim->account_id]));

        // The spoofed user_id must not be stored; guest reports must have null
        $this->assertDatabaseMissing('tbl_error_reports', ['user_id' => $victim->account_id]);
        $this->assertDatabaseHas('tbl_error_reports', ['user_id' => null]);
    }

    // B1: authenticated user's account_id is stored from session, not from request body

    public function test_authenticated_user_id_comes_from_session_not_body(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $attacker = User::create([
            'username' => 'sec_attacker',
            'email' => 'attacker@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $victim = User::create([
            'username' => 'sec_victim2',
            'email' => 'victim2@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        // Attacker sends victim's account_id as user_id
        $this->actingAs($attacker)
            ->postJson('/api/error-reports', $this->validPayload(['user_id' => $victim->account_id]));

        // The spoofed victim ID must not be stored
        $this->assertDatabaseMissing('tbl_error_reports', ['user_id' => $victim->account_id]);
        // The attacker's real account_id must be stored
        $this->assertDatabaseHas('tbl_error_reports', ['user_id' => $attacker->account_id]);
    }

    // B3: stack field longer than 10,000 characters must fail validation

    public function test_stack_field_exceeding_max_length_is_rejected(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/api/error-reports', $this->validPayload([
            'stack' => str_repeat('x', 10001),
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['stack']);
    }

    // B3: stack field at exactly 10,000 characters must be accepted

    public function test_stack_field_at_max_length_is_accepted(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->postJson('/api/error-reports', $this->validPayload([
            'stack' => str_repeat('x', 10000),
        ]));

        $response->assertCreated();
    }
}
