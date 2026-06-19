<?php

namespace App\Modules\WorkflowBuilder\Observers;

use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;

class WorkflowObserver
{
    public function __construct(private AuditLogger $audit) {}

    public function created(Workflow $m): void
    {
        $this->flushCaches($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('workflow_created', $m, 'Success', "Created workflow {$name}", [
            'workflow_id' => $m->getKey(),
            'workflow_name' => $name,
        ]);
    }

    public function updated(Workflow $m): void
    {
        $this->flushCaches($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('workflow_updated', $m, 'Success', "Updated workflow {$name}", [
            'workflow_id' => $m->getKey(),
            'workflow_name' => $name,
        ]);
    }

    public function deleted(Workflow $m): void
    {
        $this->flushCaches($m);

        $name = $this->nameOf($m);
        $this->audit->userAction('workflow_deleted', $m, 'Warning', "Deleted workflow {$name}", [
            'workflow_id' => $m->getKey(),
            'workflow_name' => $name,
        ]);
    }

    private function nameOf(Workflow $m): string
    {
        return $m->workflow_name ?? $m->name ?? $m->title ?? (string) $m->getKey();
    }

    private function flushCaches(Workflow $workflow): void
    {
        Cache::forget(WorkflowService::workflowDetailsCacheKey((int) $workflow->getKey()));
        Cache::forget(WorkflowService::availableFormsCacheKey());
        Cache::forget(WorkflowService::assignableUsersCacheKey());
    }
}
