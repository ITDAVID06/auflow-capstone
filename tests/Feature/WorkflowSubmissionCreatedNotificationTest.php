<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\Notifications\Models\Notification;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowSubmissionCreatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_submission_created_notification_is_sent_only_once_for_first_group_pending_steps(): void
    {
        $creator = $this->createUser('creator-phase3', 'creator-phase3@test.com');
        $submitter = $this->createUser('submitter-phase3', 'submitter-phase3@test.com');
        $approver = $this->createUser('approver-phase3', 'approver-phase3@test.com');

        $form = Form::create([
            'form_name' => 'Phase 3 Form',
            'form_code' => 'PHASE3_FORM',
            'description' => 'Phase 3 notification test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'current_step_id' => null,
            'current_actor_id' => null,
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $workflow = Workflow::create([
            'workflow_name' => 'Phase 3 Sequential',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $stepOne = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'First A',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver->account_id,
            'step_conditions' => ['watch_fields' => []],
        ]);

        $stepTwo = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'First B',
            'step_order' => 2,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver->account_id,
            'step_conditions' => ['watch_fields' => []],
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 5001,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepOne->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 5001,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepTwo->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now(),
        ]);

        $count = Notification::query()
            ->where('account_id', $submitter->account_id)
            ->where('type', 'submission_created')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_workflow_completion_notification_uses_canonical_submission_without_runtime_rows(): void
    {
        $creator = $this->createUser('creator-phase4', 'creator-phase4@test.com');
        $submitter = $this->createUser('submitter-phase4', 'submitter-phase4@test.com');
        $approver = $this->createUser('approver-phase4', 'approver-phase4@test.com');

        $form = Form::create([
            'form_name' => 'Phase 4 Form',
            'form_code' => 'PHASE4_FORM',
            'description' => 'Phase 4 canonical notification test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'current_step_id' => null,
            'current_actor_id' => null,
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $workflow = Workflow::create([
            'workflow_name' => 'Phase 4 Sequential',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Final Approval',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver->account_id,
            'step_conditions' => ['watch_fields' => []],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 6001,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinute(),
        ]);

        $progress->update([
            'status' => 'Approved',
            'action_taken' => 'Approved',
            'acted_at' => now(),
            'completed_at' => now(),
        ]);

        $this->assertDatabaseHas('tbl_notification', [
            'account_id' => $submitter->account_id,
            'type' => 'workflow_completed',
            'related_id' => $progress->id,
        ]);
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
