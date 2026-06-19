<?php

namespace Tests\Feature;

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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentSubmissionEditPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_approved_submission_cannot_be_edited_or_updated(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);
        $form = $this->createFormWithRuntimeTable($user->account_id);
        $submissionId = $this->insertSubmission($form, $user->account_id, null);
        $this->attachWorkflowStatus($form->id, $submissionId, $user->account_id, 'Approved');

        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', ['formId' => $form->id, 'submissionId' => $submissionId]))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('student-dashboard.submission.update', ['formId' => $form->id, 'submissionId' => $submissionId]), [])
            ->assertForbidden();
    }

    public function test_only_latest_rejected_revision_can_be_edited_or_updated(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);
        $form = $this->createFormWithRuntimeTable($user->account_id);

        $originalSubmissionId = $this->insertSubmission($form, $user->account_id, null);
        $latestSubmissionId = $this->insertSubmission($form, $user->account_id, $originalSubmissionId);

        $this->attachWorkflowStatus($form->id, $originalSubmissionId, $user->account_id, 'Rejected');
        $this->attachWorkflowStatus($form->id, $latestSubmissionId, $user->account_id, 'Rejected');

        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', ['formId' => $form->id, 'submissionId' => $originalSubmissionId]))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('student-dashboard.submission.update', ['formId' => $form->id, 'submissionId' => $originalSubmissionId]), [])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', ['formId' => $form->id, 'submissionId' => $latestSubmissionId]))
            ->assertOk();

        $this->actingAs($user)
            ->put(route('student-dashboard.submission.update', ['formId' => $form->id, 'submissionId' => $latestSubmissionId]), [])
            ->assertSessionHasNoErrors();
    }

    public function test_pending_submission_cannot_be_edited_or_updated(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);
        $form = $this->createFormWithRuntimeTable($user->account_id);
        $submissionId = $this->insertSubmission($form, $user->account_id, null);
        $this->attachWorkflowStatus($form->id, $submissionId, $user->account_id, 'Pending');

        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', ['formId' => $form->id, 'submissionId' => $submissionId]))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('student-dashboard.submission.update', ['formId' => $form->id, 'submissionId' => $submissionId]), [])
            ->assertForbidden();
    }

    public function test_rejected_submission_update_ignores_non_input_builder_fields(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);

        $form = Form::create([
            'form_name' => 'Rejected Edit Form',
            'form_code' => 'STU'.uniqid(),
            'description' => 'Test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_heading_1',
            'label' => 'Heading',
            'data_type' => 'heading',
            'is_required' => false,
            'field_order' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_section_1',
            'label' => 'Section',
            'data_type' => 'section',
            'is_required' => false,
            'field_order' => 2,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text_1',
            'label' => 'Details',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 3,
        ]);

        $submissionId = $this->insertSubmission($form, $user->account_id, null);
        $this->attachWorkflowStatus($form->id, $submissionId, $user->account_id, 'Rejected');

        $response = $this->actingAs($user)
            ->put(route('student-dashboard.submission.update', ['formId' => $form->id, 'submissionId' => $submissionId]), [
                'field_heading_1' => 'Intro heading',
                'field_section_1' => 'Section divider',
                'field_text_1' => 'Updated value',
                'slots' => [
                    ['date' => now()->addDay()->toDateString()],
                ],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_latest_rejected_canonical_submission_can_be_edited_without_runtime_row(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);

        $form = Form::create([
            'form_name' => 'Canonical Only Form',
            'form_code' => 'CAN'.uniqid(),
            'description' => 'Canonical only test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_details',
            'label' => 'Details',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $user->account_id,
            'submission_status' => 'Rejected',
            'current_workflow_status' => 'Rejected',
            'payload_json' => ['field_details' => 'Canonical details'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subMinute(),
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', ['formId' => $form->id, 'submissionId' => $submission->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student-dashboard/EditSubmissionPage')
                ->where('submission.id', $submission->id)
                ->where('submission.fields.field_details', 'Canonical details')
            );
    }

    private function createFormWithRuntimeTable(int $accountId): Form
    {
        $form = Form::create([
            'form_name' => 'Student Policy Form',
            'form_code' => 'STU'.uniqid(),
            'description' => 'Test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $accountId,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_details',
            'label' => 'Details',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_range',
            'label' => 'Date Range',
            'data_type' => 'date',
            'is_required' => false,
            'date_mode' => 'range',
            'field_order' => 2,
        ]);

        return $form;
    }

    private function insertSubmission(Form $form, int $accountId, ?int $revisionOf): int
    {
        $textColumn = null;
        foreach ($form->fields as $field) {
            if ($field->data_type === 'text') {
                $textColumn = $field->field_name;
                break;
            }
        }

        $parentCanonicalSubmission = $revisionOf
            ? FormSubmission::query()->find($revisionOf)
            : null;

        $canonicalSubmission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $accountId,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => $textColumn !== null ? [$textColumn => 'Lorem ipsum'] : [],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now(),
            'revision_of' => $parentCanonicalSubmission?->id,
            'root_submission_id' => $parentCanonicalSubmission?->root_submission_id ?? $parentCanonicalSubmission?->id,
            'is_latest_revision' => true,
        ]);

        if (! $canonicalSubmission->root_submission_id) {
            $canonicalSubmission->forceFill(['root_submission_id' => $canonicalSubmission->id])->save();
        }

        if ($parentCanonicalSubmission) {
            $parentCanonicalSubmission->forceFill(['is_latest_revision' => false])->save();
        }

        return $canonicalSubmission->id;
    }

    private function attachWorkflowStatus(int $formId, int $submissionId, int $actorId, string $status): void
    {
        $workflow = Workflow::firstOrCreate(
            ['form_id' => $formId, 'status' => 'Active', 'version' => 1],
            [
                'workflow_name' => 'WF '.uniqid(),
                'workflow_type' => 'Sequential',
                'description' => 'Test workflow',
                'created_by' => $actorId,
            ]
        );

        $step = WorkflowStep::firstOrCreate(
            ['workflow_id' => $workflow->id, 'step_order' => 1],
            [
                'step_name' => 'Step 1',
                'step_description' => 'Step',
                'step_group' => 1,
                'action_type' => 'Approve',
                'assigned_account_id' => $actorId,
            ]
        );

        WorkflowStepProgress::create([
            'form_id' => $formId,
            'submission_id' => $submissionId,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'step_id' => $step->id,
            'actor_id' => $actorId,
            'status' => $status,
            'started_at' => now()->subHour(),
            'created_at' => now()->subHour(),
            'updated_at' => now(),
            'acted_at' => now(),
        ]);

        FormSubmission::query()
            ->where('form_id', $formId)
            ->whereKey($submissionId)
            ->update([
                'submission_status' => $status,
                'current_workflow_status' => $status,
                'current_step_id' => $step->id,
                'current_actor_id' => $actorId,
            ]);
    }

    private function createUserWithPermissions(array $permissionSlugs): User
    {
        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'permission_name' => ucwords(str_replace(['.', '-'], ' ', $slug)),
                    'description' => 'Test permission',
                    'resource' => explode('.', $slug)[0] ?? 'test',
                    'action' => explode('.', $slug)[1] ?? 'access',
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role = Role::create([
            'role_name' => 'Role '.uniqid(),
            'description' => 'Test role',
            'is_active' => true,
        ]);
        $role->permissions()->sync($permissionIds);

        $user = User::create([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
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
}
