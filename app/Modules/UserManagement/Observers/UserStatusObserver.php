<?php

namespace App\Modules\UserManagement\Observers;

use App\Modules\UserManagement\Models\UserStatus;
use App\Services\AuditLogger;

class UserStatusObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(UserStatus $m): void
    {
        $name = $this->nameOf($m);
        $this->audit->userAction('user_status_created', $m, 'Success', "Created status {$name}", [
            'status_id' => $m->getKey(),
            'status_name' => $name,
        ]);
    }

    public function updated(UserStatus $m): void
    {
        $name = $this->nameOf($m);
        $this->audit->userAction('user_status_updated', $m, 'Success', "Updated status {$name}", [
            'status_id' => $m->getKey(),
            'status_name' => $name,
        ]);
    }

    public function deleted(UserStatus $m): void
    {
        $name = $this->nameOf($m);
        $this->audit->userAction('user_status_deleted', $m, 'Warning', "Deleted status {$name}", [
            'status_id' => $m->getKey(),
            'status_name' => $name,
        ]);
    }

    private function nameOf(UserStatus $m): string
    {
        return $m->status_name ?? $m->name ?? (string) $m->getKey();
    }
}
