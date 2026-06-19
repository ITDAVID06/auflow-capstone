<?php

use App\Http\Middleware\EnsureHasAnyPermission;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Aliases
        $middleware->alias([
            'any-permission' => EnsureHasAnyPermission::class,
            'permission' => EnsureHasAnyPermission::class,
        ]);

        // Web stack
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ForcePasswordChange::class,
        ]);
    })
    ->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('workflow:send-approval-reminders')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/approval_reminders.log'));

        $schedule->command('reports:send-scheduled-exports')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduled_exports.log'));

        $schedule->command('reports:cleanup-exports')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cleanup_exports.log'));

        $schedule->command('partitions:manage')
            ->monthly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/partition_management.log'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return back()->with('error', "You're submitting too quickly. Please wait a moment before trying again.");
            }

            return response()->json([
                'message' => "You're submitting too quickly. Please wait a moment before trying again.",
            ], 429);
        });

        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, Throwable $exception, Request $request) {
            if (! app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [500, 503, 404, 403])) {
                if ($request->header('X-Inertia')) {
                    return inertia('Errors/Error', [
                        'status' => $response->getStatusCode(),
                        'message' => app()->isLocal() ? ($exception->getMessage() ?: null) : null,
                    ])->toResponse($request)
                        ->setStatusCode($response->getStatusCode());
                }
            } elseif ($response->getStatusCode() === 419) {
                return back()->with([
                    'error' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });
    })->create();
