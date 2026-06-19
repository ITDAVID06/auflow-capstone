<?php

namespace App\Listeners;

use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Login $event): void
    {
        // AuditLogger will pull the authenticated user for actor_* fields.
        $this->audit->security(
            action: 'login_success',
            status: 'Success',
            description: 'Successful login',
            meta: ['ip' => request()->ip()]
        );
    }
}
