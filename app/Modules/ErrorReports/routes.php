<?php

use App\Modules\ErrorReports\Controllers\ErrorReportController;
use Illuminate\Support\Facades\Route;

// Public error report submission — no auth, CSRF provided by web middleware
Route::post('api/error-reports', [ErrorReportController::class, 'store'])
    ->name('api.error-reports.store')
    ->middleware('throttle:10,1');

// Admin error report review
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'permission:error-reports.manage'])
    ->group(function () {
        Route::get('error-reports', [ErrorReportController::class, 'index'])
            ->name('error-reports.index');

        Route::patch('error-reports/{id}', [ErrorReportController::class, 'update'])
            ->whereNumber('id')
            ->name('error-reports.update');
    });
