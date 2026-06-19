<?php

use App\Modules\UserManagement\Controllers\RoleController;
use App\Modules\UserManagement\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('user-management')
    ->name('user-management.')
    ->group(function () {

        // ── Users ─────────────────────────────────────────────────────────────
        Route::middleware(['auth', 'permission:users.manage'])
            ->group(function () {
                Route::resource('users', UserController::class)->except(['show']);
                Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            });

        // ── Roles ─────────────────────────────────────────────────────────────
        Route::middleware(['auth', 'any-permission:users.manage,roles.manage'])
            ->group(function () {
                Route::post('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
                    ->name('roles.sync-permissions');
                Route::resource('roles', RoleController::class);
            });
    });
