<?php

namespace App\Events;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;

class PermissionGrantedToRole
{
    public function __construct(public Role $role, public Permission $permission) {}
}
