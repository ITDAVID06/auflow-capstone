<?php

namespace Tests\Feature;

use App\Mail\SubmissionReminderMail;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepApprover;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendApprovalRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_command_sends_reminders_to_all_step_approvers(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-rem', 'creator-rem@test.com');
        $approverOne = $this->createUser('approver-a', 'approver-a@test.com');
        $approverTwo = $this->createUser('approver-b', 'approver-b@test.com');

        $form = Form::create([
            'form_name' => 'Reminder Form',
            'form_code' => 'REMINDER_FORM',
            'description' => 'Reminder test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Reminder Workflow',
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
            'assigned_account_id' => null,
            'step_conditions' => ['reminder_interval' => '1hour'],
        ]);

        WorkflowStepApprover::create([
            'step_id' => $step->id,
            'account_id' => $approverOne->account_id,
            'condition' => 'primary',
            'order' => 0,
        ]);

        WorkflowStepApprover::create([
            'step_id' => $step->id,
            'account_id' => $approverTwo->account_id,
            'condition' => 'or',
            'order' => 1,
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3301,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approverOne->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinutes(70),
        ]);

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertQueued(SubmissionReminderMail::class, 2);
        Mail::assertQueued(SubmissionReminderMail::class, fn (SubmissionReminderMail $mail) => $mail->hasTo($approverOne->email));
        Mail::assertQueued(SubmissionReminderMail::class, fn (SubmissionReminderMail $mail) => $mail->hasTo($approverTwo->email));

        $this->assertSame(1, (int) $progress->fresh()->reminder_count);
    }

    public function test_command_skips_when_reminders_are_disabled(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-none', 'creator-none@test.com');
        $approver = $this->createUser('approver-none', 'approver-none@test.com');

        $form = Form::create([
            'form_name' => 'No Reminder Form',
            'form_code' => 'NO_REMINDER_FORM',
            'description' => 'No reminder test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'No Reminder Workflow',
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
            'step_conditions' => ['reminder_interval' => 'none'],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3302,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now()->subDays(3),
        ]);

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertSame(0, (int) $progress->fresh()->reminder_count);
    }

    public function test_command_uses_custom_reminder_mode_value_and_unit(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-custom-rem', 'creator-custom-rem@test.com');
        $approver = $this->createUser('approver-custom-rem', 'approver-custom-rem@test.com');

        $form = Form::create([
            'form_name' => 'Custom Reminder Form',
            'form_code' => 'CUSTOM_REMINDER_FORM',
            'description' => 'Custom reminder test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Custom Reminder Workflow',
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
            'step_conditions' => [
                'reminder_mode' => 'custom',
                'reminder_value' => 2,
                'reminder_unit' => 'hours',
            ],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3303,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now()->subHours(3),
        ]);

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertQueued(SubmissionReminderMail::class, 1);
        Mail::assertQueued(SubmissionReminderMail::class, fn (SubmissionReminderMail $mail) => $mail->hasTo($approver->email));
        $this->assertSame(1, (int) $progress->fresh()->reminder_count);
    }

    public function test_command_prioritizes_custom_ten_minute_interval_over_workflow_default(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-custom-10m', 'creator-custom-10m@test.com');
        $approver = $this->createUser('approver-custom-10m', 'approver-custom-10m@test.com');

        $form = Form::create([
            'form_name' => 'Custom Ten Minute Reminder Form',
            'form_code' => 'CUSTOM_TEN_MIN_FORM',
            'description' => 'Custom ten minute reminder test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Custom Ten Minute Reminder Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => [
                'nodes' => [],
                'edges' => [],
                // Keep a long default to prove step-level custom interval wins.
                'reminder_default_interval' => '1d',
            ],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Approval Step',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver->account_id,
            'step_conditions' => [
                'reminder_mode' => 'custom',
                'reminder_interval' => '10min',
                'reminder_value' => 10,
                'reminder_unit' => 'minutes',
            ],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3310,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinutes(11),
        ]);

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertQueued(SubmissionReminderMail::class, 1);
        Mail::assertQueued(SubmissionReminderMail::class, fn (SubmissionReminderMail $mail) => $mail->hasTo($approver->email));
        $this->assertSame(1, (int) $progress->fresh()->reminder_count);
    }

    public function test_command_accepts_spaced_minute_interval_format(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-spaced-min', 'creator-spaced-min@test.com');
        $approver = $this->createUser('approver-spaced-min', 'approver-spaced-min@test.com');

        $form = Form::create([
            'form_name' => 'Spaced Minute Reminder Form',
            'form_code' => 'SPACED_MIN_REMINDER_FORM',
            'description' => 'Spaced minute interval parsing test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Spaced Minute Reminder Workflow',
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
            'step_conditions' => [
                'reminder_mode' => 'default',
                'reminder_interval' => '10 minutes',
            ],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3311,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinutes(11),
        ]);

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertQueued(SubmissionReminderMail::class, 1);
        Mail::assertQueued(SubmissionReminderMail::class, fn (SubmissionReminderMail $mail) => $mail->hasTo($approver->email));
        $this->assertSame(1, (int) $progress->fresh()->reminder_count);
    }

    public function test_command_uses_workflow_default_interval_when_step_uses_default_mode(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-wf-def-rem', 'creator-wf-def-rem@test.com');
        $approver = $this->createUser('approver-wf-def-rem', 'approver-wf-def-rem@test.com');

        $form = Form::create([
            'form_name' => 'Workflow Default Reminder Form',
            'form_code' => 'WORKFLOW_DEFAULT_REMINDER_FORM',
            'description' => 'Workflow default reminder test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Workflow Default Reminder Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => [
                'nodes' => [],
                'edges' => [],
                'reminder_default_interval' => '6h',
            ],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Approval Step',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver->account_id,
            'step_conditions' => ['reminder_interval' => 'default'],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3304,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now()->subHours(7),
        ]);

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertQueued(SubmissionReminderMail::class, 1);
        Mail::assertQueued(SubmissionReminderMail::class, fn (SubmissionReminderMail $mail) => $mail->hasTo($approver->email));
        $this->assertSame(1, (int) $progress->fresh()->reminder_count);
    }

    public function test_command_does_not_send_more_than_three_total_reminders(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-cap-rem', 'creator-cap-rem@test.com');
        $approver = $this->createUser('approver-cap-rem', 'approver-cap-rem@test.com');

        $form = Form::create([
            'form_name' => 'Cap Reminder Form',
            'form_code' => 'CAP_REMINDER_FORM',
            'description' => 'Cap reminder test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Cap Reminder Workflow',
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
            'step_conditions' => ['reminder_interval' => '1day'],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3305,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now()->subDays(30),
        ]);

        $progress->forceFill([
            'reminder_count' => 3,
            'last_reminder_at' => now()->subDays(1),
        ])->save();

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertSame(3, (int) $progress->fresh()->reminder_count);
    }

    public function test_command_sends_next_reminder_when_threshold_is_met_even_if_last_send_was_recent(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-gap-rem', 'creator-gap-rem@test.com');
        $approver = $this->createUser('approver-gap-rem', 'approver-gap-rem@test.com');

        $form = Form::create([
            'form_name' => 'Gap Reminder Form',
            'form_code' => 'GAP_REMINDER_FORM',
            'description' => 'Gap reminder test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Gap Reminder Workflow',
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
            'step_conditions' => ['reminder_interval' => '1day'],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3306,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now()->subDays(10),
        ]);

        $progress->forceFill([
            'reminder_count' => 1,
            // Last send was recent, but threshold from started_at is already satisfied.
            'last_reminder_at' => now()->subHours(2),
        ])->save();

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertQueued(SubmissionReminderMail::class, 1);
        $this->assertSame(2, (int) $progress->fresh()->reminder_count);
    }

    public function test_command_does_not_delay_third_custom_reminder_when_second_was_sent_late(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-third-rem', 'creator-third-rem@test.com');
        $approver = $this->createUser('approver-third-rem', 'approver-third-rem@test.com');

        $form = Form::create([
            'form_name' => 'Third Reminder Form',
            'form_code' => 'THIRD_REMINDER_FORM',
            'description' => 'Third reminder timing test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Third Reminder Workflow',
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
            'step_conditions' => [
                'reminder_mode' => 'custom',
                'reminder_interval' => '2min',
                'reminder_value' => 2,
                'reminder_unit' => 'minutes',
            ],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3312,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            // At ~6 minutes age, third reminder threshold is already met.
            'started_at' => now()->subMinutes(6)->subSeconds(10),
        ]);

        $progress->forceFill([
            'reminder_count' => 2,
            // Simulate a late second reminder send close to now.
            'last_reminder_at' => now()->subSeconds(70),
        ])->save();

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertQueued(SubmissionReminderMail::class, 1);
        $this->assertSame(3, (int) $progress->fresh()->reminder_count);
    }

    public function test_command_sends_second_reminder_after_next_threshold_and_gap(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-second-rem', 'creator-second-rem@test.com');
        $approver = $this->createUser('approver-second-rem', 'approver-second-rem@test.com');

        $form = Form::create([
            'form_name' => 'Second Reminder Form',
            'form_code' => 'SECOND_REMINDER_FORM',
            'description' => 'Second reminder test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Second Reminder Workflow',
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
            'step_conditions' => ['reminder_interval' => '1day'],
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 3307,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $approver->account_id,
            'status' => 'Pending',
            'started_at' => now()->subDays(3),
        ]);

        $progress->forceFill([
            'reminder_count' => 1,
            'last_reminder_at' => now()->subHours(30),
        ])->save();

        $this->artisan('workflow:send-approval-reminders')->assertSuccessful();

        Mail::assertQueued(SubmissionReminderMail::class, 1);
        $this->assertSame(2, (int) $progress->fresh()->reminder_count);
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
