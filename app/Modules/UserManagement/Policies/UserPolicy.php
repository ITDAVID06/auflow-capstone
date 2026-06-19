<?php

namespace App\Modules\UserManagement\Policies;

use App\Modules\UserManagement\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function before(User $actor, string $ability): ?bool
    {
        if ($actor->hasPermission('users.manage')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('manage_org.users');
    }

    public function view(User $actor, User $target): bool
    {
        if (! $actor->hasPermission('manage_org.users')) {
            return false;
        }

        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('manage_org.users');
    }

    public function update(User $actor, User $target): bool
    {
        if (! $actor->hasPermission('manage_org.users')) {
            return false;
        }

        return true;
    }

    public function delete(User $actor, User $target): bool
    {
        if (! $actor->hasPermission('manage_org.users')) {
            return false;
        }

        return true;
    }
}
