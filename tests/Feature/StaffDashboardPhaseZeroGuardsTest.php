<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgressCommentAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffDashboardPhaseZeroGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_staff_dashboard_routes_require_authentication(): void
    {
        $this->get(route('staff-dashboard.index'))->assertRedirect(route('login'));
        $this->get(route('staff-dashboard.requests'))->assertRedirect(route('login'));

        $this->put(route('staff-dashboard.progress.approve', ['id' => 1]), [
            'comment' => 'Approve',
        ])->assertRedirect(route('login'));

        $this->put(route('staff-dashboard.progress.reject', ['id' => 1]), [
            'comment' => 'Reject',
        ])->assertRedirect(route('login'));
    }

    public function test_unassigned_staff_cannot_view_submission(): void
    {
        $viewer = $this->createUser();
        $assigned = $this->createUser();

        $progress = $this->createPendingProgressForAssignee($assigned->account_id);

        $this->actingAs($viewer)
            ->get(route('staff-dashboard.submission.view', ['id' => $progress->id]))
            ->assertForbidden();
    }

    public function test_unassigned_staff_cannot_approve_or_reject_submission(): void
    {
        $viewer = $this->createUser();
        $assigned = $this->createUser();

        $progress = $this->createPendingProgressForAssignee($assigned->account_id);

        $this->actingAs($viewer)
            ->putJson(route('staff-dashboard.progress.approve', ['id' => $progress->id]), [
                'comment' => 'Approve',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false);

        $this->actingAs($viewer)
            ->putJson(route('staff-dashboard.progress.reject', ['id' => $progress->id]), [
                'comment' => 'Reject',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false);
    }

    public function test_assigned_staff_can_view_submission(): void
    {
        $assigned = $this->createUser();
        $progress = $this->createPendingProgressForAssignee($assigned->account_id);

        $this->actingAs($assigned)
            ->get(route('staff-dashboard.submission.view', ['id' => $progress->id]))
            ->assertOk();
    }

    public function test_authenticated_staff_can_open_dashboard_pages_with_empty_queue(): void
    {
        $staff = $this->createUser();

        $this->actingAs($staff)
            ->get(route('staff-dashboard.index'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('staff-dashboard.requests'))
            ->assertOk();
    }

    public function test_submission_owner_can_download_progress_attachment_without_runtime_rows(): void
    {
        $owner = $this->createUser();
        $fixture = $this->createProgressAttachmentFixture($owner->account_id, $owner->account_id);

        $this->actingAs($owner)
            ->get(route('staff-dashboard.progress-attachments.download', ['id' => $fixture['attachment']->id]))
            ->assertOk();
    }

    public function test_user_without_dashboard_staff_permission_cannot_access_staff_dashboard_pages(): void
    {
        $user = $this->createUserWithPermissions(['requests.approve']);

        $this->actingAs($user)
            ->get(route('staff-dashboard.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('staff-dashboard.forms.index'))
            ->assertForbidden();
    }

    public function test_user_without_review_permissions_cannot_access_review_action_routes(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.staff']);
        $assigned = $this->createUser();
        $progress = $this->createPendingProgressForAssignee($assigned->account_id);

        $this->actingAs($user)
            ->get(route('staff-dashboard.submission.view', ['id' => $progress->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->putJson(route('staff-dashboard.progress.approve', ['id' => $progress->id]), [
                'comment' => 'Approve',
            ])
            ->assertForbidden();
    }

    private function createUser(): User
    {
        return $this->createUserWithPermissions(['dashboard.staff', 'requests.approve']);
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

    private function createPendingProgressForAssignee(int $assignedAccountId): WorkflowStepProgress
    {
        $form = Form::create([
            'form_name' => 'Staff Guard Form '.uniqid(),
            'form_code' => 'SGF-'.uniqid(),
            'description' => 'Test form',
            'form_category_id' => null,
            'version' => 1,
            'status' => 'Active',
            'created_by' => $assignedAccountId,
            'email_notifications' => false,
            'submission_limit' => null,
            'is_locked' => true,
            'draft_data' => null,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Staff Guard Workflow '.uniqid(),
            'workflow_type' => 'Sequential',
            'version' => 1,
            'effective_from' => now(),
            'effective_to' => null,
            'form_id' => $form->id,
            'description' => 'Test workflow',
            'workflow_settings' => null,
            'status' => 'Active',
            'created_by' => $assignedAccountId,
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Department Review',
            'step_description' => 'Review',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $assignedAccountId,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $assignedAccountId,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'current_step_id' => $step->id,
            'current_actor_id' => $assignedAccountId,
            'payload_json' => [],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 999,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'step_id' => $step->id,
            'actor_id' => $assignedAccountId,
            'action_taken' => null,
            'comments' => null,
            'acted_at' => null,
            'status' => 'Pending',
            'started_at' => now(),
            'completed_at' => null,
            'duration_seconds' => null,
            'reminder_count' => 0,
            'last_reminder_at' => null,
        ]);
    }

    /**
     * @return array{progress: WorkflowStepProgress, attachment: WorkflowStepProgressCommentAttachment}
     */
    private function createProgressAttachmentFixture(int $assignedAccountId, int $ownerAccountId): array
    {
        $progress = $this->createPendingProgressForAssignee($assignedAccountId);

        $submission = FormSubmission::query()
            ->findOrFail($progress->submission_id);

        $submission->forceFill(['account_id' => $ownerAccountId])->save();

        $relativePath = 'test-progress-attachments/'.uniqid().'.txt';
        $absolutePath = storage_path('app/private/'.$relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }

        file_put_contents($absolutePath, 'attachment');

        $attachment = WorkflowStepProgressCommentAttachment::create([
            'progress_id' => $progress->id,
            'uploaded_by' => $assignedAccountId,
            'file_path' => $relativePath,
            'original_name' => 'evidence.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => filesize($absolutePath),
        ]);

        return [
            'progress' => $progress->fresh(),
            'attachment' => $attachment,
        ];
    }
}
