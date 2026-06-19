<?php

use App\Modules\StudentDashboard\Controllers\StudentDashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'permission:dashboard.student'])
    ->prefix('student-dashboard')
    ->name('student-dashboard.')
    ->group(function () {
        Route::get('/', fn () => Inertia::render('student-dashboard/student-dashboard'))
            ->name('index');

        Route::get('/forms/active', [StudentDashboardController::class, 'activeForms'])->name('forms.active');
        Route::get('/submissions', [StudentDashboardController::class, 'submissions'])->name('submissions');
        Route::get('/submission/{formId}/{submissionId}/edit', [StudentDashboardController::class, 'editSubmission'])
            ->name('submission.edit');

        Route::put('/submission/{formId}/{submissionId}', [StudentDashboardController::class, 'updateSubmission'])
            ->name('submission.update');
        Route::post('/forms/{id}/submit', [StudentDashboardController::class, 'submit'])
            ->middleware('throttle:submissions')
            ->name('forms.submit');

        Route::get('/metrics', [StudentDashboardController::class, 'metrics'])->name('metrics');

        Route::get('/submission/{formId}/{submissionId}', [StudentDashboardController::class, 'viewSubmission'])
            ->name('submission.view');

        Route::get('/forms', [StudentDashboardController::class, 'listForms'])->name('forms.index');
        Route::get('/forms/{id}', [StudentDashboardController::class, 'viewForm'])->name('forms.show');
        Route::get(
            '/progress-attachments/{id}/download',
            [\App\Modules\StudentDashboard\Controllers\StudentDashboardController::class, 'downloadProgressAttachment']
        )->name('progress-attachments.download');
    });
