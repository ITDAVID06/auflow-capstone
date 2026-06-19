<?php

namespace App\Modules\UserManagement\Observers;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Services\PermissionService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;

class PermissionObserver
{
    public function __construct(
        private AuditLogger $audit,
        private PermissionService $permissionService,
    ) {}

    public function created(Permission $m): void
    {
        $this->permissionService->invalidateAllCaches();
        Cache::forget('auflow:form:permissions:list');

        $name = $this->nameOf($m);
        $this->audit->userAction('permission_created', $m, 'Success', "Created Permission {$name}", [
            'permission_id' => $m->getKey(),
            'permission_name' => $name,
        ]);
    }

    public function updated(Permission $m): void
    {
        $this->permissionService->invalidateAllCaches();
        Cache::forget('auflow:form:permissions:list');

        $name = $this->nameOf($m);
        $this->audit->userAction('permission_updated', $m, 'Success', "Updated Permission {$name}", [
            'permission_id' => $m->getKey(),
            'permission_name' => $name,
        ]);
    }

    public function deleted(Permission $m): void
    {
        $this->permissionService->invalidateAllCaches();
        Cache::forget('auflow:form:permissions:list');

        $name = $this->nameOf($m);
        $this->audit->userAction('permission_deleted', $m, 'Warning', "Deleted Permission {$name}", [
            'permission_id' => $m->getKey(),
            'permission_name' => $name,
        ]);
    }

    private function nameOf(Permission $m): string
    {
        return $m->permission_name ?? $m->name ?? (string) $m->getKey();
    }
}
