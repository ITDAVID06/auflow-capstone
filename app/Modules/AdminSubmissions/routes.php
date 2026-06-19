<?php

use App\Modules\AdminSubmissions\Controllers\AdminSubmissionsController;
use App\Modules\VerificationSnapshot\Controllers\SnapshotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('admin/submissions')
    ->name('admin-submissions.')
    ->group(function () {

        // list & view
        Route::middleware(['any-permission:submissions.view,submissions.override'])->group(function () {
            Route::get('/', [AdminSubmissionsController::class, 'index'])->name('index');
            Route::get('/pending', [AdminSubmissionsController::class, 'myPending'])->name('my-pending');
            Route::get('{formId}/{submissionId}', [AdminSubmissionsController::class, 'show'])->name('show');
            Route::get('{id}/snapshot', [SnapshotController::class, 'latestSnapshot'])->name('snapshot');
        });

        // override actions
        Route::middleware(['any-permission:submissions.override'])->group(function () {
            Route::put('{id}/approve', [AdminSubmissionsController::class, 'approve'])->name('approve');
            Route::put('{id}/reject', [AdminSubmissionsController::class, 'reject'])->name('reject');
        });
    });
