<?php

use App\Modules\StaffDashboard\Controllers\ProgressAttachmentController;
use App\Modules\StaffDashboard\Controllers\StaffDashboardController;
use App\Modules\VerificationSnapshot\Controllers\SnapshotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:dashboard.staff'])
    ->prefix('staff-dashboard')
    ->name('staff-dashboard.')
    ->group(function () {
        Route::get('/', [StaffDashboardController::class, 'index'])->name('index');

        // My submissions (requester flow for staff users)
        Route::get('/my-submissions', [StaffDashboardController::class, 'mySubmissionsIndex'])
            ->name('my-submissions.index');
        Route::get('/submissions', [StaffDashboardController::class, 'mySubmissions'])
            ->name('submissions');
        Route::get('/metrics', [StaffDashboardController::class, 'myMetrics'])
            ->name('metrics');
        Route::get('/approval-metrics', [StaffDashboardController::class, 'approvalMetrics'])
            ->name('approval-metrics');
        Route::get('/my-submissions/{formId}/{submissionId}', [StaffDashboardController::class, 'viewOwnSubmission'])
            ->name('my-submissions.view');
        Route::get('/my-submissions/{formId}/{submissionId}/edit', [StaffDashboardController::class, 'editOwnSubmission'])
            ->name('my-submissions.edit');
        Route::put('/my-submissions/{formId}/{submissionId}', [StaffDashboardController::class, 'updateOwnSubmission'])
            ->name('my-submissions.update');
        Route::get('/my-progress-attachments/{id}/download', [StaffDashboardController::class, 'downloadOwnProgressAttachment'])
            ->name('my-progress-attachments.download');

        Route::middleware(['any-permission:requests.approve,submissions.view,submissions.override'])
            ->group(function () {
                // Approve / Reject (supports multipart via POST + _method=PUT from the client)
                Route::put('/progress/{id}/approve', [StaffDashboardController::class, 'approve'])
                    ->middleware('throttle:approvals')
                    ->name('progress.approve');
                Route::put('/progress/{id}/reject', [StaffDashboardController::class, 'reject'])
                    ->middleware('throttle:approvals')
                    ->name('progress.reject');

                // Review submission (by progress id)
                Route::get('/submission/{id}', [StaffDashboardController::class, 'viewSubmission'])->name('submission.view');

                // All requests page
                Route::get('/requests', [StaffDashboardController::class, 'viewAll'])->name('requests');

                // Snapshot for Review page refresh/autoload
                Route::get('/progress/{id}/snapshot', [SnapshotController::class, 'latestSnapshot'])
                    ->name('progress.snapshot');

                // Progress comment attachments - download and preview
                Route::get('/progress-attachments/{id}/download',
                    [ProgressAttachmentController::class, 'download']
                )->name('progress-attachments.download');

                Route::get('/progress-attachments/{id}/preview',
                    [ProgressAttachmentController::class, 'preview']
                )->name('progress-attachments.preview');

                // Existing submission (runtime) attachment download
                Route::get('/submission-attachments/{id}/download',
                    [StaffDashboardController::class, 'download']
                )->name('attachments.download');
            });

        // Forms (list + show + submit + active)
        Route::get('/forms', [StaffDashboardController::class, 'listForms'])->name('forms.index');
        Route::get('/forms/active', [StaffDashboardController::class, 'activeForms'])->name('forms.active');
        Route::get('/forms/{id}', [StaffDashboardController::class, 'viewForm'])->name('forms.show');
        Route::post('/forms/{id}/submit', [StaffDashboardController::class, 'submit'])
            ->middleware('throttle:submissions')
            ->name('forms.submit');
    });
