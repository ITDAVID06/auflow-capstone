<?php

namespace App\Modules\WorkflowBuilder\Services;

use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepApprover;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowDuplicateService
{
    public function duplicate(Workflow $original): Workflow
    {
        /** @var WorkflowVersioningService $versioner */
        $versioner = app(WorkflowVersioningService::class);

        return DB::transaction(function () use ($original, $versioner) {
            // Build name like "<Base> vN", no stacking like "v2 v3" or "(Copy)"
            $baseName = $versioner->extractBaseName($original->workflow_name);
            $nextVersion = $versioner->nextVersionForBase($baseName);
            $newName = "{$baseName} v{$nextVersion}";

            $newWorkflow = Workflow::create([
                'workflow_name' => $newName,
                'workflow_type' => $original->workflow_type,
                'description' => $original->description,
                'form_id' => null,              // duplicated workflows are unbound
                'status' => 'Draft',           // duplicated as Draft
                'workflow_settings' => $this->cloneCanvas($original->workflow_settings ?? ['nodes' => [], 'edges' => []]),
                'created_by' => auth()->user()->account_id ?? $original->created_by,
            ]);

            foreach ($original->steps as $step) {
                $newStep = WorkflowStep::create([
                    'workflow_id' => $newWorkflow->id,
                    'step_name' => $step->step_name,
                    'step_description' => $step->step_description,
                    'step_order' => $step->step_order,
                    'step_group' => $step->step_group,
                    'action_type' => $step->action_type,
                    'assigned_account_id' => $step->assigned_account_id,
                    'max_duration_hours' => $step->max_duration_hours,
                    'step_conditions' => $step->step_conditions,
                ]);

                // Copy approvers from pivot table if they exist
                foreach ($step->approvers as $approver) {
                    WorkflowStepApprover::create([
                        'step_id' => $newStep->id,
                        'account_id' => $approver->account_id,
                        'condition' => $approver->condition,
                        'order' => $approver->order,
                    ]);
                }
            }

            return $newWorkflow;
        });
    }

    /**
     * Clone the ReactFlow canvas while generating new node IDs
     */
    protected function cloneCanvas(array $workflowSettings): array
    {
        if (empty($workflowSettings['nodes'])) {
            return $workflowSettings;
        }

        $nodeMap = [];
        $newNodes = [];

        // First pass: create new IDs for all nodes
        foreach ($workflowSettings['nodes'] as $node) {
            $oldId = $node['id'];
            $newId = $oldId === 'start' ? 'start' : Str::uuid()->toString();
            $nodeMap[$oldId] = $newId;
        }

        // Second pass: update nodes with new IDs and parent references
        foreach ($workflowSettings['nodes'] as $node) {
            $oldId = $node['id'];
            $node['id'] = $nodeMap[$oldId];

            // Update parentNode reference if exists
            if (isset($node['parentNode'])) {
                $node['parentNode'] = $nodeMap[$node['parentNode']] ?? $node['parentNode'];
            }

            $newNodes[] = $node;
        }

        $newEdges = [];
        foreach ($workflowSettings['edges'] ?? [] as $edge) {
            $edge['id'] = Str::uuid()->toString();
            $edge['source'] = $nodeMap[$edge['source']] ?? $edge['source'];
            $edge['target'] = $nodeMap[$edge['target']] ?? $edge['target'];
            $newEdges[] = $edge;
        }

        $cloneContainers = function (array $containers) use ($nodeMap): array {
            $cloned = [];
            foreach ($containers as $container) {
                $oldId = $container['id'];
                $container['id'] = $nodeMap[$oldId] ?? Str::uuid()->toString();

                if (isset($container['children']) && is_array($container['children'])) {
                    $container['children'] = array_map(
                        fn ($childId) => $nodeMap[$childId] ?? $childId,
                        $container['children']
                    );
                }

                $cloned[] = $container;
            }

            return $cloned;
        };

        $cloneEdges = function (array $edges) use ($nodeMap): array {
            $cloned = [];
            foreach ($edges as $edge) {
                $edge['id'] = Str::uuid()->toString();
                $edge['source'] = $nodeMap[$edge['source']] ?? $edge['source'];
                $edge['target'] = $nodeMap[$edge['target']] ?? $edge['target'];
                $cloned[] = $edge;
            }

            return $cloned;
        };

        $result = [
            'nodes' => $newNodes,
            'edges' => $newEdges,
        ];

        if (! empty($workflowSettings['stepOrder']) && is_array($workflowSettings['stepOrder'])) {
            $result['stepOrder'] = array_values(array_map(
                fn ($stepId) => $nodeMap[$stepId] ?? $stepId,
                $workflowSettings['stepOrder']
            ));
        }

        if (! empty($workflowSettings['authoring']) && is_array($workflowSettings['authoring'])) {
            $result['authoring'] = $workflowSettings['authoring'];

            if (! empty($workflowSettings['authoring']['containers']) && is_array($workflowSettings['authoring']['containers'])) {
                $result['authoring']['containers'] = $cloneContainers($workflowSettings['authoring']['containers']);
            }

            if (! empty($workflowSettings['authoring']['virtual_edges']) && is_array($workflowSettings['authoring']['virtual_edges'])) {
                $result['authoring']['virtual_edges'] = $cloneEdges($workflowSettings['authoring']['virtual_edges']);
            }
        }

        if (! empty($workflowSettings['containers']) && is_array($workflowSettings['containers'])) {
            $result['containers'] = $cloneContainers($workflowSettings['containers']);
        }

        if (! empty($workflowSettings['virtual_edges']) && is_array($workflowSettings['virtual_edges'])) {
            $result['virtual_edges'] = $cloneEdges($workflowSettings['virtual_edges']);
        }

        return $result;
    }
}
