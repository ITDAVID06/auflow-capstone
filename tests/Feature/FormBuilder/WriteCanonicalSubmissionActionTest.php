<?php

namespace Tests\Feature\FormBuilder;

use App\Actions\FormBuilder\WriteCanonicalSubmissionAction;
use App\Exceptions\SlotUnavailableException;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WriteCanonicalSubmissionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_writes_a_canonical_submission_and_child_records_in_one_transaction(): void
    {
        $submitter = $this->createUser('submitter@example.com');
        $form = $this->createForm($submitter);
        [$workflow, $step] = $this->createWorkflowStep($form, $submitter);

        $submission = app(WriteCanonicalSubmissionAction::class)->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_alpha' => 'value'],
            schemaSnapshot: ['fields' => [['field_name' => 'field_alpha']]],
            currentStepId: (int) $step->id,
            currentActorId: (int) $submitter->account_id,
            submittedAt: '2026-03-11 09:30:00',
            attachmentPayloads: [[
                'submission_id' => 501,
                'file_path' => 'submissions_attachments/example.pdf',
                'original_name' => 'example.pdf',
                'mime_type' => 'application/pdf',
                'uploaded_by' => (int) $submitter->account_id,
            ]],
            slotPayloads: [[
                'submission_id' => 501,
                'facility_id' => null,
                'date' => '2026-03-12',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'Pending',
            ]],
            workflowProgressPayloads: [[
                'submission_id' => 501,
                'workflow_id' => (int) $workflow->id,
                'workflow_version' => 1,
                'step_id' => (int) $step->id,
                'actor_id' => (int) $submitter->account_id,
                'status' => 'Pending',
                'started_at' => '2026-03-11 09:30:00',
                'created_at' => '2026-03-11 09:30:00',
                'updated_at' => '2026-03-11 09:30:00',
            ]],
        );

        $this->assertDatabaseHas('tbl_form_submission', [
            'id' => $submission->id,
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'root_submission_id' => $submission->id,
        ]);

        $this->assertDatabaseHas('tbl_submission_attachment', [
            'submission_id' => $submission->id,
            'file_path' => 'submissions_attachments/example.pdf',
        ]);

        $this->assertDatabaseHas('tbl_slots', [
            'submission_id' => $submission->id,
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
        ]);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
        ]);
    }

    public function test_it_derives_root_submission_id_for_revisions(): void
    {
        $submitter = $this->createUser('revision-submit@example.com');
        $form = $this->createForm($submitter, 'REVISION-FORM');
        $action = app(WriteCanonicalSubmissionAction::class);

        $original = $action->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_alpha' => 'original'],
            schemaSnapshot: ['fields' => []],
        );

        $revision = $action->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_alpha' => 'revision'],
            schemaSnapshot: ['fields' => []],
            revisionOf: (int) $original->id,
        );

        $this->assertSame($original->id, $original->fresh()->root_submission_id);
        $this->assertFalse((bool) $original->fresh()->is_latest_revision);
        $this->assertSame($original->id, $revision->revision_of);
        $this->assertSame($original->id, $revision->root_submission_id);
        $this->assertTrue((bool) $revision->is_latest_revision);
    }

    public function test_it_rolls_back_the_submission_when_a_child_write_fails(): void
    {
        $submitter = $this->createUser('rollback-submit@example.com');
        $form = $this->createForm($submitter, 'ROLLBACK-FORM');

        try {
            app(WriteCanonicalSubmissionAction::class)->execute(
                form: $form,
                accountId: (int) $submitter->account_id,
                payload: ['field_alpha' => 'broken'],
                schemaSnapshot: ['fields' => []],
                slotPayloads: [[
                    'submission_id' => 999,
                    'facility_id' => null,
                    'start_time' => '09:00',
                    'end_time' => '10:00',
                    'status' => 'Pending',
                ]],
            );

            $this->fail('Expected canonical write to fail when a child slot payload omits required columns.');
        } catch (QueryException) {
            $this->assertDatabaseCount('tbl_form_submission', 0);
            $this->assertDatabaseCount('tbl_slots', 0);
        }
    }

    public function test_it_reuses_existing_submission_for_the_same_idempotency_key(): void
    {
        $submitter = $this->createUser('idempotency-submit@example.com');
        $form = $this->createForm($submitter, 'IDEMPOTENT-FORM');
        $action = app(WriteCanonicalSubmissionAction::class);

        $first = $action->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_alpha' => 'first'],
            schemaSnapshot: ['fields' => []],
            idempotencyKey: 'submission:fixed-key',
        );

        $second = $action->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_alpha' => 'second'],
            schemaSnapshot: ['fields' => []],
            idempotencyKey: 'submission:fixed-key',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('tbl_form_submission', 1);
    }

    public function test_it_blocks_conflicting_slot_reservations_before_insert(): void
    {
        $submitter = $this->createUser('slot-submit@example.com');
        $form = $this->createForm($submitter, 'SLOT-FORM');

        $facilityId = DB::table('tbl_facility')->insertGetId([
            'name' => 'Main Hall',
            'description' => 'Primary venue',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tbl_slots')->insert([
            'form_id' => $form->id,
            'submission_id' => 2001,
            'account_id' => $submitter->account_id,
            'facility_id' => $facilityId,
            'date' => '2026-04-15',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(SlotUnavailableException::class);

        app(WriteCanonicalSubmissionAction::class)->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_alpha' => 'conflict'],
            schemaSnapshot: ['fields' => []],
            slotPayloads: [[
                'submission_id' => 1001,
                'facility_id' => $facilityId,
                'date' => '2026-04-15',
                'start_time' => '09:30',
                'end_time' => '10:30',
                'status' => 'Pending',
            ]],
        );
    }

    private function createUser(string $email): User
    {
        $statusId = DB::table('tbl_user_status')->insertGetId([
            'status_name' => 'Active',
            'description' => 'Active test status',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->create([
            'username' => strstr($email, '@', true) ?: 'tester',
            'email' => $email,
            'password' => bcrypt('password'),
            'user_status_id' => $statusId,
            'must_change_password' => false,
        ]);
    }

    private function createForm(User $creator, string $formCode = 'CANONICAL-FORM'): Form
    {
        return Form::query()->create([
            'form_name' => 'Canonical Submission Test Form',
            'form_code' => $formCode,
            'description' => 'Test form',
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'submission_limit' => null,
            'is_locked' => false,
            'draft_data' => null,
            'created_by' => $creator->account_id,
        ]);
    }

    /**
     * @return array{0: Workflow, 1: WorkflowStep}
     */
    private function createWorkflowStep(Form $form, User $creator): array
    {
        $workflow = Workflow::query()->create([
            'workflow_name' => 'Canonical Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => 'Workflow for canonical submission tests',
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Initial Review',
            'step_description' => 'Review the submission',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $creator->account_id,
            'max_duration_hours' => null,
            'step_conditions' => [],
            'if_rejected_id' => null,
        ]);

        return [$workflow, $step];
    }
}
