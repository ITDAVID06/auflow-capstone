<?php

namespace App\Modules\UserManagement\Policies;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    private const GLOBAL_PERMISSION_SLUGS = [
        'users.manage',
        'roles.manage',
        'submissions.override',
        'submissions.view',
    ];

    /** Global admins pass everything. */
    public function before(User $actor, string $ability): ?bool
    {
        if ($actor->hasPermission('users.manage')) {
            return true;
        }

        return null;
    }

    public function assign(User $actor, Role $role): bool
    {
        if (! $actor->hasPermission('roles.manage')) {
            return false;
        }

        $containsGlobal = $role->permissions()
            ->whereIn('slug', self::GLOBAL_PERMISSION_SLUGS)
            ->exists();

        return ! $containsGlobal;
    }

    public function assignPermissions(User $actor, array $permissionIds): bool
    {
        if (! $actor->hasPermission('roles.manage')) {
            return false;
        }

        return ! Permission::whereIn('id', $permissionIds)
            ->whereIn('slug', self::GLOBAL_PERMISSION_SLUGS)
            ->exists();
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('roles.manage');
    }

    public function update(User $actor, Role $role): bool
    {
        if (! $actor->hasPermission('roles.manage')) {
            return false;
        }

        return ! $role->permissions()
            ->whereIn('slug', self::GLOBAL_PERMISSION_SLUGS)
            ->exists();
    }

    public function delete(User $actor, Role $role): bool
    {
        if (! $actor->hasPermission('roles.manage')) {
            return false;
        }

        return ! $role->permissions()
            ->whereIn('slug', self::GLOBAL_PERMISSION_SLUGS)
            ->exists();
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('roles.manage');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.manage');
    }
}
