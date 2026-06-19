<?php

namespace App\Modules\WorkflowBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    protected $table = 'tbl_workflow_step';

    protected $fillable = [
        'workflow_id',
        'step_name',
        'step_description',
        'step_order',
        'step_group',
        'action_type',
        'assigned_account_id',
        'max_duration_hours',
        'step_conditions',
        'if_rejected_id',
    ];

    protected $casts = [
        'step_conditions' => 'array',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\UserManagement\Models\User::class, 'assigned_account_id', 'account_id');
    }

    /**
     * Get all approvers for this step (multiple approvers support)
     * Eager loads user relationship for notifications
     */
    public function approvers(): HasMany
    {
        return $this->hasMany(WorkflowStepApprover::class, 'step_id')
            ->orderBy('order')
            ->with('user.profile');
    }

    /**
     * Get array of all approver account IDs
     */
    public function getApproverIdsAttribute(): array
    {
        return $this->approvers->pluck('account_id')->toArray();
    }

    /**
     * Check if this step has OR condition (multiple approvers)
     */
    public function getHasOrConditionAttribute(): bool
    {
        return $this->approvers()->where('condition', 'or')->exists();
    }
}
