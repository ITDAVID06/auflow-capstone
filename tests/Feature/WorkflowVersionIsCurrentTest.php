<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowVersionIsCurrentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function makeWorkflowWithStep(): array
    {
        $creator = User::create([
            'username' => 'is_current_creator_'.uniqid(),
            'email' => 'is_current_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $approver = User::create([
            'username' => 'is_current_approver_'.uniqid(),
            'email' => 'is_current_approver_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'IsCurrentForm_'.uniqid(),
            'form_code' => 'ISC_'.uniqid(),
            'description' => null,
            'version' => 1,
            'status' => 'Inactive',
            'created_by' => $creator->account_id,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'IsCurrentWorkflow_'.uniqid(),
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Approval Step',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver->account_id,
            'step_conditions' => ['position' => ['id' => 'step-1']],
        ]);

        return [$workflow, $creator];
    }

    public function test_publish_sets_is_current_true_on_new_version(): void
    {
        [$workflow, $creator] = $this->makeWorkflowWithStep();
        $this->actingAs($creator);

        $versionId = app(WorkflowService::class)->publishWorkflow($workflow->id);

        $version = WorkflowVersion::findOrFail($versionId);
        $this->assertTrue((bool) $version->is_current);
    }

    public function test_publish_marks_previous_versions_is_current_false(): void
    {
        [$workflow, $creator] = $this->makeWorkflowWithStep();

        // Seed a pre-existing version (simulating a prior publish) with is_current = true.
        $oldVersion = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'steps_snapshot' => [],
            'published_at' => now()->subDay(),
            'is_current' => true,
        ]);

        $this->actingAs($creator);
        $newVersionId = app(WorkflowService::class)->publishWorkflow($workflow->id);

        $oldVersion->refresh();
        $this->assertFalse((bool) $oldVersion->is_current, 'Old version must have is_current = false after republish');

        $newVersion = WorkflowVersion::findOrFail($newVersionId);
        $this->assertTrue((bool) $newVersion->is_current, 'New version must have is_current = true');
    }

    public function test_is_current_query_finds_published_version(): void
    {
        [$workflow, $creator] = $this->makeWorkflowWithStep();
        $this->actingAs($creator);

        $versionId = app(WorkflowService::class)->publishWorkflow($workflow->id);

        $found = WorkflowVersion::where('workflow_id', $workflow->id)
            ->where('is_current', true)
            ->first();

        $this->assertNotNull($found);
        $this->assertSame($versionId, (int) $found->id);
    }
}
