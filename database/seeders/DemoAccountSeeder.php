<?php

namespace Database\Seeders;

use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserProfile;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\UserManagement\Models\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    /**
     * Seed demo staff and student accounts for development/demo environments.
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

        $staffRoleId = Role::query()->where('role_name', 'Staff')->value('id');
        $studentRoleId = Role::query()->where('role_name', 'Student')->value('id');

        // Staff demo account
        $staff = User::updateOrCreate(
            ['email' => 'staff1@auf.test'],
            [
                'username' => 'staff1',
                'password' => $password,
                'must_change_password' => false,
                'user_status_id' => $activeStatusId,
            ]
        );

        UserProfile::updateOrCreate(
            ['account_id' => $staff->account_id],
            [
                'first_name' => 'Demo',
                'last_name' => 'Staff',
                'middle_name' => null,
                'employee_id' => 'EMP0010',
                'phone' => null,
                'address' => null,
                'date_of_birth' => null,
                'gender' => null,
            ]
        );

        if ($staffRoleId) {
            UserRole::updateOrCreate(
                ['account_id' => $staff->account_id, 'role_id' => $staffRoleId],
                [
                    'assigned_date' => $today,
                    'is_active' => 1,
                    'assigned_by' => $staff->account_id,
                ]
            );
        }

        // Second staff demo account (needed for OR-condition step approvers in demo workflows)
        $staff2 = User::updateOrCreate(
            ['email' => 'staff2@auf.test'],
            [
                'username' => 'staff2',
                'password' => $password,
                'must_change_password' => false,
                'user_status_id' => $activeStatusId,
            ]
        );

        UserProfile::updateOrCreate(
            ['account_id' => $staff2->account_id],
            [
                'first_name' => 'Demo',
                'last_name' => 'Staff Two',
                'middle_name' => null,
                'employee_id' => null,
                'phone' => null,
                'address' => null,
                'date_of_birth' => null,
                'gender' => null,
            ]
        );

        if ($staffRoleId) {
            UserRole::updateOrCreate(
                ['account_id' => $staff2->account_id, 'role_id' => $staffRoleId],
                [
                    'assigned_date' => $today,
                    'is_active' => 1,
                    'assigned_by' => $staff2->account_id,
                ]
            );
        }

        // Student demo account
        $student = User::updateOrCreate(
            ['email' => 'student1@auf.test'],
            [
                'username' => 'student1',
                'password' => $password,
                'must_change_password' => false,
                'user_status_id' => $activeStatusId,
            ]
        );

        UserProfile::updateOrCreate(
            ['account_id' => $student->account_id],
            [
                'first_name' => 'Demo',
                'last_name' => 'Student',
                'middle_name' => null,
                'employee_id' => null,
                'phone' => null,
                'address' => null,
                'date_of_birth' => null,
                'gender' => null,
            ]
        );

        if ($studentRoleId) {
            UserRole::updateOrCreate(
                ['account_id' => $student->account_id, 'role_id' => $studentRoleId],
                [
                    'assigned_date' => $today,
                    'is_active' => 1,
                    'assigned_by' => $student->account_id,
                ]
            );
        }

        $this->command?->info('✓ Demo accounts seeded (staff1@auf.test / password, staff2@auf.test / password, student1@auf.test / password)');
    }
}
