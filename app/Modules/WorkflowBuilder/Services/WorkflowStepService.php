<?php

namespace App\Modules\WorkflowBuilder\Services;

use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowStepService
{
    /**
     * Build steps from canvas data when creating a new workflow
     */
    public function buildStepsFromCanvas(Workflow $workflow, array $canvas)
    {
        $nodes = $canvas['nodes'] ?? [];
        $edges = $canvas['edges'] ?? [];

        $this->validateCanvas($canvas);
        $this->validateAssignedUsers($nodes);

        $stepNodes = array_values(array_filter($nodes, fn (array $node) => $this->isExecutableStepNode($node)));
        if (empty($stepNodes)) {
            throw ValidationException::withMessages([
                'nodes' => 'Workflow must contain at least one executable step node.',
            ]);
        }

        $calculatedGroups = [];
        if (! empty($edges)) {
            [$orderedStepNodes, $calculatedGroups] = $this->resolveGraphOrderAndGroups($nodes, $stepNodes, $edges);
        } else {
            $orderedStepNodes = $this->sortNonSequentialSteps($stepNodes);
        }

        return DB::transaction(function () use ($workflow, $orderedStepNodes, $calculatedGroups) {
            WorkflowStep::where('workflow_id', $workflow->id)->delete();

            foreach ($orderedStepNodes as $order => $node) {
                $nodeId = (string) ($node['id'] ?? '');
                $calculatedGroup = (int) ($calculatedGroups[$nodeId] ?? ($node['data']['step_group'] ?? ($order + 1)));

                // Get approvers array from node data
                $approvers = $node['data']['approvers'] ?? [];

                // For backwards compatibility, if no approvers array but has assigned_account_id
                if (empty($approvers) && ! empty($node['data']['assigned_account_id'])) {
                    $approvers = [[
                        'account_id' => $node['data']['assigned_account_id'],
                        'condition' => 'primary',
                        'order' => 0,
                    ]];
                }

                // Set assigned_account_id to first approver for backwards compat
                $primaryApprover = ! empty($approvers) ? ($approvers[0]['account_id'] ?? null) : null;
                $nodeActionType = strtolower((string) ($node['data']['type'] ?? 'approval'));
                $actionType = $nodeActionType === 'task' ? 'Noted' : 'Approve';
                $reminderInterval = (string) ($node['data']['reminder_interval'] ?? 'default');
                $reminderMode = (string) ($node['data']['reminder_mode'] ?? ($reminderInterval === 'none' ? 'none' : ($reminderInterval === 'default' ? 'default' : 'custom')));

                $step = WorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'step_name' => $node['data']['label'] ?? 'Untitled Step',
                    'step_order' => $order + 1,
                    'step_group' => $calculatedGroup,
                    'action_type' => $actionType,
                    'assigned_account_id' => $primaryApprover,
                    'max_duration_hours' => $node['data']['max_duration_hours'] ?? null,
                    'step_conditions' => [
                        'description' => $node['data']['description'] ?? null,
                        'duration_days' => $node['data']['duration_days'] ?? null,
                        'conditions' => $node['data']['conditions'] ?? null,
                        'notifications' => $node['data']['notifications'] ?? null,
                        'criteria' => $node['data']['criteria'] ?? null,
                        'message' => $node['data']['message'] ?? null,
                        'type' => $node['data']['type'] ?? 'approval',
                        'position' => $node['position'] ?? null,
                        'watch_fields' => array_values(array_filter((array) ($node['data']['watch_fields'] ?? []))),
                        'reminder_mode' => $reminderMode,
                        'reminder_interval' => $reminderInterval,
                        'reminder_value' => isset($node['data']['reminder_value']) ? (int) $node['data']['reminder_value'] : null,
                        'reminder_unit' => isset($node['data']['reminder_unit']) ? (string) $node['data']['reminder_unit'] : null,
                        'max_duration_hours' => $node['data']['max_duration_hours'] ?? null,
                        'branch_condition' => isset($node['data']['branch_condition']) && is_array($node['data']['branch_condition'])
                            ? $node['data']['branch_condition']
                            : null,
                    ],
                ]);

                // Create approver entries in pivot table
                if (! empty($approvers)) {
                    foreach ($approvers as $approver) {
                        if (! empty($approver['account_id'])) {
                            \App\Modules\WorkflowBuilder\Models\WorkflowStepApprover::create([
                                'step_id' => $step->id,
                                'account_id' => $approver['account_id'],
                                'condition' => $approver['condition'] ?? 'primary',
                                'order' => $approver['order'] ?? 0,
                            ]);
                        }
                    }
                }
            }

            return $workflow->steps;
        });
    }

    /**
     * Update steps from canvas data when editing a workflow
     */
    public function updateStepsFromCanvas(Workflow $workflow, array $canvas)
    {
        return $this->buildStepsFromCanvas($workflow, $canvas);
    }

    /**
     * Basic validation to ensure workflow has a start node
     */
    protected function validateCanvas(array $canvas)
    {
        $nodes = $canvas['nodes'] ?? [];
        if (empty($nodes)) {
            throw ValidationException::withMessages([
                'nodes' => 'Workflow must have at least one step.',
            ]);
        }

        $hasStart = collect($nodes)->contains(function ($node) {
            return ($node['data']['type'] ?? '') === 'form_submitted'
                || ($node['id'] ?? '') === 'start';
        });

        if (! $hasStart) {
            throw ValidationException::withMessages([
                'start' => 'Workflow must have a start node (Form Submitted).',
            ]);
        }
    }

    private function validateAssignedUsers(array $nodes): void
    {
        $assignedIds = collect($nodes)
            ->filter(fn ($node) => $this->isExecutableStepNode((array) $node))
            ->pluck('data.assigned_account_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($assignedIds)) {
            return; // No assignments to validate
        }

        // Check all assigned users are valid approvers
        $validIds = DB::table('tbl_user as u')
            ->join('tbl_user_role as ur', 'u.account_id', '=', 'ur.account_id')
            ->join('tbl_role_permission as rp', 'rp.role_id', '=', 'ur.role_id')
            ->join('tbl_permission as perm', 'perm.id', '=', 'rp.permission_id')
            ->where('u.user_status_id', 1)
            ->where('ur.is_active', 1)
            ->where(function ($q) {
                $q->whereNull('ur.expiry_date')
                    ->orWhere('ur.expiry_date', '>', now());
            })
            ->whereIn('perm.slug', ['requests.approve', 'submissions.override'])
            ->whereIn('u.account_id', $assignedIds)
            ->pluck('u.account_id')
            ->all();

        $invalidIds = array_diff($assignedIds, $validIds);

        if (! empty($invalidIds)) {
            throw ValidationException::withMessages([
                'assigned_account_id' => 'Invalid approver IDs: '.implode(', ', $invalidIds),
            ]);
        }
    }

    private function isExecutableStepNode(array $node): bool
    {
        $nodeId = (string) ($node['id'] ?? '');
        $nodeType = (string) ($node['type'] ?? '');
        $dataType = strtolower((string) ($node['data']['type'] ?? ''));

        if ($nodeId === 'start') {
            return false;
        }

        if ($nodeType === 'branchContainer') {
            return false;
        }

        return ! in_array($dataType, ['form_submitted', 'branching'], true);
    }

    private function sortNonSequentialSteps(array $stepNodes): array
    {
        usort($stepNodes, function (array $a, array $b): int {
            $groupA = (int) ($a['data']['step_group'] ?? 1);
            $groupB = (int) ($b['data']['step_group'] ?? 1);
            if ($groupA !== $groupB) {
                return $groupA <=> $groupB;
            }

            $xA = (float) ($a['position']['x'] ?? 0);
            $xB = (float) ($b['position']['x'] ?? 0);
            if ($xA !== $xB) {
                return $xA <=> $xB;
            }

            $yA = (float) ($a['position']['y'] ?? 0);
            $yB = (float) ($b['position']['y'] ?? 0);
            if ($yA !== $yB) {
                return $yA <=> $yB;
            }

            return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
        });

        return $stepNodes;
    }

    /**
     * Resolve deterministic step order and group depth from the canvas graph.
     *
     * Branch container nodes are treated as pass-through containers, so
     * executable steps inside the same branch share a group depth.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $stepNodes
     * @param  array<int, array<string, mixed>>  $edges
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, int>}
     */
    private function resolveGraphOrderAndGroups(array $nodes, array $stepNodes, array $edges): array
    {
        $nodeById = [];
        $startOrigins = ['start'];
        foreach ($nodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $nodeById[$id] = $node;

            $isStartLike = $id === 'start' || strtolower((string) ($node['data']['type'] ?? '')) === 'form_submitted';
            if ($isStartLike && ! in_array($id, $startOrigins, true)) {
                $startOrigins[] = $id;
            }
        }

        $stepNodeById = [];
        foreach ($stepNodes as $node) {
            $id = (string) ($node['id'] ?? '');
            if ($id !== '') {
                $stepNodeById[$id] = $node;
            }
        }

        $stepIds = array_keys($stepNodeById);
        $stepIdLookup = array_fill_keys($stepIds, true);
        $knownNodeLookup = array_fill_keys(array_merge(array_keys($nodeById), ['start']), true);

        $containerChildStepIds = [];
        foreach ($stepNodes as $stepNode) {
            $stepId = (string) ($stepNode['id'] ?? '');
            $parentId = (string) ($stepNode['parentNode'] ?? '');

            if ($stepId === '' || $parentId === '') {
                continue;
            }

            if (($nodeById[$parentId]['type'] ?? null) !== 'branchContainer') {
                continue;
            }

            $containerChildStepIds[$parentId] ??= [];
            if (! in_array($stepId, $containerChildStepIds[$parentId], true)) {
                $containerChildStepIds[$parentId][] = $stepId;
            }
        }

        $adjAll = [];
        $adjSteps = [];
        $inDegree = array_fill_keys($stepIds, 0);

        $addAdjacency = static function (array &$adjacency, string $source, string $target): void {
            if ($source === '' || $target === '') {
                return;
            }

            $adjacency[$source] ??= [];
            if (! in_array($target, $adjacency[$source], true)) {
                $adjacency[$source][] = $target;
            }
        };

        foreach ($edges as $edge) {
            $source = (string) ($edge['source'] ?? '');
            $target = (string) ($edge['target'] ?? '');

            if ($source === '' || $target === '') {
                continue;
            }

            if (! isset($knownNodeLookup[$source]) || ! isset($knownNodeLookup[$target])) {
                continue;
            }

            $sourceChildren = $containerChildStepIds[$source] ?? [];
            $targetChildren = $containerChildStepIds[$target] ?? [];

            $sourceIsContainer = ! empty($sourceChildren);
            $targetIsContainer = ! empty($targetChildren);

            if (! $sourceIsContainer && ! $targetIsContainer) {
                $addAdjacency($adjAll, $source, $target);

                continue;
            }

            if ($sourceIsContainer && ! $targetIsContainer) {
                foreach ($sourceChildren as $childStepId) {
                    $addAdjacency($adjAll, $childStepId, $target);
                }

                continue;
            }

            if (! $sourceIsContainer && $targetIsContainer) {
                foreach ($targetChildren as $childStepId) {
                    $addAdjacency($adjAll, $source, $childStepId);
                }

                continue;
            }

            foreach ($sourceChildren as $sourceChildStepId) {
                foreach ($targetChildren as $targetChildStepId) {
                    $addAdjacency($adjAll, $sourceChildStepId, $targetChildStepId);
                }
            }
        }

        $findNextExecutableTargets = function (string $origin) use ($adjAll, $stepIdLookup): array {
            $queue = $adjAll[$origin] ?? [];
            $visited = [];
            $targets = [];

            while (! empty($queue)) {
                $current = array_shift($queue);

                if (isset($visited[$current])) {
                    continue;
                }

                $visited[$current] = true;

                if (isset($stepIdLookup[$current])) {
                    $targets[$current] = true;

                    continue;
                }

                foreach ($adjAll[$current] ?? [] as $next) {
                    $queue[] = $next;
                }
            }

            return array_keys($targets);
        };

        foreach ($stepIds as $sourceStepId) {
            $adjSteps[$sourceStepId] ??= [];

            foreach ($findNextExecutableTargets($sourceStepId) as $targetStepId) {
                if ($targetStepId === $sourceStepId) {
                    continue;
                }

                if (! in_array($targetStepId, $adjSteps[$sourceStepId], true)) {
                    $adjSteps[$sourceStepId][] = $targetStepId;
                    $inDegree[$targetStepId]++;
                }
            }
        }

        $depthByStepId = [];
        $startTargets = [];
        foreach ($startOrigins as $startOrigin) {
            foreach ($findNextExecutableTargets($startOrigin) as $startTarget) {
                $startTargets[$startTarget] = true;
                $depthByStepId[$startTarget] = 1;
            }
        }

        $reachableStepIds = [];
        $reachQueue = $startOrigins;
        $visitedNodes = [];
        while (! empty($reachQueue)) {
            $current = array_shift($reachQueue);

            if (isset($visitedNodes[$current])) {
                continue;
            }

            $visitedNodes[$current] = true;
            if (isset($stepIdLookup[$current])) {
                $reachableStepIds[$current] = true;
            }

            foreach ($adjAll[$current] ?? [] as $nextNode) {
                if (! isset($visitedNodes[$nextNode])) {
                    $reachQueue[] = $nextNode;
                }
            }
        }

        $unreachable = array_values(array_filter($stepIds, fn (string $id): bool => ! isset($reachableStepIds[$id])));
        if (! empty($unreachable)) {
            throw ValidationException::withMessages([
                'nodes' => 'Workflow contains step(s) not reachable from start: '.implode(', ', $unreachable),
            ]);
        }

        $ready = array_values(array_filter($stepIds, fn (string $id): bool => ($inDegree[$id] ?? 0) === 0));
        $orderedIds = [];

        while (! empty($ready)) {
            usort($ready, function (string $a, string $b) use ($depthByStepId, $stepNodeById): int {
                $depthA = $depthByStepId[$a] ?? 1;
                $depthB = $depthByStepId[$b] ?? 1;
                if ($depthA !== $depthB) {
                    return $depthA <=> $depthB;
                }

                $xA = (float) ($stepNodeById[$a]['position']['x'] ?? 0);
                $xB = (float) ($stepNodeById[$b]['position']['x'] ?? 0);
                if ($xA !== $xB) {
                    return $xA <=> $xB;
                }

                $yA = (float) ($stepNodeById[$a]['position']['y'] ?? 0);
                $yB = (float) ($stepNodeById[$b]['position']['y'] ?? 0);
                if ($yA !== $yB) {
                    return $yA <=> $yB;
                }

                return strcmp($a, $b);
            });

            $current = array_shift($ready);
            if (in_array($current, $orderedIds, true)) {
                continue;
            }

            $orderedIds[] = $current;
            $currentDepth = $depthByStepId[$current] ?? 1;

            foreach ($adjSteps[$current] ?? [] as $next) {
                $nextDepth = $currentDepth + 1;
                if (! isset($depthByStepId[$next]) || $nextDepth > $depthByStepId[$next]) {
                    $depthByStepId[$next] = $nextDepth;
                }

                $inDegree[$next] = max(0, ($inDegree[$next] ?? 0) - 1);
                if ($inDegree[$next] === 0) {
                    $ready[] = $next;
                }
            }
        }

        if (count($orderedIds) !== count($stepIds)) {
            throw ValidationException::withMessages([
                'edges' => 'Workflow ordering could not be resolved. Check for invalid or cyclic connections.',
            ]);
        }

        $orderedNodes = array_map(fn (string $id): array => $stepNodeById[$id], $orderedIds);

        return [$orderedNodes, $depthByStepId];
    }
}
