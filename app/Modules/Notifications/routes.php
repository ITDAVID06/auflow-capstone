<?php

use App\Modules\Notifications\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// API routes for notifications (AJAX calls)
Route::middleware('auth')->prefix('api/notifications')->name('api.notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('mark-read');
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
});

// Page route for viewing all notifications
Route::middleware('auth')->get('/notifications', [NotificationController::class, 'page'])->name('notifications.page');
