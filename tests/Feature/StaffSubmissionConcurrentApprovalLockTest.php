<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\StaffDashboard\Services\StaffSubmissionService;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffSubmissionConcurrentApprovalLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_simulated_concurrent_approvals_only_allow_one_success(): void
    {
        $creator = User::create([
            'username' => 'lock_creator',
            'email' => 'lock_creator@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $approver = User::create([
            'username' => 'lock_approver',
            'email' => 'lock_approver@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $submitter = User::create([
            'username' => 'lock_submitter',
            'email' => 'lock_submitter@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Locking Test Form',
            'form_code' => 'LOCK_'.uniqid(),
            'description' => 'Concurrency lock test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'is_locked' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Locking Test Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Approval Step',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver->account_id,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'current_step_id' => $step->id,
            'current_actor_id' => $approver->account_id,
            'payload_json' => ['field_text_name' => 'Lock test'],
            'schema_snapshot_json' => $form->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(StaffSubmissionService::class);

        $first = $service->approveStep($progress->id, (int) $approver->account_id, 'first attempt');
        $this->assertTrue($first['ok']);

        try {
            $service->approveStep($progress->id, (int) $approver->account_id, 'second attempt');
            $this->fail('Second approval attempt should fail for already-processed progress row.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Already processed', $e->getMessage());
        }

        $progress->refresh();
        $this->assertSame('Approved', $progress->status);
        $this->assertSame('first attempt', $progress->comments);
    }
}
