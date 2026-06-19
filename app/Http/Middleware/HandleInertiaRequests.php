<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),

            'name' => config('app.name'),
            'quote' => [
                'message' => trim($message),
                'author' => trim($author),
            ],

            // Make flash messages available to Inertia pages
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'submission_success' => fn () => $request->session()->get('submission_success'),
                'submission_pending' => fn () => $request->session()->get('submission_pending'),
            ],

            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],

            'sidebarOpen' => function () use ($request) {
                $user = $request->user();

                // If user is authenticated, check their role
                if ($user) {
                    $isAdmin = $user->hasPermission('dashboard.admin');

                    // Admins: always open, never auto-collapse
                    if ($isAdmin) {
                        return true;
                    }

                    // Non-admins (Approver/Requester): always collapsed
                    return false;
                }

                // Not authenticated: default to open
                return true;
            },
        ];
    }
}
