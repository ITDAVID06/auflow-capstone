<?php

namespace App\Modules\WorkflowBuilder\Controllers;

use App\Actions\WorkflowBuilder\PersistCanvasAction;
use App\Http\Controllers\Controller;
use App\Modules\WorkflowBuilder\Requests\SaveCanvasRequest;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use Illuminate\Http\JsonResponse;

class WorkflowCanvasController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly PersistCanvasAction $persistCanvasAction,
    ) {}

    public function show(int $id): JsonResponse
    {
        $workflow = $this->workflowService->getWorkflowDetails($id);

        return response()->json([
            'id' => $workflow->id,
            'workflow_name' => $workflow->workflow_name,
            'status' => $workflow->status,
            'workflow_settings' => $workflow->workflow_settings ?? ['nodes' => [], 'edges' => []],
            'steps' => $workflow->steps->map(fn ($step) => [
                'id' => $step->id,
                'step_name' => $step->step_name,
                'step_order' => $step->step_order,
                'step_group' => $step->step_group,
                'action_type' => $step->action_type,
                'assigned_account_id' => $step->assigned_account_id,
                'max_duration_hours' => $step->max_duration_hours,
                'step_conditions' => $step->step_conditions,
                'branch_condition' => $step->step_conditions['branch_condition'] ?? null,
            ])->values(),
        ]);
    }

    public function save(SaveCanvasRequest $request, int $id): JsonResponse
    {
        $this->persistCanvasAction->execute($id, $request->validated());

        $updated = $this->workflowService->getWorkflowDetails($id);

        return response()->json([
            'ok' => true,
            'id' => $updated->id,
            'workflow_name' => $updated->workflow_name,
            'status' => $updated->status,
            'workflow_settings' => $updated->workflow_settings,
            'steps' => $updated->steps->map(fn ($step) => [
                'id' => $step->id,
                'step_name' => $step->step_name,
                'step_order' => $step->step_order,
                'step_group' => $step->step_group,
                'action_type' => $step->action_type,
                'assigned_account_id' => $step->assigned_account_id,
                'max_duration_hours' => $step->max_duration_hours,
                'step_conditions' => $step->step_conditions,
                'branch_condition' => $step->step_conditions['branch_condition'] ?? null,
            ])->values(),
        ]);
    }
}
