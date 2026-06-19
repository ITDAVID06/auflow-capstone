<?php

namespace Tests\Feature;

use App\Modules\UserManagement\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_seeder_creates_expected_account_counts_with_role_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(AccountSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'admin@auf.test')->count());
        $this->assertSame(5, User::query()->where('email', 'like', 'approver%@auf.test')->count());
        $this->assertSame(5, User::query()->where('email', 'like', 'requester%@auf.test')->count());

        $admin = User::query()->where('email', 'admin@auf.test')->firstOrFail();
        $this->assertTrue($admin->directRoles()->where('role_name', 'Admin')->exists());
        $this->assertContains('dashboard.admin', $admin->allPermissions());
        $this->assertContains('users.manage', $admin->allPermissions());

        $approver = User::query()->where('email', 'approver1@auf.test')->firstOrFail();
        $this->assertTrue($approver->directRoles()->where('role_name', 'Approver')->exists());
        $this->assertContains('requests.approve', $approver->allPermissions());
        $this->assertContains('dashboard.staff', $approver->allPermissions());

        $requester = User::query()->where('email', 'requester1@auf.test')->firstOrFail();
        $this->assertTrue($requester->directRoles()->where('role_name', 'Requester')->exists());
        $this->assertContains('forms.student-access', $requester->allPermissions());
        $this->assertNotContains('requests.approve', $requester->allPermissions());
    }
}
