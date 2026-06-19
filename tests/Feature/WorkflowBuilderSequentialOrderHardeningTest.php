<?php

namespace Tests\Feature;

use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use App\Modules\WorkflowBuilder\Services\WorkflowStepService;
use App\Modules\WorkflowBuilder\Services\WorkflowValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class WorkflowBuilderSequentialOrderHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_sequential_steps_are_persisted_in_graph_order_not_payload_order(): void
    {
        $creator = User::create([
            'username' => 'wf_seq_creator',
            'email' => 'wf_seq_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Sequential Hardening Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => null,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $creator->account_id,
            'workflow_settings' => null,
        ]);

        $canvas = [
            'nodes' => [
                [
                    'id' => 'step-c',
                    'type' => 'step',
                    'position' => ['x' => 600, 'y' => 120],
                    'data' => ['label' => 'Step C', 'step_name' => 'Step C', 'type' => 'approval'],
                ],
                [
                    'id' => 'start',
                    'type' => 'start',
                    'position' => ['x' => 80, 'y' => 120],
                    'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
                ],
                [
                    'id' => 'step-b',
                    'type' => 'step',
                    'position' => ['x' => 400, 'y' => 120],
                    'data' => ['label' => 'Step B', 'step_name' => 'Step B', 'type' => 'approval'],
                ],
                [
                    'id' => 'step-a',
                    'type' => 'step',
                    'position' => ['x' => 200, 'y' => 120],
                    'data' => ['label' => 'Step A', 'step_name' => 'Step A', 'type' => 'approval'],
                ],
            ],
            'edges' => [
                ['id' => 'e-start-a', 'source' => 'start', 'target' => 'step-a'],
                ['id' => 'e-a-b', 'source' => 'step-a', 'target' => 'step-b'],
                ['id' => 'e-b-c', 'source' => 'step-b', 'target' => 'step-c'],
            ],
        ];

        app(WorkflowStepService::class)->buildStepsFromCanvas($workflow, $canvas);

        $steps = WorkflowStep::where('workflow_id', $workflow->id)
            ->orderBy('step_order')
            ->get();

        $this->assertCount(3, $steps);
        $this->assertSame(['Step A', 'Step B', 'Step C'], $steps->pluck('step_name')->all());
        $this->assertSame([1, 2, 3], $steps->pluck('step_group')->map(fn ($group) => (int) $group)->all());
        $this->assertDatabaseMissing('tbl_workflow_step', [
            'workflow_id' => $workflow->id,
            'step_name' => 'Form Submitted',
        ]);
    }

    public function test_sequential_validation_rejects_branching_paths(): void
    {
        $nodes = [
            [
                'id' => 'start',
                'type' => 'start',
                'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
            ],
            [
                'id' => 'step-a',
                'type' => 'step',
                'data' => ['label' => 'Step A', 'type' => 'approval', 'assigned_account_id' => 1],
            ],
            [
                'id' => 'step-b',
                'type' => 'step',
                'data' => ['label' => 'Step B', 'type' => 'approval', 'assigned_account_id' => 1],
            ],
            [
                'id' => 'step-c',
                'type' => 'step',
                'data' => ['label' => 'Step C', 'type' => 'approval', 'assigned_account_id' => 1],
            ],
        ];

        $edges = [
            ['source' => 'start', 'target' => 'step-a'],
            ['source' => 'step-a', 'target' => 'step-b'],
            ['source' => 'step-a', 'target' => 'step-c'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple outgoing paths');

        app(WorkflowValidationService::class)->validate($nodes, $edges, 'Sequential');
    }

    public function test_sequential_validation_rejects_merged_paths(): void
    {
        $nodes = [
            [
                'id' => 'start',
                'type' => 'start',
                'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
            ],
            [
                'id' => 'step-a',
                'type' => 'step',
                'data' => ['label' => 'Step A', 'type' => 'approval', 'assigned_account_id' => 1],
            ],
            [
                'id' => 'step-b',
                'type' => 'step',
                'data' => ['label' => 'Step B', 'type' => 'approval', 'assigned_account_id' => 1],
            ],
            [
                'id' => 'step-c',
                'type' => 'step',
                'data' => ['label' => 'Step C', 'type' => 'approval', 'assigned_account_id' => 1],
            ],
        ];

        $edges = [
            ['source' => 'start', 'target' => 'step-a'],
            ['source' => 'start', 'target' => 'step-b'],
            ['source' => 'step-a', 'target' => 'step-c'],
            ['source' => 'step-b', 'target' => 'step-c'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not branch from Form Submitted');

        app(WorkflowValidationService::class)->validate($nodes, $edges, 'Sequential');
    }

    public function test_validation_ignores_ui_helper_end_node_edges(): void
    {
        $nodes = [
            [
                'id' => 'start',
                'type' => 'start',
                'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
            ],
            [
                'id' => 'step-a',
                'type' => 'step',
                'data' => ['label' => 'Step A', 'type' => 'approval', 'assigned_account_id' => 1],
            ],
        ];

        $edges = [
            ['source' => 'start', 'target' => 'step-a'],
            ['source' => 'step-a', 'target' => '__end__'],
        ];

        app(WorkflowValidationService::class)->validate($nodes, $edges, 'Sequential');

        $this->assertTrue(true);
    }

    public function test_parallel_branching_node_does_not_require_an_assignee_or_persist_as_step(): void
    {
        $creator = User::create([
            'username' => 'wf_branch_creator',
            'email' => 'wf_branch_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Parallel Branch Workflow',
            'workflow_type' => 'Parallel',
            'form_id' => null,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $creator->account_id,
            'workflow_settings' => null,
        ]);

        $canvas = [
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'start',
                    'position' => ['x' => 80, 'y' => 120],
                    'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
                ],
                [
                    'id' => 'branch-1',
                    'type' => 'step',
                    'position' => ['x' => 280, 'y' => 120],
                    'data' => ['label' => 'Branch', 'step_name' => 'Branch', 'type' => 'branching'],
                ],
                [
                    'id' => 'step-a',
                    'type' => 'step',
                    'position' => ['x' => 500, 'y' => 60],
                    'data' => ['label' => 'Step A', 'step_name' => 'Step A', 'type' => 'notification'],
                ],
            ],
            'edges' => [
                ['source' => 'start', 'target' => 'branch-1'],
                ['source' => 'branch-1', 'target' => 'step-a'],
            ],
        ];

        app(WorkflowStepService::class)->buildStepsFromCanvas($workflow, $canvas);

        $this->assertSame(['Step A'], WorkflowStep::where('workflow_id', $workflow->id)->orderBy('step_order')->pluck('step_name')->all());
    }

    public function test_parallel_branch_container_groups_inner_steps_concurrently_and_preserves_outer_sequence(): void
    {
        $creator = User::create([
            'username' => 'wf_parallel_branch_creator',
            'email' => 'wf_parallel_branch_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Parallel Branch Grouping Workflow',
            'workflow_type' => 'Parallel',
            'form_id' => null,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $creator->account_id,
            'workflow_settings' => null,
        ]);

        $canvas = [
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'start',
                    'position' => ['x' => 60, 'y' => 220],
                    'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
                ],
                [
                    'id' => 'step-carlo',
                    'type' => 'step',
                    'position' => ['x' => 260, 'y' => 220],
                    'data' => ['label' => 'Carlo', 'step_name' => 'Carlo', 'type' => 'approval'],
                ],
                [
                    'id' => 'branch-1',
                    'type' => 'branchContainer',
                    'position' => ['x' => 500, 'y' => 180],
                    'data' => ['label' => 'Branch', 'step_name' => 'Branch', 'type' => 'branching'],
                ],
                [
                    'id' => 'step-diana',
                    'type' => 'step',
                    'position' => ['x' => 740, 'y' => 170],
                    'data' => ['label' => 'Diana', 'step_name' => 'Diana', 'type' => 'approval'],
                ],
                [
                    'id' => 'step-erwin',
                    'type' => 'step',
                    'position' => ['x' => 740, 'y' => 280],
                    'data' => ['label' => 'Erwin', 'step_name' => 'Erwin', 'type' => 'approval'],
                ],
                [
                    'id' => 'step-juan',
                    'type' => 'step',
                    'position' => ['x' => 980, 'y' => 220],
                    'data' => ['label' => 'Juan', 'step_name' => 'Juan', 'type' => 'approval'],
                ],
            ],
            'edges' => [
                ['source' => 'start', 'target' => 'step-carlo'],
                ['source' => 'step-carlo', 'target' => 'branch-1'],
                ['source' => 'branch-1', 'target' => 'step-diana'],
                ['source' => 'branch-1', 'target' => 'step-erwin'],
                ['source' => 'step-diana', 'target' => 'step-juan'],
                ['source' => 'step-erwin', 'target' => 'step-juan'],
            ],
        ];

        app(WorkflowStepService::class)->buildStepsFromCanvas($workflow, $canvas);

        $steps = WorkflowStep::where('workflow_id', $workflow->id)
            ->orderBy('step_order')
            ->get(['step_name', 'step_group']);

        $this->assertSame(['Carlo', 'Diana', 'Erwin', 'Juan'], $steps->pluck('step_name')->all());
        $this->assertSame([1, 2, 2, 3], $steps->pluck('step_group')->map(fn ($group) => (int) $group)->all());
    }

    public function test_parallel_branch_container_edges_expand_to_parented_child_steps_for_reachability(): void
    {
        $creator = User::create([
            'username' => 'wf_parallel_parented_children_creator',
            'email' => 'wf_parallel_parented_children_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Parallel Branch Container Parented Children Workflow',
            'workflow_type' => 'Parallel',
            'form_id' => null,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $creator->account_id,
            'workflow_settings' => null,
        ]);

        $canvas = [
            'nodes' => [
                [
                    'id' => 'start',
                    'type' => 'start',
                    'position' => ['x' => 60, 'y' => 220],
                    'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
                ],
                [
                    'id' => 'step-carlo',
                    'type' => 'step',
                    'position' => ['x' => 260, 'y' => 220],
                    'data' => ['label' => 'Carlo', 'step_name' => 'Carlo', 'type' => 'approval'],
                ],
                [
                    'id' => 'branch-1',
                    'type' => 'branchContainer',
                    'position' => ['x' => 500, 'y' => 180],
                    'data' => ['label' => 'Branch', 'step_name' => 'Branch', 'type' => 'branching'],
                ],
                [
                    'id' => 'step-diana',
                    'type' => 'step',
                    'parentNode' => 'branch-1',
                    'position' => ['x' => 24, 'y' => 60],
                    'data' => ['label' => 'Diana', 'step_name' => 'Diana', 'type' => 'approval'],
                ],
                [
                    'id' => 'step-erwin',
                    'type' => 'step',
                    'parentNode' => 'branch-1',
                    'position' => ['x' => 24, 'y' => 170],
                    'data' => ['label' => 'Erwin', 'step_name' => 'Erwin', 'type' => 'approval'],
                ],
                [
                    'id' => 'step-juan',
                    'type' => 'step',
                    'position' => ['x' => 980, 'y' => 220],
                    'data' => ['label' => 'Juan', 'step_name' => 'Juan', 'type' => 'approval'],
                ],
            ],
            'edges' => [
                ['source' => 'start', 'target' => 'step-carlo'],
                ['source' => 'step-carlo', 'target' => 'branch-1'],
                ['source' => 'branch-1', 'target' => 'step-juan'],
            ],
        ];

        app(WorkflowStepService::class)->buildStepsFromCanvas($workflow, $canvas);

        $steps = WorkflowStep::where('workflow_id', $workflow->id)
            ->orderBy('step_order')
            ->get(['step_name', 'step_group']);

        $this->assertSame(['Carlo', 'Diana', 'Erwin', 'Juan'], $steps->pluck('step_name')->all());
        $this->assertSame([1, 2, 2, 3], $steps->pluck('step_group')->map(fn ($group) => (int) $group)->all());
    }

    public function test_create_workflow_wraps_graph_validation_error_as_validation_exception(): void
    {
        $creator = User::create([
            'username' => 'wf_invalid_graph_creator',
            'email' => 'wf_invalid_graph_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $payload = [
            'workflow_name' => 'Invalid Graph Workflow',
            'workflow_type' => 'Sequential',
            'description' => null,
            'form_id' => null,
            'status' => 'draft',
            'created_by' => $creator->account_id,
            'workflow_settings' => [
                'nodes' => [
                    [
                        'id' => 'start',
                        'type' => 'start',
                        'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
                    ],
                    [
                        'id' => 'step-a',
                        'type' => 'step',
                        'data' => ['label' => 'Step A', 'type' => 'approval', 'assigned_account_id' => 1],
                    ],
                ],
                'edges' => [
                    ['source' => 'start', 'target' => 'step-a'],
                    ['source' => 'step-a', 'target' => 'missing-node'],
                ],
            ],
        ];

        try {
            app(WorkflowService::class)->createWorkflow($payload);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $this->assertArrayHasKey('workflow_settings', $errors);
            $this->assertStringContainsString('Edge references unknown node(s)', $errors['workflow_settings'][0]);
        }
    }

    public function test_duplicate_workflow_keeps_start_node_canonical_and_remaps_saved_canvas_ids(): void
    {
        $creator = User::create([
            'username' => 'wf_duplicate_creator',
            'email' => 'wf_duplicate_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Original Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => null,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $creator->account_id,
            'workflow_settings' => [
                'nodes' => [
                    [
                        'id' => 'start',
                        'type' => 'start',
                        'position' => ['x' => 80, 'y' => 120],
                        'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
                    ],
                    [
                        'id' => 'step-a',
                        'type' => 'step',
                        'position' => ['x' => 220, 'y' => 120],
                        'data' => ['label' => 'Step A', 'step_name' => 'Step A', 'type' => 'approval', 'assigned_account_id' => 1],
                    ],
                ],
                'edges' => [
                    ['id' => 'edge-start-step-a', 'source' => 'start', 'target' => 'step-a'],
                ],
                'stepOrder' => ['step-a'],
            ],
        ]);

        $copy = app(WorkflowService::class)->duplicateWorkflow($workflow->id);
        $settings = $copy->workflow_settings;

        $this->assertNotNull($settings);
        $this->assertSame('start', collect($settings['nodes'])->firstWhere('data.type', 'form_submitted')['id'] ?? null);

        $nodeIds = collect($settings['nodes'])->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->assertContains('start', $nodeIds);

        foreach ($settings['edges'] as $edge) {
            $this->assertContains((string) $edge['source'], $nodeIds);
            $this->assertContains((string) $edge['target'], $nodeIds);
        }

        foreach ($settings['stepOrder'] as $stepId) {
            $this->assertContains((string) $stepId, $nodeIds);
        }

        app(WorkflowValidationService::class)->validate($settings['nodes'], $settings['edges'], 'Sequential');
    }
}
