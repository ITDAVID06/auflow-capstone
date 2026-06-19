<?php

namespace App\Modules\UserManagement\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    protected $table = 'tbl_user_role';

    protected $primaryKey = 'id';

    protected $fillable = [
        'account_id', 'role_id', 'assigned_date', 'expiry_date',
        'is_active', 'assigned_by',
    ];

    /**
     * Scope: only active, non-expired role assignments.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1)
            ->where(function (Builder $q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>', now());
            });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id', 'account_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by', 'account_id');
    }
}
