<?php

use App\Modules\AuditTrail\Controllers\AuditTrailController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:users.manage'])
    ->prefix('admin/audit-trail')
    ->group(function () {
        Route::get('/', [AuditTrailController::class, 'index'])->name('audit.index');
        Route::get('/data', [AuditTrailController::class, 'data'])->name('audit.data');
        Route::get('/export', [AuditTrailController::class, 'export'])->name('audit.export');
    });
