<?php

namespace App\Providers;

use App\Services\ProfilePictureUrlResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Core Application Service Provider
 *
 * Handles cross-cutting concerns only. Module-specific observers
 * are registered in their respective module ServiceProviders:
 *
 * @see \App\Modules\FormBuilder\FormBuilderServiceProvider
 * @see \App\Modules\WorkflowBuilder\WorkflowBuilderServiceProvider
 * @see \App\Modules\UserManagement\UserManagementServiceProvider
 * @see \App\Modules\VerificationSnapshot\VerificationSnapshotServiceProvider
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('submissions', function (Request $request) {
            $userId = $request->user()?->getKey() ?? 0;
            $throttleKey = $userId > 0
                ? 'user:'.$userId
                : 'ip:'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('approvals', function (Request $request) {
            $userId = $request->user()?->getKey() ?? 0;
            $throttleKey = $userId > 0
                ? 'user:'.$userId
                : 'ip:'.$request->ip();

            return Limit::perMinute(10)->by($throttleKey);
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::transliterate(Str::lower($request->input('email', '')).'|'.$request->ip())
            );
        });

        Inertia::share('auth', function () {
            $user = Auth::user();

            if (! $user) {
                return ['user' => null];
            }

            // Load only the fields shared to Inertia to keep payloads lean.
            $user->load([
                'profile:account_id,id,first_name,last_name,middle_name,address,profile_picture',
                'directRoles:id,role_name',
            ]);
            $profile = $user->profile;
            $avatar = app(ProfilePictureUrlResolver::class)->resolve($profile?->profile_picture);

            // Use cached PermissionService instead of raw query
            $permissionService = app(\App\Modules\UserManagement\Services\PermissionService::class);

            return [
                'user' => [
                    'id' => (int) $user->account_id,
                    'account_id' => (int) $user->account_id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'name' => trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? '')) ?: $user->username,
                    'avatar' => $avatar,
                    'profile' => $profile ? [
                        'id' => (int) $profile->id,
                        'account_id' => (int) $profile->account_id,
                        'first_name' => $profile->first_name,
                        'last_name' => $profile->last_name,
                        'middle_name' => $profile->middle_name,
                        'address' => $profile->address,
                        'profile_picture' => $profile->profile_picture,
                        'profile_picture_url' => $avatar,
                    ] : null,
                    'roles' => $user->directRoles
                        ->map(fn ($role) => [
                            'id' => (int) $role->id,
                            'role_name' => $role->role_name,
                        ])
                        ->values()
                        ->all(),
                    'permissions' => $permissionService->getSlugsForUser($user),
                    'must_change_password' => (bool) $user->must_change_password,
                ],
            ];
        });
    }
}
