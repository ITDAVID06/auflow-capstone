<?php

namespace App\Services\DemoSeeding;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepApprover;

class DemoWorkflowSeeder
{
    /**
     * @param  array<int, Form>  $forms
     * @return array<int, array{workflow: Workflow, steps: array<int, WorkflowStep>}>
     */
    public function seed(array $forms, int $adminAccountId): array
    {
        $staffApprovers = User::query()
            ->where('email', 'like', 'staff%@auf.test')
            ->orderBy('account_id')
            ->pluck('account_id')
            ->all();

        if ($staffApprovers === []) {
            $staffApprovers = [$adminAccountId];
        }

        $seeded = [];
        foreach ($forms as $index => $form) {
            $formSequence = $index + 1;
            $hasBranching = (($index + 1) % 2) === 0;

            $workflow = Workflow::query()->updateOrCreate(
                [
                    'form_id' => $form->id,
                    'status' => 'Active',
                    'version' => 1,
                ],
                [
                    'workflow_name' => "{$form->form_name} Approval Flow",
                    'workflow_type' => $hasBranching ? 'Parallel' : 'Sequential',
                    'effective_from' => now()->subMonth(),
                    'effective_to' => null,
                    'description' => 'Deterministic workflow seeded for demo scenarios.',
                    'workflow_settings' => [
                        'layout' => $hasBranching ? 'parallel' : 'sequential',
                        'source' => 'seed:demo',
                    ],
                    'created_by' => $adminAccountId,
                ]
            );

            $stepDefinitions = $hasBranching
                ? [
                    ['step_name' => 'Initial Review', 'action_type' => 'Review', 'step_order' => 1, 'step_group' => 1],
                    ['step_name' => 'Department Approval', 'action_type' => 'Approve', 'step_order' => 2, 'step_group' => 2],
                    ['step_name' => 'Compliance Approval', 'action_type' => 'Approve', 'step_order' => 3, 'step_group' => 2],
                    ['step_name' => 'Final Verification', 'action_type' => 'Verify', 'step_order' => 4, 'step_group' => 3],
                ]
                : [
                    ['step_name' => 'Initial Review', 'action_type' => 'Review', 'step_order' => 1, 'step_group' => 1],
                    ['step_name' => 'Department Approval', 'action_type' => 'Approve', 'step_order' => 2, 'step_group' => 2],
                    ['step_name' => 'Final Verification', 'action_type' => 'Verify', 'step_order' => 3, 'step_group' => 3],
                ];

            $primaryAssigneeIndex = $index % count($staffApprovers);

            $steps = [];
            foreach ($stepDefinitions as $stepIndex => $definition) {
                $assigneeOffset = match ((int) $definition['step_order']) {
                    1, 2 => 0,
                    3 => 1,
                    default => 2,
                };
                $accountId = (int) ($definition['step_order'] === 2
                    ? $staffApprovers[0]
                    : $staffApprovers[($primaryAssigneeIndex + $assigneeOffset) % count($staffApprovers)]);

                $step = WorkflowStep::query()->updateOrCreate(
                    [
                        'workflow_id' => $workflow->id,
                        'step_order' => $definition['step_order'],
                    ],
                    [
                        'step_name' => $definition['step_name'],
                        'step_description' => 'Seeded demo workflow step.',
                        'step_group' => $definition['step_group'],
                        'action_type' => $definition['action_type'],
                        'assigned_account_id' => $accountId,
                        'max_duration_hours' => 48,
                        'step_conditions' => [
                            'seed_source' => 'seed:demo',
                            'service_level' => [
                                'target_hours' => (int) ($definition['step_order'] <= 2 ? 24 : 36),
                                'priority' => $definition['step_order'] === 1 ? 'high' : 'normal',
                            ],
                            'routing' => [
                                'on_reject' => 'return_to_first_step',
                            ],
                            'position' => ['id' => "demo-step-{$workflow->id}-{$definition['step_order']}"],
                        ],
                        'if_rejected_id' => null,
                    ]
                );

                WorkflowStepApprover::query()->updateOrCreate(
                    [
                        'step_id' => $step->id,
                        'account_id' => $accountId,
                    ],
                    [
                        'condition' => 'primary',
                        'order' => 1,
                    ]
                );

                $shouldSeedOrApprover = $definition['step_order'] >= 2
                    && count($staffApprovers) > 1
                    && ($hasBranching || ($formSequence % 3) === 0);

                if ($shouldSeedOrApprover) {
                    $fallbackAccountId = (int) $staffApprovers[($primaryAssigneeIndex + $assigneeOffset + 1) % count($staffApprovers)];
                    if ($fallbackAccountId !== $accountId) {
                        WorkflowStepApprover::query()->updateOrCreate(
                            [
                                'step_id' => $step->id,
                                'account_id' => $fallbackAccountId,
                            ],
                            [
                                'condition' => 'or',
                                'order' => 2,
                            ]
                        );
                    }
                }

                $steps[] = $step;
            }

            // Route rejection to first step for revision flow demos.
            if (isset($steps[0], $steps[1])) {
                $rejectedTarget = $steps[0]->id;
                foreach ($steps as $stepIndex => $step) {
                    if ($stepIndex === 0) {
                        continue;
                    }

                    $step->update(['if_rejected_id' => $rejectedTarget]);
                }
            }

            $workflow->update([
                'workflow_settings' => $this->buildWorkflowSettings($workflow, $steps, $hasBranching, $formSequence),
            ]);

            $seeded[$form->id] = [
                'workflow' => $workflow,
                'steps' => $steps,
            ];
        }

        return $seeded;
    }

    /**
     * @param  array<int, WorkflowStep>  $steps
     * @return array<string, mixed>
     */
    private function buildWorkflowSettings(Workflow $workflow, array $steps, bool $hasBranching, int $formSequence): array
    {
        $startNode = [
            'id' => 'start',
            'type' => 'start',
            'position' => ['x' => 120, 'y' => 120],
            'data' => [
                'type' => 'form_submitted',
                'label' => $workflow->workflow_name,
            ],
        ];

        $stepNodes = [];
        foreach ($steps as $index => $step) {
            $stepNodeId = "demo-step-{$workflow->id}-{$step->step_order}";

            $stepNodes[] = [
                'id' => $stepNodeId,
                'type' => 'step',
                'position' => [
                    'x' => $hasBranching
                        ? match ((int) $step->step_order) {
                            1 => 420,
                            2 => 840,
                            3 => 840,
                            default => 1240,
                        }
                        : 120 + ($index * 280),
                    'y' => $hasBranching
                        ? match ((int) $step->step_order) {
                            2 => 150,
                            3 => 380,
                            default => 260,
                        }
                        : 260,
                ],
                'data' => [
                    'step_id' => $step->id,
                    'step_name' => $step->step_name,
                    'label' => $step->step_name,
                    'type' => strtolower((string) $step->action_type),
                ],
            ];

            if ($hasBranching && in_array((int) $step->step_order, [2, 3], true)) {
                $stepNodes[count($stepNodes) - 1]['parentNode'] = "demo-branch-{$workflow->id}";
            }
        }

        $nodes = [$startNode];
        $edges = [];

        if ($hasBranching) {
            $branchNodeId = "demo-branch-{$workflow->id}";
            $branchNode = [
                'id' => $branchNodeId,
                'type' => 'branchContainer',
                'position' => ['x' => 760, 'y' => 90],
                'style' => ['width' => 340, 'height' => 380],
                'data' => [
                    'type' => 'branching',
                    'label' => 'Parallel Review',
                ],
            ];

            $nodes[] = $branchNode;
            $nodes = array_merge($nodes, $stepNodes);

            $orderedNodeIds = collect($stepNodes)->keyBy(function (array $node): int {
                $parts = explode('-', (string) ($node['id'] ?? ''));

                return (int) end($parts);
            });

            $stepOneId = (string) ($orderedNodeIds[1]['id'] ?? '');
            $stepTwoId = (string) ($orderedNodeIds[2]['id'] ?? '');
            $stepThreeId = (string) ($orderedNodeIds[3]['id'] ?? '');
            $stepFourId = (string) ($orderedNodeIds[4]['id'] ?? '');

            if ($stepOneId !== '') {
                $edges[] = [
                    'id' => "edge-start-{$stepOneId}",
                    'source' => 'start',
                    'target' => $stepOneId,
                    'type' => 'straight',
                ];
                $edges[] = [
                    'id' => "edge-{$stepOneId}-{$branchNodeId}",
                    'source' => $stepOneId,
                    'target' => $branchNodeId,
                    'type' => 'straight',
                ];
            }

            if ($stepTwoId !== '') {
                $edges[] = [
                    'id' => "edge-{$branchNodeId}-{$stepTwoId}",
                    'source' => $branchNodeId,
                    'target' => $stepTwoId,
                    'type' => 'straight',
                ];
            }

            if ($stepThreeId !== '') {
                $edges[] = [
                    'id' => "edge-{$branchNodeId}-{$stepThreeId}",
                    'source' => $branchNodeId,
                    'target' => $stepThreeId,
                    'type' => 'straight',
                ];
            }

            if ($stepTwoId !== '' && $stepFourId !== '') {
                $edges[] = [
                    'id' => "edge-{$stepTwoId}-{$stepFourId}",
                    'source' => $stepTwoId,
                    'target' => $stepFourId,
                    'type' => 'straight',
                ];
            }

            if ($stepThreeId !== '' && $stepFourId !== '') {
                $edges[] = [
                    'id' => "edge-{$stepThreeId}-{$stepFourId}",
                    'source' => $stepThreeId,
                    'target' => $stepFourId,
                    'type' => 'straight',
                ];
            }
        } else {
            $nodes = array_merge($nodes, $stepNodes);

            if ($stepNodes !== []) {
                $edges[] = [
                    'id' => "edge-start-{$stepNodes[0]['id']}",
                    'source' => 'start',
                    'target' => $stepNodes[0]['id'],
                    'type' => 'straight',
                ];
            }

            for ($i = 0; $i < count($stepNodes) - 1; $i++) {
                $sourceId = $stepNodes[$i]['id'];
                $targetId = $stepNodes[$i + 1]['id'];

                $edges[] = [
                    'id' => "edge-{$sourceId}-{$targetId}",
                    'source' => $sourceId,
                    'target' => $targetId,
                    'type' => 'straight',
                ];
            }
        }

        return [
            'layout' => $hasBranching ? 'parallel' : 'sequential',
            'source' => 'seed:demo',
            'variant' => [
                'name' => $hasBranching ? 'parallel_review_lane' : 'sequential_review_lane',
                'index' => $formSequence,
            ],
            'routing' => [
                'rejection' => [
                    'target' => 'first_step',
                    'allow_revision' => true,
                ],
                'join_strategy' => $hasBranching ? 'all_branches_required' : 'linear',
            ],
            'notifications' => [
                'channels' => ['in_app', 'email'],
                'pending_reminder_hours' => $hasBranching ? 12 : 24,
                'escalation_after_hours' => $hasBranching ? 36 : 48,
            ],
            'service_level' => [
                'target_hours' => $hasBranching ? 72 : 48,
                'priority' => $hasBranching ? 'coordinated' : 'standard',
            ],
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
