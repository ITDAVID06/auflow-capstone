<?php

namespace App\Modules\UserManagement\Observers;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\RolePermission;
use App\Modules\UserManagement\Services\PermissionService;
use App\Services\AuditLogger;

class RolePermissionObserver
{
    public function __construct(
        private AuditLogger $audit,
        private PermissionService $permissionService
    ) {}

    public function created(RolePermission $m): void
    {
        [$roleName, $permName] = $this->resolveNames($m);
        $this->audit->userAction('role_permission_granted', $m, 'Success', "Granted permission {$permName} to role {$roleName}", [
            'role_id' => $m->role_id,
            'role_name' => $roleName,
            'permission_id' => $m->permission_id,
            'permission_name' => $permName,
        ]);

        // Invalidate all permission caches since role permissions changed
        $this->permissionService->invalidateAllCaches();
    }

    public function updated(RolePermission $m): void
    {
        [$roleName, $permName] = $this->resolveNames($m);
        $this->audit->userAction('role_permission_updated', $m, 'Success', "Updated permission {$permName} for role {$roleName}", [
            'role_id' => $m->role_id,
            'role_name' => $roleName,
            'permission_id' => $m->permission_id,
            'permission_name' => $permName,
        ]);

        // Invalidate all permission caches since role permissions changed
        $this->permissionService->invalidateAllCaches();
    }

    public function deleted(RolePermission $m): void
    {
        [$roleName, $permName] = $this->resolveNames($m);
        $this->audit->userAction('role_permission_revoked', $m, 'Warning', "Revoked permission {$permName} from role {$roleName}", [
            'role_id' => $m->role_id,
            'role_name' => $roleName,
            'permission_id' => $m->permission_id,
            'permission_name' => $permName,
        ]);

        // Invalidate all permission caches since role permissions changed
        $this->permissionService->invalidateAllCaches();
    }

    private function resolveNames(RolePermission $m): array
    {
        $role = Role::find($m->role_id);
        $perm = Permission::find($m->permission_id);

        $roleName = $role?->role_name ?? $role?->name ?? "Role #{$m->role_id}";
        $permName = $perm?->permission_name ?? $perm?->name ?? "Permission #{$m->permission_id}";

        return [$roleName, $permName];
    }
}
