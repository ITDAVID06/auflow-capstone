<?php

use App\Modules\Reports\Controllers\ReportsController;
use App\Modules\Reports\Controllers\SavedReportController;
use App\Modules\Reports\Controllers\ScheduledExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'any-permission:submissions.view,submissions.override'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportsController::class, 'index'])->name('index');
    Route::get('/forms', [ReportsController::class, 'forms'])->name('forms');
    Route::get('/form-submissions', [ReportsController::class, 'getFormSubmissions'])->name('form-submissions');
    Route::get('/export-csv', [ReportsController::class, 'exportCSV'])->name('export-csv');
    Route::get('/export-pdf', [ReportsController::class, 'exportPDF'])->name('export-pdf');
    Route::get('/export-pdf-download', [ReportsController::class, 'exportPdfDownload'])->name('export-pdf-download');
    Route::get('/chart-data', [ReportsController::class, 'chartData'])->name('chart-data');
    Route::get('/compare', [ReportsController::class, 'compare'])->name('compare');
    Route::get('/aggregate', [ReportsController::class, 'aggregate'])->name('aggregate');
    Route::get('/exports/{exportId}', [ReportsController::class, 'exportStatus'])->name('exports.status');
    Route::get('/exports/{exportId}/download', [ReportsController::class, 'downloadExport'])->name('exports.download');
    Route::get('/attachments/{id}/download', [ReportsController::class, 'downloadAttachment'])->name('attachments.download');
    Route::get('/attachments/{id}/preview', [ReportsController::class, 'previewAttachment'])->name('attachments.preview');

    // Saved report views
    Route::get('/views', [SavedReportController::class, 'index'])->name('views.index');
    Route::post('/views', [SavedReportController::class, 'store'])->name('views.store');
    Route::put('/views/{id}', [SavedReportController::class, 'update'])->name('views.update');
    Route::delete('/views/{id}', [SavedReportController::class, 'destroy'])->name('views.destroy');

    // Scheduled exports
    Route::get('/scheduled-exports', [ScheduledExportController::class, 'index'])->name('scheduled-exports.index');
    Route::post('/scheduled-exports', [ScheduledExportController::class, 'store'])->name('scheduled-exports.store');
    Route::put('/scheduled-exports/{id}', [ScheduledExportController::class, 'update'])->name('scheduled-exports.update');
    Route::delete('/scheduled-exports/{id}', [ScheduledExportController::class, 'destroy'])->name('scheduled-exports.destroy');
});
