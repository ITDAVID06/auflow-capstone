<?php

namespace App\Modules\WorkflowBuilder\Models;

use App\Modules\UserManagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepApprover extends Model
{
    protected $table = 'tbl_workflow_step_approvers';

    protected $fillable = [
        'step_id',
        'account_id',
        'condition',
        'order',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    /**
     * Get the workflow step this approver belongs to
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    /**
     * Get the user assigned as approver
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id', 'account_id');
    }
}
