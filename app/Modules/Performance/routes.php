<?php

use App\Modules\Performance\Controllers\PerformanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'any-permission:performance.view'])->prefix('performance')->name('performance.')->group(function () {
    Route::get('/', [PerformanceController::class, 'index'])->name('index');
    Route::get('/data', [PerformanceController::class, 'data'])->name('data');
});
