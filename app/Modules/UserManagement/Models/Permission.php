<?php

namespace App\Modules\UserManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $table = 'tbl_permission';

    protected $primaryKey = 'id';

    protected $fillable = ['permission_name', 'slug', 'description', 'resource', 'action'];

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'permission_id');
    }
}
