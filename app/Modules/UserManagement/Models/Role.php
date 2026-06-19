<?php

namespace App\Modules\UserManagement\Models;

use App\Modules\UserManagement\Services\PermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'tbl_role';

    protected $primaryKey = 'id';

    protected $fillable = ['role_name', 'description', 'is_active'];

    /**
     * Sync permissions and invalidate caches.
     * Use this instead of $role->permissions()->sync() directly.
     */
    public function syncPermissions(array $permissionIds): array
    {
        $changes = $this->permissions()->sync($permissionIds);

        // Invalidate all permission caches since role permissions changed
        app(PermissionService::class)->invalidateAllCaches();

        return $changes;
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'role_id');
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'tbl_role_permission',
            'role_id',
            'permission_id'
        );
    }
}
