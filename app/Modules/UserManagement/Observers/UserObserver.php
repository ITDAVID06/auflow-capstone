<?php

namespace App\Modules\UserManagement\Observers;

use App\Modules\UserManagement\Models\User;
use App\Services\AuditLogger;

class UserObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(User $m): void
    {
        $name = $this->nameOf($m);
        $this->audit->userAction('user_created', $m, 'Success', "Created user {$name}", [
            'user_id' => $m->getKey(),
            'user_name' => $name,
            'email' => $m->email,
        ]);
    }

    public function updated(User $m): void
    {
        $name = $this->nameOf($m);
        $this->audit->userAction('user_updated', $m, 'Success', "Updated user {$name}", [
            'user_id' => $m->getKey(),
            'user_name' => $name,
            'email' => $m->email,
        ]);
    }

    public function deleted(User $m): void
    {
        $name = $this->nameOf($m);
        $this->audit->userAction('user_deleted', $m, 'Warning', "Deleted user {$name}", [
            'user_id' => $m->getKey(),
            'user_name' => $name,
            'email' => $m->email,
        ]);
    }

    private function nameOf(User $m): string
    {
        return $m->full_name ?? $m->username ?? (string) $m->getKey();
    }
}
