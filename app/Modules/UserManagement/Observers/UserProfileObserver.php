<?php

namespace App\Modules\UserManagement\Observers;

use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserProfile;
use App\Services\AuditLogger;

class UserProfileObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(UserProfile $m): void
    {
        $accountName = $this->accountName($m);
        $this->audit->userAction('user_profile_created', $m, 'Success', "Created profile for {$accountName}", [
            'user_profile_id' => $m->getKey(),
            'account_id' => $m->account_id,
            'account_name' => $accountName,
        ]);
    }

    public function updated(UserProfile $m): void
    {
        $accountName = $this->accountName($m);
        $this->audit->userAction('user_profile_updated', $m, 'Success', "Updated profile for {$accountName}", [
            'user_profile_id' => $m->getKey(),
            'account_id' => $m->account_id,
            'account_name' => $accountName,
        ]);
    }

    public function deleted(UserProfile $m): void
    {
        $accountName = $this->accountName($m);
        $this->audit->userAction('user_profile_deleted', $m, 'Warning', "Deleted profile for {$accountName}", [
            'user_profile_id' => $m->getKey(),
            'account_id' => $m->account_id,
            'account_name' => $accountName,
        ]);
    }

    private function accountName(UserProfile $m): string
    {
        $user = User::find($m->account_id);

        return $user?->full_name ?? $user?->username ?? "Account #{$m->account_id}";
    }
}
