<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use App\Modules\WorkflowBuilder\Services\WorkflowStepService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkflowPublishGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_publish_rejects_zero_step_workflow(): void
    {
        $creator = User::create([
            'username' => 'guard_creator',
            'email' => 'guard_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Guard Test Form',
            'form_code' => 'GUARD_'.uniqid(),
            'description' => null,
            'version' => 1,
            'status' => 'Inactive',
            'created_by' => $creator->account_id,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Zero Step Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $this->actingAs($creator);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot publish a workflow with no steps');

        app(WorkflowService::class)->publishWorkflow($workflow->id);
    }

    public function test_task_node_type_maps_to_noted_action_type(): void
    {
        $creator = User::create([
            'username' => 'task_node_creator',
            'email' => 'task_node_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Task Node Workflow',
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
                    'id' => 'start',
                    'type' => 'start',
                    'position' => ['x' => 80, 'y' => 120],
                    'data' => ['label' => 'Form Submitted', 'type' => 'form_submitted'],
                ],
                [
                    'id' => 'step-task',
                    'type' => 'step',
                    'position' => ['x' => 300, 'y' => 120],
                    'data' => ['label' => 'Do a Task', 'type' => 'task'],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start', 'target' => 'step-task'],
            ],
        ];

        app(WorkflowStepService::class)->buildStepsFromCanvas($workflow, $canvas);

        $step = WorkflowStep::where('workflow_id', $workflow->id)->first();
        $this->assertNotNull($step);
        $this->assertSame('Noted', $step->action_type);
    }
}
