<?php

namespace App\Policies;

use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;

class WorkflowStepProgressPolicy
{
    public function view(User $user, WorkflowStepProgress $progress): bool
    {
        return $this->isUserAnApprover($user, $progress);
    }

    public function update(User $user, WorkflowStepProgress $progress): bool
    {
        return $this->isUserAnApprover($user, $progress) && $progress->status === 'Pending';
    }

    /**
     * Check if user is an approver for this step (supports multi-approver OR condition)
     */
    private function isUserAnApprover(User $user, WorkflowStepProgress $progress): bool
    {
        $step = $progress->step;

        // Check new approvers pivot table
        $isInApprovers = $step->approvers()
            ->where('account_id', $user->account_id)
            ->exists();

        if ($isInApprovers) {
            return true;
        }

        // Fallback: legacy assigned_account_id for backwards compatibility
        return (int) $step->assigned_account_id === (int) $user->account_id;
    }
}
