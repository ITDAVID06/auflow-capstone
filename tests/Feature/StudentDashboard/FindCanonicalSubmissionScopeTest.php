<?php

namespace Tests\Feature\StudentDashboard;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * findCanonicalSubmissionRecord must scope the query to the given form ID.
 *
 * Without the constraint, a caller can pass any submission ID and get back a
 * record belonging to a different form. This test verifies that the helper
 * returns null (not the wrong record) when the submission belongs to a
 * different form than the one provided.
 */
class FindCanonicalSubmissionScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createForm(): Form
    {
        $form = Form::create([
            'form_name' => 'Form '.uniqid(),
            'form_code' => 'F-'.uniqid(),
            'form_family_code' => 'FC-'.uniqid(),
            'status' => 'Active',
            'is_locked' => true,
            'version' => 1,
            'created_by' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'notes',
            'label' => 'Notes',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 0,
        ]);

        return $form;
    }

    private function createUser(): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'dashboard.student'],
            ['permission_name' => 'Student Dashboard', 'description' => 'Access student dashboard', 'resource' => 'dashboard', 'action' => 'student']
        );

        $role = Role::create(['role_name' => 'Role '.uniqid(), 'description' => '', 'is_active' => true]);
        $role->permissions()->attach($permission->id);

        $user = User::create([
            'username' => 'user_'.uniqid(),
            'email' => 'u_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        UserRole::create([
            'account_id' => $user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active' => true,
            'assigned_by' => $user->account_id,
        ]);

        return $user;
    }

    private function createSubmission(Form $form, int $accountId): FormSubmission
    {
        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $accountId,
            'submission_status' => 'Rejected',
            'current_workflow_status' => 'Rejected',
            'payload_json' => ['notes' => 'test'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }

    /** Creates a Rejected workflow step progress so assertSubmissionEditable passes. */
    private function attachRejectedStatus(Form $form, FormSubmission $submission, int $actorId): void
    {
        $workflow = Workflow::firstOrCreate(
            ['form_id' => $form->id, 'status' => 'Active', 'version' => 1],
            [
                'workflow_name' => 'WF '.uniqid(),
                'workflow_type' => 'Sequential',
                'description' => 'Test',
                'created_by' => $actorId,
            ]
        );

        $step = WorkflowStep::firstOrCreate(
            ['workflow_id' => $workflow->id, 'step_order' => 1],
            [
                'step_name' => 'Step 1',
                'step_description' => '',
                'step_group' => 1,
                'action_type' => 'Approve',
                'assigned_account_id' => $actorId,
            ]
        );

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'step_id' => $step->id,
            'actor_id' => $actorId,
            'status' => 'Rejected',
            'started_at' => now()->subHour(),
            'acted_at' => now(),
            'created_at' => now()->subHour(),
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // Tests
    // =========================================================================

    /**
     * When the submission belongs to form A but form B's ID is passed,
     * the edit-payload endpoint must return 404 (not the wrong form's data).
     *
     * This exercises getSubmissionEditPayload → findCanonicalSubmissionRecord.
     */
    public function test_edit_payload_returns_404_when_submission_belongs_to_different_form(): void
    {
        $user = $this->createUser();
        $formA = $this->createForm();
        $formB = $this->createForm();

        // Submission belongs to formA
        $submission = $this->createSubmission($formA, (int) $user->account_id);
        $this->attachRejectedStatus($formA, $submission, (int) $user->account_id);

        // Request the edit page for formB but pass formA's submission ID
        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', [
                'formId' => $formB->id,
                'submissionId' => $submission->id,
            ]))
            ->assertNotFound();
    }

    /**
     * Correct pairing: submission belongs to the given form → returns 200.
     */
    public function test_edit_payload_returns_200_when_submission_belongs_to_correct_form(): void
    {
        $user = $this->createUser();
        $form = $this->createForm();
        $submission = $this->createSubmission($form, (int) $user->account_id);
        $this->attachRejectedStatus($form, $submission, (int) $user->account_id);

        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', [
                'formId' => $form->id,
                'submissionId' => $submission->id,
            ]))
            ->assertOk();
    }
}
