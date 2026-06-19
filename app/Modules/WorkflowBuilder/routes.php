<?php

use App\Modules\WorkflowBuilder\Controllers\WorkflowCanvasController;
use App\Modules\WorkflowBuilder\Controllers\WorkflowConfigController;
use App\Modules\WorkflowBuilder\Controllers\WorkflowManageController;
use Illuminate\Support\Facades\Route;

// Workflow Management Routes
Route::prefix('workflows')->middleware(['auth', 'permission:workflows.manage'])->group(function () {
    Route::get('/', [WorkflowManageController::class, 'index'])->name('workflows.index');
    Route::post('/', [WorkflowManageController::class, 'store'])->name('workflows.store');
    Route::get('/create', [WorkflowManageController::class, 'create'])->name('workflows.create');
    Route::get('{id}', [WorkflowManageController::class, 'show'])->name('workflows.show');
    Route::put('{id}', [WorkflowManageController::class, 'update'])->name('workflows.update');

    Route::patch('{id}/publish', [WorkflowManageController::class, 'publish'])->name('workflows.publish');
    Route::post('{id}/duplicate', [WorkflowManageController::class, 'duplicate'])->name('workflows.duplicate');

    Route::patch('{id}/archive', [WorkflowManageController::class, 'archive'])->name('workflows.archive');
    Route::patch('{id}/enable', [WorkflowManageController::class, 'enable'])->name('workflows.enable');
    Route::patch('{id}/draft', [WorkflowManageController::class, 'draft'])->name('workflows.draft');

    // Readiness
    Route::get('{id}/readiness', [WorkflowManageController::class, 'readiness'])->name('workflows.readiness');

    // Async canvas data endpoint (lazy loading)
    Route::get('{id}/canvas-data', [WorkflowManageController::class, 'getCanvasData'])->name('workflows.canvas-data');

    // Canvas-specific
    Route::get('{id}/canvas', [WorkflowCanvasController::class, 'show'])->name('workflows.canvas.show');
    Route::post('{id}/canvas', [WorkflowCanvasController::class, 'save'])->name('workflows.canvas.save');
});

// Admin Workflow Views
Route::prefix('admin')->middleware(['auth', 'permission:workflows.manage'])->group(function () {
    Route::get('/workflows', [WorkflowManageController::class, 'index'])->name('admin.workflows.index');
    Route::get('/workflows/create', [WorkflowManageController::class, 'create'])->name('admin.workflows.create');
    Route::get('/workflows/{id}/edit', [WorkflowManageController::class, 'edit'])->name('admin.workflows.edit');
});

Route::prefix('workflow-config')->middleware(['auth', 'permission:workflows.manage'])->group(function () {
    Route::get('/forms', [WorkflowConfigController::class, 'forms']);
    Route::get('/users', [WorkflowConfigController::class, 'users']);
    Route::get('/forms/{id}/fields', [WorkflowConfigController::class, 'fields']);
});
