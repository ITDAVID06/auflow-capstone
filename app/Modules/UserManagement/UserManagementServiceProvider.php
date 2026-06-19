<?php

namespace App\Modules\UserManagement;

use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\RolePermission;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserProfile;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\UserManagement\Models\UserStatus;
use App\Modules\UserManagement\Observers\PermissionObserver;
use App\Modules\UserManagement\Observers\RoleObserver;
use App\Modules\UserManagement\Observers\RolePermissionObserver;
use App\Modules\UserManagement\Observers\UserObserver;
use App\Modules\UserManagement\Observers\UserProfileObserver;
use App\Modules\UserManagement\Observers\UserRoleObserver;
use App\Modules\UserManagement\Observers\UserStatusObserver;
use Illuminate\Support\ServiceProvider;

/**
 * UserManagement Module Service Provider
 *
 * Manages users, roles, permissions, departments, profiles,
 * and the complete RBAC system with cached permission checks.
 *
 * @dependencies
 *  - AuditTrail: AuditLogger service for lifecycle audit logging
 *  - PermissionService: Internal service with 60-min cache TTL;
 *    auto-invalidated by UserRoleObserver and RolePermissionObserver
 *
 * @dependedOnBy
 *  - FormBuilder: Form access controlled by permissions
 *  - WorkflowBuilder: Approver assignments reference user accounts
 *  - AdminSubmissions: Admin override permissions
 *  - StaffDashboard: Staff role-based dashboard access
 *  - StudentDashboard: Student role-based dashboard access
 *  - AppServiceProvider: Inertia shared auth state via PermissionService
 */
class UserManagementServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        User::observe(UserObserver::class);
        UserProfile::observe(UserProfileObserver::class);
        UserRole::observe(UserRoleObserver::class);
        UserStatus::observe(UserStatusObserver::class);
        Role::observe(RoleObserver::class);
        RolePermission::observe(RolePermissionObserver::class);
        Permission::observe(PermissionObserver::class);
    }
}
