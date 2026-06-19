<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Logout $event): void
    {
        $this->audit->security(
            action: 'logout',
            status: 'Success',
            description: 'User logged out',
            meta: ['ip' => request()->ip()]
        );
    }
}
