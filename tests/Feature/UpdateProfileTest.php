<?php

namespace Tests\Feature;

use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserProfile;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function makeUser(string $usernameVal = 'initial_user'): User
    {
        $user = User::create([
            'username' => $usernameVal,
            'email' => $usernameVal.'@example.com',
            'password' => Hash::make('Password123!'),
            'user_status_id' => 1,
        ]);

        UserProfile::create([
            'account_id' => $user->account_id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        return $user;
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => 'new_username',
                'first_name' => 'Maria',
                'middle_name' => 'Cruz',
                'last_name' => 'Santos',
                'address' => '123 University Ave',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated.');

        $this->assertDatabaseHas('tbl_user', [
            'account_id' => $user->account_id,
            'username' => 'new_username',
        ]);

        $this->assertDatabaseHas('tbl_userprofile', [
            'account_id' => $user->account_id,
            'first_name' => 'Maria',
            'middle_name' => 'Cruz',
            'last_name' => 'Santos',
            'address' => '123 University Ave',
        ]);
    }

    public function test_username_must_be_unique_across_users(): void
    {
        $this->makeUser('taken_username');
        $user = $this->makeUser('another_user');

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => 'taken_username',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_user_can_keep_their_own_username(): void
    {
        $user = $this->makeUser('my_username');

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => 'my_username',
                'first_name' => 'Updated',
                'last_name' => 'Name',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated.');
    }

    public function test_username_with_spaces_is_rejected(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => 'user name',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_username_starting_with_digit_is_rejected(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => '1invalid',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_email_cannot_be_changed_via_profile_update(): void
    {
        $user = $this->makeUser();
        $originalEmail = $user->email;

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => 'valid_user',
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'hacker@example.com', // ignored by FormRequest
            ]);

        $this->assertDatabaseHas('tbl_user', [
            'account_id' => $user->account_id,
            'email' => $originalEmail,
        ]);
    }

    public function test_assignable_users_cache_is_busted_on_profile_update(): void
    {
        $user = $this->makeUser();

        Cache::put('auflow:workflow:assignable_users', ['cached_data'], 60);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => 'valid_user',
                'first_name' => 'Updated',
                'last_name' => 'Name',
            ]);

        $this->assertNull(Cache::get('auflow:workflow:assignable_users'));
    }

    public function test_user_can_update_student_id(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => 'initial_user',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'student_id' => 'STU-2024-001',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated.');

        $this->assertDatabaseHas('tbl_userprofile', [
            'account_id' => $user->account_id,
            'student_id' => 'STU-2024-001',
        ]);
    }

    public function test_user_can_update_employee_id(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => 'initial_user',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'employee_id' => 'EMP-2024-099',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated.');

        $this->assertDatabaseHas('tbl_userprofile', [
            'account_id' => $user->account_id,
            'employee_id' => 'EMP-2024-099',
        ]);
    }

    public function test_student_id_must_not_exceed_max_length(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'username' => 'initial_user',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'student_id' => str_repeat('A', 101),
            ]);

        $response->assertSessionHasErrors('student_id');
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->patch(route('profile.update'), [
            'username' => 'hacker',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        // Unauthenticated users are redirected (exact destination depends on middleware chain)
        $response->assertRedirect();
        $this->assertFalse(auth()->check());
    }
}
