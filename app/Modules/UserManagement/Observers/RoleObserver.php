<?php

namespace App\Modules\UserManagement\Observers;

use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Services\PermissionService;
use App\Services\AuditLogger;

class RoleObserver
{
    public function __construct(
        private AuditLogger $audit,
        private PermissionService $permissionService,
    ) {}

    public function created(Role $m): void
    {
        $name = $this->nameOf($m);
        $this->audit->userAction('role_created', $m, 'Success', "Created Role {$name}", [
            'role_id' => $m->getKey(),
            'role_name' => $name,
        ]);
    }

    public function updated(Role $m): void
    {
        $this->permissionService->invalidateAllCaches();

        $name = $this->nameOf($m);
        $this->audit->userAction('role_updated', $m, 'Success', "Updated Role {$name}", [
            'role_id' => $m->getKey(),
            'role_name' => $name,
        ]);
    }

    public function deleted(Role $m): void
    {
        $this->permissionService->invalidateAllCaches();

        $name = $this->nameOf($m);
        $this->audit->userAction('role_deleted', $m, 'Warning', "Deleted Role {$name}", [
            'role_id' => $m->getKey(),
            'role_name' => $name,
        ]);
    }

    private function nameOf(Role $m): string
    {
        return $m->role_name ?? $m->name ?? (string) $m->getKey();
    }
}
