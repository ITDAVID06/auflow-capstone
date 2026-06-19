<?php

namespace App\Modules\UserManagement\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable; // ADD THIS

class User extends Authenticatable
{
    protected $table = 'tbl_user';

    protected $primaryKey = 'account_id';

    use Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'user_status_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'account_id', 'account_id');
    }

    public function getFullNameAttribute(): string
    {
        $first = $this->profile->first_name ?? '';
        $last = $this->profile->last_name ?? '';
        $name = trim($first.' '.$last);

        return $name !== '' ? $name : $this->username;
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(UserStatus::class, 'user_status_id');
    }

    // Pivot records (tbl_user_role)
    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'account_id', 'account_id');
    }

    // Direct role models (belongsToMany)
    public function directRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'tbl_user_role', 'account_id', 'role_id')
            ->withPivot(['assigned_date', 'expiry_date', 'is_active', 'assigned_by']);
    }

    // Aggregated permissions (through roles)
    public function permissions()
    {
        return $this->directRoles()
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->unique('id');
    }

    /**
     * Get all permissions for this user (cached via PermissionService).
     */
    public function allPermissions(): array
    {
        return app(\App\Modules\UserManagement\Services\PermissionService::class)
            ->getUserPermissions($this);
    }

    /**
     * Check if user has a specific permission (cached via PermissionService).
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return app(\App\Modules\UserManagement\Services\PermissionService::class)
            ->hasPermission($this, $permissionSlug);
    }

    /**
     * Check if user is an admin (has admin dashboard access).
     * This is the standardized way to identify admins across the application.
     */
    public function isAdmin(): bool
    {
        return $this->hasPermission('dashboard.admin');
    }

    /**
     * Check if user has elevated admin privileges (can override approvals or view all submissions).
     * Use this for sensitive operations like file access or workflow overrides.
     */
    public function hasAdminPrivileges(): bool
    {
        return $this->hasPermission('submissions.override')
            || $this->hasPermission('submissions.view');
    }
}
