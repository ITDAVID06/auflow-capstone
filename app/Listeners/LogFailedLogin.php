<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Failed $event): void
    {
        $this->audit->security(
            action: 'login_failed',
            status: 'Warning',
            description: 'Failed login attempt',
            meta: [
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
            ]
        );
    }
}
