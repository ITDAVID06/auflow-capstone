<?php

namespace Tests\Feature;

use App\Mail\SubmissionCompletedMail;
use App\Mail\SubmissionPendingMail;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowEmailRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_sequential_notifier_emails_only_current_pending_group(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator', 'creator@test.com');
        $approverOne = $this->createUser('approver1', 'approver1@test.com');
        $approverTwo = $this->createUser('approver2', 'approver2@test.com');

        $form = Form::create([
            'form_name' => 'Leave Form',
            'form_code' => 'LEAVE_FORM',
            'description' => 'Test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Leave Approval',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $stepOne = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'First Approval',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverOne->account_id,
            'step_conditions' => ['reminder_interval' => 'default'],
        ]);

        $stepTwo = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Second Approval',
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverTwo->account_id,
            'step_conditions' => ['reminder_interval' => 'default'],
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 1001,
            'workflow_id' => $workflow->id,
            'step_id' => $stepOne->id,
            'actor_id' => $approverOne->account_id,
            'status' => 'Skipped',
        ]);

        $pendingProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 1001,
            'workflow_id' => $workflow->id,
            'step_id' => $stepTwo->id,
            'actor_id' => $approverTwo->account_id,
            'status' => 'Pending',
        ]);

        app(NotificationService::class)->notifyFirstSequentialApprovers($workflow, 1001, $form);

        Mail::assertSent(SubmissionPendingMail::class, 1);
        Mail::assertSent(SubmissionPendingMail::class, function (SubmissionPendingMail $mail) use ($approverTwo, $stepTwo) {
            return $mail->hasTo($approverTwo->email)
                && $mail->step->is($stepTwo)
                && $mail->submissionId === 1001;
        });

        Mail::assertSent(SubmissionPendingMail::class, function (SubmissionPendingMail $mail) use ($pendingProgress) {
            return $mail->progressId === $pendingProgress->id;
        });
    }

    public function test_parallel_notifier_emails_only_pending_steps(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator2', 'creator2@test.com');
        $approverOne = $this->createUser('parallel1', 'parallel1@test.com');
        $approverTwo = $this->createUser('parallel2', 'parallel2@test.com');

        $form = Form::create([
            'form_name' => 'Travel Form',
            'form_code' => 'TRAVEL_FORM',
            'description' => 'Test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Travel Approval',
            'workflow_type' => 'Parallel',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $pendingStep = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Parallel A',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverOne->account_id,
            'step_conditions' => ['reminder_interval' => 'default'],
        ]);

        $waitingStep = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Parallel B',
            'step_order' => 2,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverTwo->account_id,
            'step_conditions' => ['reminder_interval' => 'default'],
        ]);

        $pendingProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 2002,
            'workflow_id' => $workflow->id,
            'step_id' => $pendingStep->id,
            'actor_id' => $approverOne->account_id,
            'status' => 'Pending',
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 2002,
            'workflow_id' => $workflow->id,
            'step_id' => $waitingStep->id,
            'actor_id' => $approverTwo->account_id,
            'status' => 'Waiting',
        ]);

        app(NotificationService::class)->notifyAllParallelApprovers($workflow, 2002, $form);

        Mail::assertSent(SubmissionPendingMail::class, 1);
        Mail::assertSent(SubmissionPendingMail::class, function (SubmissionPendingMail $mail) use ($approverOne, $pendingStep) {
            return $mail->hasTo($approverOne->email)
                && $mail->step->is($pendingStep)
                && $mail->submissionId === 2002;
        });

        Mail::assertSent(SubmissionPendingMail::class, function (SubmissionPendingMail $mail) use ($pendingProgress) {
            return $mail->progressId === $pendingProgress->id;
        });
    }

    public function test_completion_notifier_reads_canonical_submission_without_runtime_rows(): void
    {
        Mail::fake();

        $creator = $this->createUser('completion-creator', 'completion-creator@test.com');
        $submitter = $this->createUser('completion-submit', 'completion-submit@test.com');

        DB::table('tbl_userprofile')->insert([
            'account_id' => $submitter->account_id,
            'first_name' => 'Canonical',
            'last_name' => 'Submitter',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $form = Form::create([
            'form_name' => 'Completion Canonical Form',
            'form_code' => 'COMPLETE_CANONICAL',
            'description' => 'Test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Approved',
            'current_workflow_status' => 'Approved',
            'current_step_id' => null,
            'current_actor_id' => null,
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $workflow = Workflow::create([
            'workflow_name' => 'Completion Canonical Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Completion Step',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $creator->account_id,
            'step_conditions' => ['watch_fields' => []],
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $creator->account_id,
            'status' => 'Approved',
            'action_taken' => 'Approved',
            'started_at' => now()->subMinutes(5),
            'acted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        app(NotificationService::class)->notifySubmissionCompletion($workflow, $submission->id, 'approved');

        Mail::assertSent(SubmissionCompletedMail::class, function (SubmissionCompletedMail $mail) use ($submitter, $submission): bool {
            return $mail->hasTo($submitter->email)
                && $mail->submissionId === $submission->id
                && $mail->submitterName === 'Canonical Submitter';
        });

        $this->assertDatabaseHas('tbl_notification', [
            'account_id' => $submitter->account_id,
            'type' => 'submission_approved',
            'related_id' => $submission->id,
        ]);
    }

    public function test_completion_notifier_ignores_unrelated_legacy_rows_when_canonical_submission_is_complete(): void
    {
        Mail::fake();

        $creator = $this->createUser('completion-creator-2', 'completion-creator-2@test.com');
        $submitter = $this->createUser('completion-submit-2', 'completion-submit-2@test.com');
        $otherSubmitter = $this->createUser('completion-other', 'completion-other@test.com');

        DB::table('tbl_userprofile')->insert([
            [
                'account_id' => $submitter->account_id,
                'first_name' => 'Canonical',
                'last_name' => 'Primary',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $otherSubmitter->account_id,
                'first_name' => 'Canonical',
                'last_name' => 'Other',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $form = Form::create([
            'form_name' => 'Completion Filter Form',
            'form_code' => 'COMPLETE_FILTER',
            'description' => 'Canonical completion filter test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Approved',
            'current_workflow_status' => 'Approved',
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $otherSubmission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $otherSubmitter->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $otherSubmission->forceFill(['root_submission_id' => $otherSubmission->id])->save();

        $workflow = Workflow::create([
            'workflow_name' => 'Completion Filter Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Completion Filter Step',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $creator->account_id,
            'step_conditions' => ['watch_fields' => []],
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $creator->account_id,
            'status' => 'Approved',
            'action_taken' => 'Approved',
            'started_at' => now()->subMinutes(5),
            'acted_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $otherSubmission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $creator->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinute(),
        ]);

        app(NotificationService::class)->notifySubmissionCompletion($workflow, $submission->id, 'approved');

        Mail::assertSent(SubmissionCompletedMail::class, function (SubmissionCompletedMail $mail) use ($submitter, $submission): bool {
            return $mail->hasTo($submitter->email)
                && $mail->submissionId === $submission->id
                && $mail->submitterName === 'Canonical Primary';
        });

        Mail::assertSent(SubmissionCompletedMail::class, 1);
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
