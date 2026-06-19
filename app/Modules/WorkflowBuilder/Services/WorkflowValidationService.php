<?php

namespace App\Modules\WorkflowBuilder\Services;

use InvalidArgumentException;

final class WorkflowValidationService
{
    /**
     * Validate a canvas (nodes/edges) before publish.
     *
     * @param  array  $nodes  ReactFlow nodes
     * @param  array  $edges  ReactFlow edges
     * @param  string|null  $workflowType  The workflow type (Sequential/Parallel)
     *
     * @throws InvalidArgumentException
     */
    public function validate(array $nodes, array $edges, ?string $workflowType = null): void
    {
        if (empty($nodes)) {
            throw new InvalidArgumentException('No nodes defined.');
        }

        // Check for Branch nodes in Sequential workflows
        if ($workflowType && strcasecmp($workflowType, 'Sequential') === 0) {
            $branchNodes = collect($nodes)
                ->filter(fn ($n) => ($n['type'] ?? null) === 'branchContainer')
                ->pluck('data.label')
                ->values();

            if ($branchNodes->isNotEmpty()) {
                throw new InvalidArgumentException('Sequential workflows cannot contain Branch nodes. Please switch to Parallel workflow type or remove the following branch nodes: '.$branchNodes->implode(', '));
            }
        }

        $byId = collect($nodes)->keyBy('id');
        $ids = $byId->keys()->all();
        $knownNodeIds = array_fill_keys(array_map('strval', $ids), true);

        // Start node check
        $start = collect($nodes)->first(fn ($n) => ($n['data']['type'] ?? null) === 'form_submitted');
        if (! $start) {
            throw new InvalidArgumentException('Missing start node (Form Submitted).');
        }

        // Edge endpoints must exist (ignore known UI helper terminals like __end__ and __plus__*)
        $validatedEdges = [];
        foreach ($edges as $e) {
            if (! isset($e['source'], $e['target'])) {
                throw new InvalidArgumentException('Edge is missing source or target.');
            }

            $source = (string) $e['source'];
            $target = (string) $e['target'];

            $sourceKnown = isset($knownNodeIds[$source]);
            $targetKnown = isset($knownNodeIds[$target]);

            if (! $sourceKnown || ! $targetKnown) {
                $isUiHelperEndpoint = $source === '__end__'
                    || $target === '__end__'
                    || str_starts_with($source, '__plus__')
                    || str_starts_with($target, '__plus__');

                if ($isUiHelperEndpoint) {
                    continue;
                }

                throw new InvalidArgumentException("Edge references unknown node(s): {$source} -> {$target}.");
            }

            $validatedEdges[] = [
                'source' => $source,
                'target' => $target,
            ];
        }

        // No cycles
        $adj = [];
        foreach ($validatedEdges as $e) {
            $adj[$e['source']][] = $e['target'];
        }
        $vis = [];
        $rec = [];
        $hasCycle = false;
        $dfs = function (string $u) use (&$dfs, &$vis, &$rec, &$adj, &$hasCycle): void {
            $vis[$u] = true;
            $rec[$u] = true;
            foreach ($adj[$u] ?? [] as $v) {
                if (empty($vis[$v]) && ! $hasCycle) {
                    $dfs($v);
                } elseif (! empty($rec[$v])) {
                    $hasCycle = true;
                }
            }
            $rec[$u] = false;
        };
        $dfs($start['id']);
        if ($hasCycle) {
            throw new InvalidArgumentException('Workflow has a cycle.');
        }

        if ($workflowType && strcasecmp($workflowType, 'Sequential') === 0) {
            $startId = (string) ($start['id'] ?? 'start');

            $isExecutableStep = static fn (array $node): bool => strtolower((string) ($node['data']['type'] ?? '')) !== 'form_submitted'
                && ($node['type'] ?? null) !== 'branchContainer';

            $stepNodes = collect($nodes)
                ->filter(fn ($node) => $isExecutableStep((array) $node))
                ->values();

            $stepIds = $stepNodes->pluck('id')->map(fn ($id) => (string) $id)->all();
            $stepLookup = array_fill_keys($stepIds, true);

            $inDegree = array_fill_keys($stepIds, 0);
            $outDegree = array_fill_keys($stepIds, 0);
            $startOutDegree = 0;
            $reachable = [];

            $adjacencyForReachability = [];

            foreach ($validatedEdges as $edge) {
                $source = (string) ($edge['source'] ?? '');
                $target = (string) ($edge['target'] ?? '');

                if ($target === '' || ! isset($stepLookup[$target])) {
                    continue;
                }

                if ($source !== $startId && ! isset($stepLookup[$source])) {
                    continue;
                }

                $adjacencyForReachability[$source] ??= [];
                if (! in_array($target, $adjacencyForReachability[$source], true)) {
                    $adjacencyForReachability[$source][] = $target;
                }

                $inDegree[$target]++;

                if ($source === $startId) {
                    $startOutDegree++;
                } else {
                    $outDegree[$source] = ($outDegree[$source] ?? 0) + 1;
                }
            }

            if ($startOutDegree > 1) {
                throw new InvalidArgumentException('Sequential workflow must not branch from Form Submitted.');
            }

            foreach ($outDegree as $stepId => $count) {
                if ($count > 1) {
                    throw new InvalidArgumentException("Sequential workflow step '{$stepId}' has multiple outgoing paths. Branching requires Parallel workflow type.");
                }
            }

            foreach ($inDegree as $stepId => $count) {
                if ($count > 1) {
                    throw new InvalidArgumentException("Sequential workflow step '{$stepId}' has multiple incoming paths. Merge paths require Parallel workflow type.");
                }
            }

            $queue = [$startId];
            $seen = [$startId => true];

            while (! empty($queue)) {
                $current = array_shift($queue);
                foreach ($adjacencyForReachability[$current] ?? [] as $next) {
                    if (! isset($seen[$next])) {
                        $seen[$next] = true;
                        $reachable[$next] = true;
                        $queue[] = $next;
                    }
                }
            }

            $unreachable = array_values(array_filter($stepIds, fn (string $id): bool => ! isset($reachable[$id])));
            if (! empty($unreachable)) {
                throw new InvalidArgumentException('Sequential workflow has unreachable step(s): '.implode(', ', $unreachable));
            }
        }

        // Assignees required for approval/task
        $missing = collect($nodes)
            ->filter(fn ($n) => $this->requiresAssignedUser($n))
            ->filter(function ($n) {
                // Check new approvers array first
                $approvers = $n['data']['approvers'] ?? [];
                $hasApproversAssigned = ! empty($approvers) && collect($approvers)->some(fn ($a) => ! empty($a['account_id']));

                // Fallback to legacy assigned_account_id for backwards compatibility
                $hasLegacyAssignee = ! empty($n['data']['assigned_account_id']) || ! empty($n['data']['assigned_user']);

                // Node is unassigned if neither new nor legacy assignment exists
                return ! $hasApproversAssigned && ! $hasLegacyAssignee;
            })
            ->pluck('data.label')->values();

        if ($missing->isNotEmpty()) {
            throw new InvalidArgumentException('Unassigned steps: '.$missing->implode(', '));
        }

        // Validate approvers have valid account IDs (for nodes with approvers array)
        $invalidApprovers = collect($nodes)
            ->filter(fn ($n) => $this->requiresAssignedUser($n))
            ->filter(function ($n) {
                $approvers = $n['data']['approvers'] ?? [];
                if (empty($approvers)) {
                    return false;
                }

                // Check if any approver has invalid account_id
                return collect($approvers)->some(fn ($a) => isset($a['account_id']) && (! is_numeric($a['account_id']) || $a['account_id'] <= 0));
            })
            ->pluck('data.label')->values();

        if ($invalidApprovers->isNotEmpty()) {
            throw new InvalidArgumentException('Invalid approver assignments in steps: '.$invalidApprovers->implode(', '));
        }
    }

    private function requiresAssignedUser(array $node): bool
    {
        $nodeType = (string) ($node['type'] ?? '');
        $dataType = strtolower((string) ($node['data']['type'] ?? 'approval'));

        if ($nodeType === 'branchContainer' || $dataType === 'branching') {
            return false;
        }

        return in_array($dataType, ['approval', 'task'], true);
    }
}
