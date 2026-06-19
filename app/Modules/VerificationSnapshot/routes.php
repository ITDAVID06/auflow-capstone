<?php

use App\Modules\VerificationSnapshot\Controllers\SnapshotController;
use Illuminate\Support\Facades\Route;

/**
 * Public-by-link viewer (unguessable id).
 * If you want staff-only, wrap with ->middleware(['auth'])
 */
Route::prefix('snapshots')
    ->name('snapshots.')
    ->group(function () {
        Route::get('{public_id}', [SnapshotController::class, 'show'])->name('show');
        Route::get('{public_id}/pdf', [SnapshotController::class, 'pdf'])->name('pdf');
        Route::get('/progress/{id}/snapshot', [SnapshotController::class, 'latestSnapshot'])
            ->middleware(['auth', 'any-permission:submissions.view,submissions.override'])
            ->name('progress.snapshot');

        // NEW: Verification endpoints
        Route::get('{public_id}/verify', [SnapshotController::class, 'verifyHash'])
            ->name('verify');
        Route::get('submission/{submission_id}/verify-all', [SnapshotController::class, 'verifySubmission'])
            ->middleware(['auth', 'any-permission:submissions.view,submissions.override'])
            ->name('verify.submission');
    });
