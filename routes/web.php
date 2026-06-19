<?php

use App\Http\Controllers\ProfilePictureController;
use App\Modules\Dashboard\Controllers\AdminDashboardController;
use App\Modules\Dashboard\Controllers\ReportWidgetsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

Route::get('/', function () {
    // If already logged in, skip the marketing page
    if (auth()->check()) {
        $user = auth()->user();

        if ($user->hasPermission('dashboard.admin')) {
            return redirect()->route('dashboard');
        }

        if ($user->hasPermission('dashboard.staff')) {
            return redirect()->route('staff-dashboard.index');
        }

        if ($user->hasPermission('dashboard.student')) {
            return redirect()->route('student-dashboard.index');
        }

        return redirect()->route('login');
    }

    // Guest users see the marketing one-pager
    return Inertia::render('welcome');
})->name('home');

// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::get('dashboard', function () {
//         return Inertia::render('dashboard');
//     })->name('dashboard');
// });

Route::middleware(['auth', 'permission:dashboard.admin'])
    ->get('/dashboard', [AdminDashboardController::class, 'index'])
    ->name('dashboard');

Route::middleware(['auth', 'permission:dashboard.admin'])
    ->prefix('dashboard/report-widgets')
    ->name('dashboard.widgets.')
    ->group(function () {
        Route::get('submission-trend', [ReportWidgetsController::class, 'submissionTrend'])
            ->name('submission-trend');
        Route::get('status-breakdown', [ReportWidgetsController::class, 'statusBreakdown'])
            ->name('status-breakdown');
    });

Route::get('/files/{path}', function (string $path) {
    $decodedPath = urldecode($path);
    $normalizedPath = ltrim(str_replace('\\', '/', $decodedPath), '/');

    // Block traversal attempts and malformed paths.
    if ($normalizedPath === '' || str_contains($normalizedPath, '../')) {
        abort(404);
    }

    $disk = 'private';
    abort_unless(Storage::disk($disk)->exists($normalizedPath), 404);

    return response()->file(Storage::disk($disk)->path($normalizedPath));
})->where('path', '.*')->middleware(['auth', 'any-permission:submissions.view,submissions.override,dashboard.admin,dashboard.staff,dashboard.student,requests.approve']);

Route::middleware(['auth'])->group(function () {
    Route::get('/student-dashboard', fn () => Inertia::render('student-dashboard/student-dashboard'))
        ->name('student-dashboard.index');
});

// Route::middleware(['auth', 'verified'])
//     ->prefix('admin')
//     ->group(function () {
//         Route::get('/dashboard', [\App\Modules\Dashboard\Controllers\AdminDashboardController::class, 'index'])
//             ->name('admin.dashboard');
//     });

// Now you can include your modular routes safely
require base_path('app/Modules/UserManagement/routes.php');
require base_path('app/Modules/FormBuilder/routes.php');
require base_path('app/Modules/AuditTrail/routes.php');
require base_path('app/Modules/WorkflowBuilder/routes.php');
require base_path('app/Modules/StaffDashboard/routes.php');
require base_path('app/Modules/StudentDashboard/routes.php');
require base_path('app/Modules/VerificationSnapshot/routes.php');
require base_path('app/Modules/AdminSubmissions/routes.php');
require base_path('app/Modules/Reports/routes.php');
require base_path('app/Modules/Notifications/routes.php');
require base_path('app/Modules/Performance/routes.php');
require base_path('app/Modules/ErrorReports/routes.php');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::middleware('auth')
    ->get('/profile-pictures/{path}', [ProfilePictureController::class, 'show'])
    ->where('path', '.*')
    ->name('profile-pictures.show');
