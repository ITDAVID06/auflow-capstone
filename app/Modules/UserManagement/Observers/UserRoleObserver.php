<?php

namespace App\Modules\UserManagement\Observers;

use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\UserManagement\Services\PermissionService;
use App\Services\AuditLogger;

class UserRoleObserver
{
    public function __construct(
        private AuditLogger $audit,
        private PermissionService $permissionService
    ) {}

    public function created(UserRole $m): void
    {
        [$accountName, $roleName] = $this->names($m);
        $this->audit->userAction('role_assigned', $m, 'Success', "Assigned role {$roleName} to {$accountName}", [
            'user_role_id' => $m->getKey(),
            'account_id' => $m->account_id,
            'account_name' => $accountName,
            'role_id' => $m->role_id,
            'role_name' => $roleName,
        ]);

        // Invalidate this user's permission cache
        $this->permissionService->invalidateUserCache($m->account_id);
    }

    public function updated(UserRole $m): void
    {
        [$accountName, $roleName] = $this->names($m);
        $this->audit->userAction('role_updated', $m, 'Success', "Updated role {$roleName} for {$accountName}", [
            'user_role_id' => $m->getKey(),
            'account_id' => $m->account_id,
            'account_name' => $accountName,
            'role_id' => $m->role_id,
            'role_name' => $roleName,
        ]);

        // Invalidate this user's permission cache
        $this->permissionService->invalidateUserCache($m->account_id);
    }

    public function deleted(UserRole $m): void
    {
        [$accountName, $roleName] = $this->names($m);
        $this->audit->userAction('role_revoked', $m, 'Warning', "Revoked role {$roleName} from {$accountName}", [
            'user_role_id' => $m->getKey(),
            'account_id' => $m->account_id,
            'account_name' => $accountName,
            'role_id' => $m->role_id,
            'role_name' => $roleName,
        ]);

        // Invalidate this user's permission cache
        $this->permissionService->invalidateUserCache($m->account_id);
    }

    private function names(UserRole $m): array
    {
        $user = User::find($m->account_id);
        $role = Role::find($m->role_id);

        return [
            $user?->full_name ?? $user?->username ?? "Account #{$m->account_id}",
            $role?->role_name ?? $role?->name ?? "Role #{$m->role_id}",
        ];
    }
}
