<?php

namespace Tests\Feature;

use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePictureControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Storage::fake('profile-pictures');
        Storage::fake('public');
    }

    private function makeUser(): User
    {
        $role = Role::create(['role_name' => 'Role-'.uniqid(), 'description' => '', 'is_active' => true]);

        $user = User::create([
            'username' => 'u_'.uniqid(),
            'email' => 'u_'.uniqid().'@test.com',
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

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->get('/profile-pictures/avatar.jpg');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_serve_file_from_private_disk(): void
    {
        $user = $this->makeUser();

        Storage::disk('profile-pictures')->put('avatar.jpg', 'fake-image-data');

        $response = $this->actingAs($user)->get('/profile-pictures/avatar.jpg');

        $response->assertOk();
    }

    public function test_missing_file_on_both_disks_returns_404(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/profile-pictures/nonexistent.jpg');

        $response->assertNotFound();
    }

    public function test_path_traversal_attempt_returns_404(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/profile-pictures/'.urlencode('../../../etc/passwd'));

        $response->assertNotFound();
    }

    public function test_legacy_file_on_public_disk_is_redirected(): void
    {
        $user = $this->makeUser();

        // Simulate an old file that only exists on the public disk
        Storage::disk('public')->put('profile-pictures/legacy.jpg', 'fake-image-data');

        $response = $this->actingAs($user)->get('/profile-pictures/profile-pictures/legacy.jpg');

        // Falls back to public storage redirect
        $response->assertRedirect('/storage/profile-pictures/legacy.jpg');
    }
}
