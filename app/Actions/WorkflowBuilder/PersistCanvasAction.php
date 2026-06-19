<?php

namespace App\Actions\WorkflowBuilder;

use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Services\WorkflowPersistenceService;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use App\Modules\WorkflowBuilder\Services\WorkflowStepService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersistCanvasAction
{
    public function __construct(
        private readonly WorkflowPersistenceService $workflowPersistenceService,
        private readonly WorkflowStepService $workflowStepService,
    ) {}

    /**
     * Persist validated canvas data (nodes + edges) for the given workflow
     * and bust the relevant cache keys.
     *
     * @param  array{nodes: array<mixed>, edges: array<mixed>}  $validated
     *
     * @throws ValidationException if the workflow is not in Draft status.
     */
    public function execute(int $workflowId, array $validated): Workflow
    {
        DB::transaction(function () use ($workflowId, $validated) {
            /** @var Workflow $workflow */
            $workflow = Workflow::lockForUpdate()->findOrFail($workflowId);

            if (strcasecmp($workflow->status, 'Draft') !== 0) {
                throw ValidationException::withMessages([
                    'status' => 'Only Draft workflows can be edited on the canvas.',
                ]);
            }

            $settings = $this->workflowPersistenceService->normalizeWorkflowSettings([
                'nodes' => $validated['nodes'],
                'edges' => $validated['edges'],
            ]);

            $workflow->update(['workflow_settings' => $settings]);

            $this->workflowStepService->updateStepsFromCanvas($workflow, $settings);
        });

        Cache::forget(WorkflowService::workflowDetailsCacheKey($workflowId));
        Cache::forget(WorkflowService::availableFormsCacheKey());

        return Workflow::findOrFail($workflowId);
    }
}
