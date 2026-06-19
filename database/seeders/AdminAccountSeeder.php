<?php

namespace Database\Seeders;

use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserProfile;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\UserManagement\Models\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminAccountSeeder extends Seeder
{
    /**
     * Seed the system administrator account.
     *
     * Depends on: PermissionSeeder (user statuses and roles must exist).
     * Default password: "password"
     */
    public function run(): void
    {
        $password = Hash::make('password');
        $today = now()->toDateString();

        $activeStatusId = UserStatus::query()
            ->where('status_name', 'Active')
            ->value('id');

        if (! $activeStatusId) {
            $this->command?->error('Missing Active user status. Run PermissionSeeder first.');

            return;
        }

        $adminRoleId = Role::query()
            ->where('role_name', 'Admin')
            ->value('id');

        if (! $adminRoleId) {
            $this->command?->error('Missing Admin role. Run PermissionSeeder first.');

            return;
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@auf.edu.ph'],
            [
                'username' => 'admin',
                'password' => $password,
                'must_change_password' => false,
                'user_status_id' => $activeStatusId,
            ]
        );

        UserProfile::updateOrCreate(
            ['account_id' => $admin->account_id],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'middle_name' => 'A.',
                'employee_id' => 'EMP0001',
                'phone' => '+639171234567',
                'address' => 'AUF Main Campus, Angeles City, Pampanga',
                'date_of_birth' => '1985-06-15',
                'gender' => 'Male',
            ]
        );

        UserRole::updateOrCreate(
            ['account_id' => $admin->account_id, 'role_id' => $adminRoleId],
            [
                'assigned_date' => $today,
                'is_active' => 1,
                'assigned_by' => $admin->account_id,
            ]
        );

        $this->command->info('✓ Admin account seeded (admin@auf.edu.ph / password)');
    }
}
