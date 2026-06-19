<?php

namespace App\Modules\WorkflowBuilder\Observers;

use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;

class WorkflowStepObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(WorkflowStep $m): void
    {
        $this->flushWorkflowDefinitionCache($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('workflow_step_created', $m, 'Success', "Created step {$name}", [
            'step_id' => $m->getKey(),
            'step_name' => $name,
            'workflow_id' => $m->workflow_id,
        ]);
    }

    public function updated(WorkflowStep $m): void
    {
        $this->flushWorkflowDefinitionCache($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('workflow_step_updated', $m, 'Success', "Updated step {$name}", [
            'step_id' => $m->getKey(),
            'step_name' => $name,
            'workflow_id' => $m->workflow_id,
        ]);
    }

    public function deleted(WorkflowStep $m): void
    {
        $this->flushWorkflowDefinitionCache($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('workflow_step_deleted', $m, 'Warning', "Deleted step {$name}", [
            'step_id' => $m->getKey(),
            'step_name' => $name,
            'workflow_id' => $m->workflow_id,
        ]);
    }

    private function nameOf(WorkflowStep $m): string
    {
        return $m->step_name ?? $m->name ?? $m->label ?? (string) $m->getKey();
    }

    private function flushWorkflowDefinitionCache(WorkflowStep $step): void
    {
        if ($step->workflow_id) {
            Cache::forget(WorkflowService::workflowDetailsCacheKey((int) $step->workflow_id));
        }
    }
}
