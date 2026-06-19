<?php

namespace App\Modules\FormBuilder\Services;

use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Support\Collection;

class SubmissionReadRepository
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findByProgress(WorkflowStepProgress $progress, array $relations = []): ?FormSubmission
    {
        return FormSubmission::query()->with($relations)->find($progress->submission_id);
    }

    public function workflowStatusesForSubmission(int $workflowId, int $submissionId): Collection
    {
        return WorkflowStepProgress::query()
            ->where('workflow_id', $workflowId)
            ->where('submission_id', $submissionId)
            ->pluck('status');
    }
}
