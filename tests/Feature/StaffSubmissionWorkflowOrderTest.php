<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\StaffDashboard\Services\StaffDashboardQueryService;
use App\Modules\StaffDashboard\Services\StaffStepReadinessService;
use App\Modules\StaffDashboard\Services\StaffSubmissionDetailsService;
use App\Modules\StaffDashboard\Services\StaffSubmissionService;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffSubmissionWorkflowOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_staff_submission_workflow_is_ordered_by_step_group_and_step_order(): void
    {
        $staff = User::create([
            'username' => 'staff_order_user',
            'email' => 'staff_order_user@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        DB::table('tbl_userprofile')->insert([
            'account_id' => $staff->account_id,
            'first_name' => 'Staff',
            'last_name' => 'Order',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submitter = User::create([
            'username' => 'submitter_order_user',
            'email' => 'submitter_order_user@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        DB::table('tbl_userprofile')->insert([
            'account_id' => $submitter->account_id,
            'first_name' => 'Submitter',
            'last_name' => 'Order',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $form = Form::create([
            'form_name' => 'Order Test Form',
            'form_code' => 'FORM'.uniqid(),
            'description' => 'Workflow order test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $staff->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text_name',
            'label' => 'Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $canonicalSubmission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => ['field_text_name' => 'Order Test'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $canonicalSubmission->forceFill(['root_submission_id' => $canonicalSubmission->id])->save();

        $submissionId = 1001;

        $workflow = Workflow::create([
            'workflow_name' => 'Order Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => 'Order workflow description',
            'status' => 'Active',
            'created_by' => $staff->account_id,
        ]);

        $stepB = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step B',
            'step_order' => 2,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $staff->account_id,
        ]);

        $stepA = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step A',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $staff->account_id,
        ]);

        $stepC = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step C',
            'step_order' => 1,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $staff->account_id,
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $canonicalSubmission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepC->id,
            'actor_id' => $staff->account_id,
            'status' => 'Waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $canonicalSubmission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepB->id,
            'actor_id' => $staff->account_id,
            'status' => 'Waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $progressForAccess = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $canonicalSubmission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepA->id,
            'actor_id' => $staff->account_id,
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(StaffSubmissionDetailsService::class);
        $details = $service->getSubmissionDetailsForStaff($progressForAccess->id, (int) $staff->account_id);

        $orderedStepNames = array_map(fn (array $step) => $step['step'], $details['workflow']);

        $this->assertSame(['Step A', 'Step B', 'Step C'], $orderedStepNames);
    }

    public function test_staff_pending_requests_use_canonical_submissions_without_runtime_rows(): void
    {
        $staff = User::create([
            'username' => 'staff_queue_user',
            'email' => 'staff_queue_user@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        DB::table('tbl_userprofile')->insert([
            'account_id' => $staff->account_id,
            'first_name' => 'Queue',
            'last_name' => 'Staff',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submitter = User::create([
            'username' => 'queue_submitter_user',
            'email' => 'queue_submitter_user@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        DB::table('tbl_userprofile')->insert([
            'account_id' => $submitter->account_id,
            'first_name' => 'Queue',
            'last_name' => 'Submitter',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $form = Form::create([
            'form_name' => 'Queue Test Form',
            'form_code' => 'QUEUE'.uniqid(),
            'description' => 'Queue workflow test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $staff->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text_name',
            'label' => 'Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Queue Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => 'Queue workflow description',
            'status' => 'Active',
            'created_by' => $staff->account_id,
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Queue Review',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $staff->account_id,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'current_step_id' => $step->id,
            'current_actor_id' => $staff->account_id,
            'payload_json' => ['field_text_name' => 'Queue Test'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $staff->account_id,
            'status' => 'Pending',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requests = app(StaffDashboardQueryService::class)->getPendingRequestsForStaff((int) $staff->account_id);

        $this->assertCount(1, $requests);
        $this->assertSame($submission->id, $requests[0]['submission_id']);
        $this->assertSame('Queue Submitter', $requests[0]['submitter']);
        $this->assertSame($form->form_name, $requests[0]['form_name']);
    }

    public function test_staff_readiness_treats_skipped_prior_steps_as_completed(): void
    {
        $staff = User::create([
            'username' => 'staff_ready_user',
            'email' => 'staff_ready_user@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Readiness Skip Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => null,
            'description' => null,
            'status' => 'Active',
            'created_by' => $staff->account_id,
        ]);

        $readinessForm = Form::create([
            'form_name' => 'Readiness Test Form',
            'form_code' => 'READY_'.uniqid(),
            'description' => null,
            'version' => 1,
            'status' => 'Active',
            'created_by' => $staff->account_id,
        ]);

        $firstStep = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 1',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $staff->account_id,
        ]);

        $secondStep = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 2',
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $staff->account_id,
        ]);

        WorkflowStepProgress::create([
            'form_id' => $readinessForm->id,
            'submission_id' => 77,
            'workflow_id' => $workflow->id,
            'step_id' => $firstStep->id,
            'actor_id' => $staff->account_id,
            'status' => 'Skipped',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $readinessForm->id,
            'submission_id' => 77,
            'workflow_id' => $workflow->id,
            'step_id' => $secondStep->id,
            'actor_id' => $staff->account_id,
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $isReady = app(StaffStepReadinessService::class)->isStepReady($secondStep->fresh('workflow'), 77);

        $this->assertTrue($isReady);
    }

    public function test_parallel_grouped_workflow_advances_only_after_current_group_completes(): void
    {
        $approverOne = User::create([
            'username' => 'parallel_group_approver_one',
            'email' => 'parallel_group_approver_one@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $approverTwo = User::create([
            'username' => 'parallel_group_approver_two',
            'email' => 'parallel_group_approver_two@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        DB::table('tbl_userprofile')->insert([
            [
                'account_id' => $approverOne->account_id,
                'first_name' => 'Parallel',
                'last_name' => 'One',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $approverTwo->account_id,
                'first_name' => 'Parallel',
                'last_name' => 'Two',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $form = Form::create([
            'form_name' => 'Parallel Group Form',
            'form_code' => 'PGRP'.uniqid(),
            'description' => 'Parallel grouped progression test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $approverOne->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text_name',
            'label' => 'Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $approverOne->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => ['field_text_name' => 'Parallel Test'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $workflow = Workflow::create([
            'workflow_name' => 'Parallel Group Workflow',
            'workflow_type' => 'Parallel',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $approverOne->account_id,
        ]);

        $stepOne = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step One',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverOne->account_id,
        ]);

        $stepTwo = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step Two',
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverTwo->account_id,
        ]);

        $firstProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepOne->id,
            'actor_id' => $approverOne->account_id,
            'status' => 'Pending',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepTwo->id,
            'actor_id' => $approverTwo->account_id,
            'status' => 'Waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(StaffSubmissionService::class)->approveStep($firstProgress->id, (int) $approverOne->account_id);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'workflow_id' => $workflow->id,
            'submission_id' => $submission->id,
            'step_id' => $stepTwo->id,
            'status' => 'Pending',
        ]);
    }

    public function test_workflow_advances_past_fully_skipped_group_into_next_parallel_group(): void
    {
        $approverOne = User::create([
            'username' => 'skip_group_approver_one',
            'email' => 'skip_group_approver_one@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $approverThree = User::create([
            'username' => 'skip_group_approver_three',
            'email' => 'skip_group_approver_three@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $approverFour = User::create([
            'username' => 'skip_group_approver_four',
            'email' => 'skip_group_approver_four@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        DB::table('tbl_userprofile')->insert([
            [
                'account_id' => $approverOne->account_id,
                'first_name' => 'Skip',
                'last_name' => 'One',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $approverThree->account_id,
                'first_name' => 'Skip',
                'last_name' => 'Three',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $approverFour->account_id,
                'first_name' => 'Skip',
                'last_name' => 'Four',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $form = Form::create([
            'form_name' => 'Skipped Group Cascade Form',
            'form_code' => 'SGCF'.uniqid(),
            'description' => 'Skipped group cascade progression test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $approverOne->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text_name',
            'label' => 'Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $approverOne->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => ['field_text_name' => 'Skip Cascade Test'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $workflow = Workflow::create([
            'workflow_name' => 'Skipped Group Cascade Workflow',
            'workflow_type' => 'Parallel',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $approverOne->account_id,
        ]);

        $stepOne = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step One',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverOne->account_id,
        ]);

        $stepTwoSkipped = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step Two (Skipped)',
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverOne->account_id,
        ]);

        $stepThree = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step Three',
            'step_order' => 3,
            'step_group' => 3,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverThree->account_id,
        ]);

        $stepFour = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step Four',
            'step_order' => 4,
            'step_group' => 3,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverFour->account_id,
        ]);

        $firstProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepOne->id,
            'actor_id' => $approverOne->account_id,
            'status' => 'Pending',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepTwoSkipped->id,
            'actor_id' => $approverOne->account_id,
            'status' => 'Skipped',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepThree->id,
            'actor_id' => $approverThree->account_id,
            'status' => 'Waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepFour->id,
            'actor_id' => $approverFour->account_id,
            'status' => 'Waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(StaffSubmissionService::class)->approveStep($firstProgress->id, (int) $approverOne->account_id);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'workflow_id' => $workflow->id,
            'submission_id' => $submission->id,
            'step_id' => $stepThree->id,
            'status' => 'Pending',
        ]);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'workflow_id' => $workflow->id,
            'submission_id' => $submission->id,
            'step_id' => $stepFour->id,
            'status' => 'Pending',
        ]);
    }

    public function test_unlock_skips_watched_step_when_payload_is_empty_json_object(): void
    {
        $approverOne = User::create([
            'username' => 'skip_json_approver_one',
            'email' => 'skip_json_approver_one@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $approverTwo = User::create([
            'username' => 'skip_json_approver_two',
            'email' => 'skip_json_approver_two@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $approverThree = User::create([
            'username' => 'skip_json_approver_three',
            'email' => 'skip_json_approver_three@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Skip Empty Json Object Form',
            'form_code' => 'SEJOF'.uniqid(),
            'description' => 'Watch-field skip regression for empty json object payload',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $approverOne->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_table_data',
            'label' => 'Table Data',
            'data_type' => 'table',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $approverOne->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => ['field_table_data' => '{}'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $workflow = Workflow::create([
            'workflow_name' => 'Skip Empty Json Object Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $approverOne->account_id,
        ]);

        $stepOne = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step One',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverOne->account_id,
        ]);

        $stepTwoWatched = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step Two Watched',
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverTwo->account_id,
            'step_conditions' => ['watch_fields' => ['field_table_data']],
        ]);

        $stepThree = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step Three',
            'step_order' => 3,
            'step_group' => 3,
            'action_type' => 'Approve',
            'assigned_account_id' => $approverThree->account_id,
        ]);

        $firstProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepOne->id,
            'actor_id' => $approverOne->account_id,
            'status' => 'Pending',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepTwoWatched->id,
            'actor_id' => $approverTwo->account_id,
            'status' => 'Waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $stepThree->id,
            'actor_id' => $approverThree->account_id,
            'status' => 'Waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(StaffSubmissionService::class)->approveStep($firstProgress->id, (int) $approverOne->account_id);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'workflow_id' => $workflow->id,
            'submission_id' => $submission->id,
            'step_id' => $stepTwoWatched->id,
            'status' => 'Skipped',
        ]);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'workflow_id' => $workflow->id,
            'submission_id' => $submission->id,
            'step_id' => $stepThree->id,
            'status' => 'Pending',
        ]);
    }
}
