<?php

namespace Tests\Feature;

use App\Mail\SubmissionPendingMail;
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
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowApprovalEmailFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_approving_step_notifies_next_sequential_approver(): void
    {
        Mail::fake();

        $creator = $this->createUser('wf_creator_mail', 'wf_creator_mail@test.com');
        $approverOne = $this->createUser('wf_approver_one', 'wf_approver_one@test.com');
        $approverTwo = $this->createUser('wf_approver_two', 'wf_approver_two@test.com');

        $form = Form::create([
            'form_name' => 'Sequential Email Flow Form',
            'form_code' => 'SEQ_MAIL_'.uniqid(),
            'description' => 'Sequential flow email test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Sequential Email Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $stepOne = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'First Approver',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverOne->account_id,
        ]);

        $stepTwo = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Second Approver',
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverTwo->account_id,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $approverOne->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $firstProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepOne->id,
            'actor_id' => $approverOne->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepTwo->id,
            'actor_id' => $approverTwo->account_id,
            'status' => 'Waiting',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        app(StaffSubmissionService::class)->approveStep($firstProgress->id, (int) $approverOne->account_id);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'submission_id' => $submission->id,
            'step_id' => $stepTwo->id,
            'status' => 'Pending',
        ]);

        Mail::assertSent(SubmissionPendingMail::class, 1);
        Mail::assertSent(SubmissionPendingMail::class, function (SubmissionPendingMail $mail) use ($approverTwo, $stepTwo, $submission) {
            return $mail->hasTo($approverTwo->email)
                && $mail->step->is($stepTwo)
                && $mail->submissionId === $submission->id;
        });
    }

    public function test_rejecting_step_does_not_notify_remaining_approvers(): void
    {
        Mail::fake();

        $creator = $this->createUser('wf_creator_reject', 'wf_creator_reject@test.com');
        $approverOne = $this->createUser('wf_reject_one', 'wf_reject_one@test.com');
        $approverTwo = $this->createUser('wf_reject_two', 'wf_reject_two@test.com');

        $form = Form::create([
            'form_name' => 'Reject Flow Form',
            'form_code' => 'REJ_MAIL_'.uniqid(),
            'description' => 'Reject flow email test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Reject Email Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $stepOne = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Reject Step One',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverOne->account_id,
        ]);

        $stepTwo = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Reject Step Two',
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverTwo->account_id,
        ]);

        $rejSubmission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $approverOne->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $rejSubmission->forceFill(['root_submission_id' => $rejSubmission->id])->save();

        $firstProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $rejSubmission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepOne->id,
            'actor_id' => $approverOne->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinutes(10),
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $rejSubmission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepTwo->id,
            'actor_id' => $approverTwo->account_id,
            'status' => 'Waiting',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        app(StaffSubmissionService::class)->rejectStep($firstProgress->id, (int) $approverOne->account_id, 'Rejected by first approver');

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'submission_id' => $rejSubmission->id,
            'step_id' => $stepTwo->id,
            'status' => 'Rejected',
        ]);

        Mail::assertNotSent(SubmissionPendingMail::class);
    }

    private function createUser(string $username, string $email): User
    {
        return User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
    }
}
