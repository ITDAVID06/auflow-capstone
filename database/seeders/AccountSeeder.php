<?php

namespace Database\Seeders;

use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserProfile;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\UserManagement\Models\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    /**
     * Seed test accounts with Approver and Requester roles.
     *
     * Depends on: PermissionSeeder (permissions and base roles must exist).
     * Creates:  admin@auf.test (Admin), approver1-5@auf.test (Approver), requester1-5@auf.test (Requester)
     */
    public function run(): void
    {
        $password = Hash::make('password');
        $today = now()->toDateString();

        $activeStatusId = (int) UserStatus::query()
            ->where('status_name', 'Active')
            ->value('id');

        if ($activeStatusId <= 0) {
            $this->command?->error('Missing Active user status. Run PermissionSeeder first.');

            return;
        }

        // Ensure Approver role exists with the right permissions.
        DB::table('tbl_role')->insertOrIgnore([
            ['role_name' => 'Approver', 'description' => 'Can review and approve/reject submissions', 'is_active' => 1],
            ['role_name' => 'Requester', 'description' => 'Can submit forms and track requests', 'is_active' => 1],
        ]);

        $permissionIds = DB::table('tbl_permission')->pluck('id', 'slug');
        $roleIds = DB::table('tbl_role')->pluck('id', 'role_name');

        $approverPermissions = ['requests.view', 'requests.approve', 'requests.reject', 'dashboard.staff'];
        $requesterPermissions = ['forms.student-access', 'dashboard.student'];

        foreach ($approverPermissions as $slug) {
            if (isset($permissionIds[$slug], $roleIds['Approver'])) {
                DB::table('tbl_role_permission')->insertOrIgnore([
                    'role_id' => $roleIds['Approver'],
                    'permission_id' => $permissionIds[$slug],
                ]);
            }
        }

        foreach ($requesterPermissions as $slug) {
            if (isset($permissionIds[$slug], $roleIds['Requester'])) {
                DB::table('tbl_role_permission')->insertOrIgnore([
                    'role_id' => $roleIds['Requester'],
                    'permission_id' => $permissionIds[$slug],
                ]);
            }
        }

        $adminRoleId = (int) ($roleIds['Admin'] ?? 0);
        $approverRoleId = (int) ($roleIds['Approver'] ?? 0);
        $requesterRoleId = (int) ($roleIds['Requester'] ?? 0);

        // Admin account
        $admin = User::updateOrCreate(
            ['email' => 'admin@auf.test'],
            [
                'username' => 'admin_test',
                'password' => $password,
                'must_change_password' => false,
                'user_status_id' => $activeStatusId,
            ]
        );
        UserProfile::updateOrCreate(
            ['account_id' => $admin->account_id],
            ['first_name' => 'Admin', 'last_name' => 'Test']
        );
        if ($adminRoleId > 0) {
            UserRole::updateOrCreate(
                ['account_id' => $admin->account_id, 'role_id' => $adminRoleId],
                ['assigned_date' => $today, 'is_active' => 1, 'assigned_by' => $admin->account_id]
            );
        }

        // Approver accounts
        for ($i = 1; $i <= 5; $i++) {
            $approver = User::updateOrCreate(
                ['email' => "approver{$i}@auf.test"],
                [
                    'username' => "approver{$i}",
                    'password' => $password,
                    'must_change_password' => false,
                    'user_status_id' => $activeStatusId,
                ]
            );
            UserProfile::updateOrCreate(
                ['account_id' => $approver->account_id],
                ['first_name' => 'Approver', 'last_name' => (string) $i]
            );
            if ($approverRoleId > 0) {
                UserRole::updateOrCreate(
                    ['account_id' => $approver->account_id, 'role_id' => $approverRoleId],
                    ['assigned_date' => $today, 'is_active' => 1, 'assigned_by' => $approver->account_id]
                );
            }
        }

        // Requester accounts
        for ($i = 1; $i <= 5; $i++) {
            $requester = User::updateOrCreate(
                ['email' => "requester{$i}@auf.test"],
                [
                    'username' => "requester{$i}",
                    'password' => $password,
                    'must_change_password' => false,
                    'user_status_id' => $activeStatusId,
                ]
            );
            UserProfile::updateOrCreate(
                ['account_id' => $requester->account_id],
                ['first_name' => 'Requester', 'last_name' => (string) $i]
            );
            if ($requesterRoleId > 0) {
                UserRole::updateOrCreate(
                    ['account_id' => $requester->account_id, 'role_id' => $requesterRoleId],
                    ['assigned_date' => $today, 'is_active' => 1, 'assigned_by' => $requester->account_id]
                );
            }
        }
    }
}
