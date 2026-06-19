<?php

namespace Tests\Feature\StudentDashboard;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NeedsRevisionAndFlashKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    // -------------------------------------------------------------------------
    // Issue 1: "Needs Revision" submissions should be editable
    // -------------------------------------------------------------------------

    public function test_edit_route_returns_403_for_pending_submission(): void
    {
        $user = $this->createStudentUser();
        [$form, $submission] = $this->createSubmissionWithStatus($user, 'Pending');

        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', [
                'formId' => $form->id,
                'submissionId' => $submission->id,
            ]))
            ->assertForbidden();
    }

    public function test_edit_route_is_accessible_for_rejected_submission(): void
    {
        $user = $this->createStudentUser();
        [$form, $submission] = $this->createSubmissionWithStatus($user, 'Rejected');

        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', [
                'formId' => $form->id,
                'submissionId' => $submission->id,
            ]))
            ->assertOk();
    }

    public function test_edit_route_is_accessible_for_needs_revision_submission(): void
    {
        $user = $this->createStudentUser();
        [$form, $submission] = $this->createSubmissionWithStatus($user, 'Needs Revision');

        $this->actingAs($user)
            ->get(route('student-dashboard.submission.edit', [
                'formId' => $form->id,
                'submissionId' => $submission->id,
            ]))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Issue 2: submission_success flash key is set after update redirect
    // -------------------------------------------------------------------------

    public function test_update_submission_sets_submission_success_flash_key(): void
    {
        $user = $this->createStudentUser();
        [$form, $submission] = $this->createSubmissionWithStatus($user, 'Rejected');

        $this->withoutExceptionHandling()
            ->actingAs($user)
            ->put(route('student-dashboard.submission.update', [
                'formId' => $form->id,
                'submissionId' => $submission->id,
            ]), [])
            ->assertRedirect()
            ->assertSessionHas('submission_success');
    }

    public function test_submission_success_flash_contains_form_name_and_submission_id(): void
    {
        $user = $this->createStudentUser();
        [$form, $submission] = $this->createSubmissionWithStatus($user, 'Rejected');

        $this->withoutExceptionHandling()
            ->actingAs($user)
            ->put(route('student-dashboard.submission.update', [
                'formId' => $form->id,
                'submissionId' => $submission->id,
            ]), [])
            ->assertSessionHas('submission_success.form_name', $form->form_name)
            ->assertSessionHas('submission_success.submission_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{0: Form, 1: FormSubmission}
     */
    private function createSubmissionWithStatus(User $user, string $status): array
    {
        $form = Form::create([
            'form_name' => 'Edit Test Form '.uniqid(),
            'form_code' => 'ETF-'.uniqid(),
            'form_family_code' => 'ETF-'.uniqid(),
            'description' => 'Test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        // One text field so editSubmission payload returns form fields correctly.
        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'notes',
            'label' => 'Notes',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $form = $form->fresh('fields');

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $user->account_id,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'payload_json' => ['notes' => 'original note'],
            'schema_snapshot_json' => $form->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return [$form, $submission->fresh()];
    }

    private function createStudentUser(): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'dashboard.student'],
            [
                'permission_name' => 'Student Dashboard',
                'description' => 'Access student dashboard',
                'resource' => 'dashboard',
                'action' => 'student',
            ]
        );

        $role = Role::create([
            'role_name' => 'Student '.uniqid(),
            'description' => 'Student role',
            'is_active' => true,
        ]);

        $role->permissions()->sync([$permission->id]);

        $user = User::create([
            'username' => 'student_'.uniqid(),
            'email' => 'student_'.uniqid().'@test.com',
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
