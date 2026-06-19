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

class WorkflowPublishVersionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_publish_creates_version_record_and_increments_workflow_version(): void
    {
        $creator = User::create([
            'username' => 'wf_version_creator',
            'email' => 'wf_version_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $approver = User::create([
            'username' => 'wf_version_approver',
            'email' => 'wf_version_approver@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Workflow Version Form',
            'form_code' => 'WFV_'.uniqid(),
            'description' => null,
            'version' => 1,
            'status' => 'Inactive',
            'created_by' => $creator->account_id,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Workflow Version Snapshot',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Initial Approval',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver->account_id,
            'step_conditions' => [
                'position' => ['id' => 'step-1'],
            ],
        ]);

        $this->actingAs($creator);

        $versionId = app(WorkflowService::class)->publishWorkflow($workflow->id);

        $workflow->refresh();
        $this->assertSame(2, (int) $workflow->version);

        $version = WorkflowVersion::query()->findOrFail($versionId);
        $this->assertSame((int) $workflow->id, (int) $version->workflow_id);
        $this->assertSame(2, (int) $version->version_number);
        $this->assertNotEmpty($version->steps_snapshot);
        $this->assertSame('Initial Approval', $version->steps_snapshot[0]['step_name'] ?? null);
    }
}
