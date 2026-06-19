<?php

use App\Modules\FormBuilder\Controllers\Admin\AdminCalendarController;
use App\Modules\FormBuilder\Controllers\Admin\FacilityController;
use App\Modules\FormBuilder\Controllers\Admin\FormManageController;
use App\Modules\FormBuilder\Controllers\Admin\FormSaveController;
use App\Modules\FormBuilder\Controllers\Admin\ImageUploadController;
use App\Modules\FormBuilder\Controllers\RequestFormController;
use Illuminate\Support\Facades\Route;

// Admin routes
Route::prefix('admin')->middleware(['auth', 'permission:forms.manage'])->group(function () {
    Route::get('/forms', [FormManageController::class, 'index'])->name('admin.forms.index');
    Route::get('/forms/create', [FormManageController::class, 'create'])->name('admin.forms.create');
    Route::get('/forms/{form}/edit', [FormManageController::class, 'edit'])->can('update', 'form')->name('admin.forms.edit');
    Route::get('/forms/{id}/view', [FormManageController::class, 'viewLocked'])->name('admin.forms.view');
    Route::patch('/forms/{form}/archive', [FormManageController::class, 'archive'])->can('archive', 'form')->name('admin.forms.archive');
    Route::patch('/forms/{form}/restore', [FormManageController::class, 'restore'])->can('restore', 'form')->withTrashed()->name('admin.forms.restore');
    Route::post('/forms/{form}/revise', [FormManageController::class, 'revise'])->can('revise', 'form')->withTrashed()->name('admin.forms.revise');
    Route::post('/forms/{form}/duplicate', [FormManageController::class, 'duplicate'])->can('duplicate', 'form')->withTrashed()->name('admin.forms.duplicate');

    Route::get('/forms/categories', [FormManageController::class, 'listCategories'])->name('admin.forms.categories');
    Route::post('/forms/categories', [FormManageController::class, 'storeCategory']) // fixed
        ->name('admin.forms.categories.store');

    Route::get('/forms/permissions', [FormManageController::class, 'listFormPermissions'])
        ->name('admin.forms.permissions.list');
    Route::patch('/forms/{id}/visibility', [FormManageController::class, 'updateVisibility'])
        ->name('admin.forms.visibility.update');

    // Image upload for form builder
    Route::post('/forms/upload-image', [ImageUploadController::class, 'upload'])
        ->name('admin.forms.upload-image');
    Route::delete('/forms/delete-image', [ImageUploadController::class, 'delete'])
        ->name('admin.forms.delete-image');
});

Route::middleware(['auth', 'permission:facilities.manage'])
    ->prefix('admin/facilities/calendar')
    ->name('admin.facilities.calendar.')
    ->group(function () {
        Route::get('/', [AdminCalendarController::class, 'index'])->name('index');
        Route::get('/events', [AdminCalendarController::class, 'events'])->name('events');
    });

Route::middleware(['auth', 'permission:facilities.manage'])
    ->prefix('admin/facilities')
    ->name('admin.facilities.')
    ->group(function () {
        Route::get('/', [FacilityController::class, 'index'])->name('index');
        Route::get('/upcoming-events', [AdminCalendarController::class, 'upcomingEvents'])->name('upcoming-events');
        Route::post('/', [FacilityController::class, 'store'])->name('store');
        Route::put('/{id}', [FacilityController::class, 'update'])->name('update');
        Route::put('/{id}/toggle', [FacilityController::class, 'toggleStatus'])->name('toggle');
        Route::delete('/{id}', [FacilityController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth'])
    ->prefix('admin/facilities')
    ->name('admin.facilities.')
    ->group(function () {
        Route::get('/active', [FacilityController::class, 'activeList'])->name('active');
        Route::get('/slots/availability', [FacilityController::class, 'availability']);
    });

// Save/Update
Route::prefix('forms')->middleware(['auth', 'permission:forms.manage'])->group(function () {
    Route::post('/', [FormSaveController::class, 'store'])->name('forms.store');
    Route::put('{form}', [FormSaveController::class, 'update'])->can('update', 'form')->name('forms.update');
    Route::put('{form}/draft', [FormSaveController::class, 'saveDraft'])->can('view', 'form')->name('forms.draft');
    Route::patch('{id}/status', [FormSaveController::class, 'updateStatus'])->name('forms.updateStatus');
    Route::get('{form}', [FormManageController::class, 'edit'])->can('update', 'form')->name('forms.show');
});

// Request forms (user-facing)
Route::middleware(['auth'])->group(function () {
    Route::get('/user/forms', [RequestFormController::class, 'index'])->name('user.forms');
    Route::get('/user/forms/{id}', [RequestFormController::class, 'show'])->name('user.form.view');
    Route::post('/user/forms/{id}/submit', [RequestFormController::class, 'submit'])
        ->middleware('throttle:submissions')
        ->name('user.form.submit');
});
