<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // \Illuminate\Auth\Events\Login::class  => [ \App\Listeners\LogSuccessfulLogin::class ],
        // \Illuminate\Auth\Events\Failed::class => [ \App\Listeners\LogFailedLogin::class ],
        // \Illuminate\Auth\Events\Logout::class => [ \App\Listeners\LogLogout::class ],
    ];

    // IMPORTANT: disable auto-discovery so Laravel doesn't add them a second time
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    public function boot(): void {}
}
