<?php

namespace App\Modules\UserManagement\Services;

use App\Modules\UserManagement\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * Cache duration for user permissions (in seconds)
     * Default: 15 minutes
     */
    private const CACHE_TTL = 900;

    /**
     * Cache key prefix for user permissions
     */
    private const CACHE_PREFIX = 'auflow:user_permissions';

    /**
     * Cache tag for permission-related caches (used only if tags are supported)
     */
    private const CACHE_TAG = 'permissions';

    /**
     * Cache key for tracking all user IDs with cached permissions
     */
    private const CACHE_USERS_KEY = 'auflow:user_permissions:cached_users';

    /**
     * Get all permissions for a user with caching.
     *
     * Returns permission slugs only.
     *
     * @return array Array of permission slugs
     */
    public function getUserPermissions(User $user): array
    {
        $cacheKey = $this->getCacheKey($user->account_id);

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($user) {
                // Track this user ID for bulk invalidation
                $this->trackCachedUser($user->account_id);

                // Only load active, non-expired role assignments
                $permissions = $user->directRoles()
                    ->wherePivot('is_active', 1)
                    ->where(function ($q) {
                        $q->whereNull('tbl_user_role.expiry_date')
                            ->orWhere('tbl_user_role.expiry_date', '>', now());
                    })
                    ->with('permissions')
                    ->get()
                    ->flatMap(fn ($role) => $role->permissions)
                    ->unique('id');

                return $permissions->pluck('slug')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        );
    }

    /**
     * Alias used by AppServiceProvider for Inertia shared data.
     *
     * @return array Array of permission slugs
     */
    public function getSlugsForUser(User $user): array
    {
        return $this->getUserPermissions($user);
    }

    /**
     * Check if a user has a specific permission (cached).
     */
    public function hasPermission(User $user, string $permissionSlug): bool
    {
        $permissions = $this->getUserPermissions($user);

        return in_array($permissionSlug, $permissions, true);
    }

    /**
     * Check if a user has any of the specified permissions (cached).
     */
    public function hasAnyPermission(User $user, array $permissionSlugs): bool
    {
        $permissions = $this->getUserPermissions($user);

        return ! empty(array_intersect($permissionSlugs, $permissions));
    }

    /**
     * Check if a user has all of the specified permissions (cached).
     */
    public function hasAllPermissions(User $user, array $permissionSlugs): bool
    {
        $permissions = $this->getUserPermissions($user);

        return empty(array_diff($permissionSlugs, $permissions));
    }

    /**
     * Invalidate cached permissions for a specific user.
     * Call this when a user's roles or permissions are modified.
     */
    public function invalidateUserCache(int|User $user): void
    {
        $accountId = $user instanceof User ? $user->account_id : $user;
        $cacheKey = $this->getCacheKey($accountId);

        Cache::forget($cacheKey);
        $this->untrackCachedUser($accountId);
    }

    /**
     * Invalidate all permission caches.
     * Call this when roles/permissions are modified globally.
     */
    public function invalidateAllCaches(): void
    {
        // Get all tracked user IDs
        $cachedUsers = Cache::get(self::CACHE_USERS_KEY, []);

        // Invalidate each user's cache
        foreach ($cachedUsers as $accountId) {
            $cacheKey = $this->getCacheKey($accountId);
            Cache::forget($cacheKey);
        }

        // Clear the tracking list
        Cache::forget(self::CACHE_USERS_KEY);
    }

    /**
     * Track a user ID that has cached permissions.
     */
    private function trackCachedUser(int $accountId): void
    {
        $cachedUsers = Cache::get(self::CACHE_USERS_KEY, []);

        if (! in_array($accountId, $cachedUsers, true)) {
            $cachedUsers[] = $accountId;
            Cache::put(self::CACHE_USERS_KEY, $cachedUsers, self::CACHE_TTL);
        }
    }

    /**
     * Remove a user ID from the tracking list.
     */
    private function untrackCachedUser(int $accountId): void
    {
        $cachedUsers = Cache::get(self::CACHE_USERS_KEY, []);
        $cachedUsers = array_values(array_filter($cachedUsers, fn ($id) => $id !== $accountId));

        if (! empty($cachedUsers)) {
            Cache::put(self::CACHE_USERS_KEY, $cachedUsers, self::CACHE_TTL);
        } else {
            Cache::forget(self::CACHE_USERS_KEY);
        }
    }

    /**
     * Get the cache key for a user's permissions.
     */
    private function getCacheKey(int $accountId): string
    {
        return self::CACHE_PREFIX.":{$accountId}";
    }

    /**
     * Warm up the cache for a user (useful after login).
     */
    public function warmCache(User $user): array
    {
        // This will populate the cache if it doesn't exist
        return $this->getUserPermissions($user);
    }
}
