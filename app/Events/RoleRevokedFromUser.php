<?php

namespace App\Events;

use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;

class RoleRevokedFromUser
{
    public function __construct(public User $user, public Role $role) {}
}
