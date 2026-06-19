<?php

namespace App\Modules\ErrorReports;

use Illuminate\Support\ServiceProvider;

class ErrorReportsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Routes are loaded via require in routes/web.php to ensure the web middleware group is applied.
    }
}
