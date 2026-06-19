<?php

namespace Tests\Feature\Auth;

use App\Modules\UserManagement\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createUser(bool $mustChangePassword): User
    {
        $user = User::create([
            'username' => 'testuser_'.uniqid(),
            'email' => 'test_'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
        $user->must_change_password = $mustChangePassword;
        $user->save();

        return $user;
    }

    public function test_user_with_must_change_password_is_redirected_to_password_change_route(): void
    {
        $user = $this->createUser(mustChangePassword: true);

        $response = $this->actingAs($user)
            ->withSession(['_token' => csrf_token()])
            ->get(route('dashboard'));

        $response->assertRedirect(route('password.change'));
    }

    public function test_user_without_must_change_password_is_not_redirected_to_password_change(): void
    {
        $user = $this->createUser(mustChangePassword: false);

        $response = $this->actingAs($user)
            ->withSession(['_token' => csrf_token()])
            ->get(route('dashboard'));

        // Not redirected to password.change (may 403 due to missing role – that is expected)
        $this->assertNotEquals(
            route('password.change'),
            $response->headers->get('Location')
        );
    }

    public function test_user_with_must_change_password_can_access_password_change_route(): void
    {
        $user = $this->createUser(mustChangePassword: true);

        $response = $this->actingAs($user)
            ->withSession(['_token' => csrf_token()])
            ->get(route('password.change'));

        $response->assertStatus(200);
    }

    public function test_user_with_must_change_password_can_access_logout_route(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = $this->createUser(mustChangePassword: true);

        $response = $this->actingAs($user)
            ->withSession(['_token' => csrf_token()])
            ->post(route('logout'));

        // Should not redirect to password.change — logout should proceed normally
        $response->assertRedirect('/');
    }
}
