<?php

namespace App\Actions\UserManagement;

use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateUserProfileAction
{
    /**
     * Update the authenticated user's own profile fields.
     *
     * Touches only: username (tbl_user), and first_name / middle_name /
     * last_name / address / student_id / employee_id (tbl_userprofile).
     * Never modifies email, password, account_id, or any role / permission columns.
     *
     * @param  array{username: string, first_name: string, middle_name: ?string, last_name: string, address: ?string, student_id: ?string, employee_id: ?string}  $data
     */
    public function execute(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            // 1. Update username on tbl_user
            $user->username = $data['username'];
            $user->save();

            // 2. Update or create the tbl_userprofile row
            $user->profile()->updateOrCreate(
                ['account_id' => $user->account_id],
                [
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'],
                    'address' => $data['address'] ?? null,
                    'student_id' => $data['student_id'] ?? null,
                    'employee_id' => $data['employee_id'] ?? null,
                ]
            );
        });

        // 3. Bust the workflow assignable-users cache so name changes are
        //    immediately reflected wherever that list is consumed.
        Cache::forget(WorkflowService::assignableUsersCacheKey());
    }
}
