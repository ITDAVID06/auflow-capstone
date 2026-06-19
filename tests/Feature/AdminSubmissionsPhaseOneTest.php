<?php

namespace Tests\Feature;

use App\Modules\AdminSubmissions\Services\AdminSubmissionsOverrideService;
use App\Modules\AdminSubmissions\Services\AdminSubmissionsService;
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
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class AdminSubmissionsPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_my_pending_uses_authenticated_users_account_id_for_service_calls(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $expectedAccountId = (int) $user->account_id;

        $service = Mockery::mock(AdminSubmissionsService::class);
        $service->shouldReceive('getUserMetrics')
            ->once()
            ->with($expectedAccountId)
            ->andReturn([]);
        $service->shouldReceive('getSystemSubmissionsForUserPaginated')
            ->once()
            ->with($expectedAccountId, 'pending', null, 9)
            ->andReturn([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 9,
                    'total' => 0,
                ],
            ]);

        $this->app->instance(AdminSubmissionsService::class, $service);

        $this->actingAs($user)
            ->get(route('admin-submissions.my-pending'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-submissions/components/MyPendingApprovalsPage')
                ->where('filters.status', 'pending')
            );
    }

    public function test_show_sets_can_act_false_when_user_cannot_approve_and_has_no_override_permission(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);

        $service = Mockery::mock(AdminSubmissionsService::class);
        $service->shouldReceive('getAdminSubmissionDetails')
            ->once()
            ->with(10, 100)
            ->andReturn([
                'progress_id' => 100,
                'form_name' => 'Test Form',
            ]);

        $this->app->instance(AdminSubmissionsService::class, $service);

        $this->actingAs($user)
            ->get(route('admin-submissions.show', ['formId' => 10, 'submissionId' => 100]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-submissions/AdminReviewPage')
                ->where('backUrl', route('admin-submissions.index'))
                ->where('canAct', false)
            );
    }

    public function test_show_sets_can_act_true_for_users_with_override_permission(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);

        $service = Mockery::mock(AdminSubmissionsService::class);
        $service->shouldReceive('getAdminSubmissionDetails')
            ->once()
            ->with(20, 200)
            ->andReturn([
                'progress_id' => 200,
                'form_name' => 'Override Form',
            ]);

        $this->app->instance(AdminSubmissionsService::class, $service);

        $this->actingAs($user)
            ->get(route('admin-submissions.show', ['formId' => 20, 'submissionId' => 200]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-submissions/AdminReviewPage')
                ->where('backUrl', route('admin-submissions.index'))
                ->where('canAct', true)
            );
    }

    public function test_override_actions_require_submissions_override_permission(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);

        $this->actingAs($user)
            ->put(route('admin-submissions.approve', ['id' => 9999]), ['comment' => 'test'])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin-submissions.reject', ['id' => 9999]), ['comment' => 'test'])
            ->assertForbidden();
    }

    public function test_admin_override_accepts_waiting_progress_status(): void
    {
        $admin = $this->createUserWithPermissions(['submissions.override']);

        $form = Form::create([
            'form_name' => 'Override Waiting Form',
            'form_code' => 'OW'.uniqid(),
            'version' => 1,
            'status' => 'Active',
            'created_by' => $admin->account_id,
            'is_locked' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Override Waiting Workflow',
            'workflow_type' => 'Sequential',
            'version' => 1,
            'effective_from' => now(),
            'effective_to' => null,
            'form_id' => null,
            'description' => null,
            'workflow_settings' => null,
            'status' => 'Active',
            'created_by' => $admin->account_id,
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 1',
            'step_description' => null,
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $admin->account_id,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $admin->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $admin->account_id,
            'status' => 'Waiting',
            'started_at' => now(),
        ]);

        app(AdminSubmissionsOverrideService::class)->adminOverride(
            progressId: $progress->id,
            adminId: $admin->account_id,
            status: 'Approved',
            comment: 'Admin override from waiting',
            forceAssignment: true,
            forceReadiness: true,
        );

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'id' => $progress->id,
            'status' => 'Approved',
            'action_taken' => 'Override-Approve',
            'actor_id' => $admin->account_id,
        ]);
    }

    public function test_approve_route_allows_override_when_previous_steps_are_pending(): void
    {
        Bus::fake();

        $admin = $this->createUserWithPermissions(['submissions.override']);

        $form = Form::create([
            'form_name' => 'Override Pending Previous Form',
            'form_code' => 'OP'.uniqid(),
            'version' => 1,
            'status' => 'Active',
            'created_by' => $admin->account_id,
            'is_locked' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Override Pending Previous Steps Workflow',
            'workflow_type' => 'Sequential',
            'version' => 1,
            'effective_from' => now(),
            'effective_to' => null,
            'form_id' => null,
            'description' => null,
            'workflow_settings' => null,
            'status' => 'Active',
            'created_by' => $admin->account_id,
        ]);

        $firstStep = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 1',
            'step_description' => null,
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $admin->account_id,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $secondStep = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 2',
            'step_description' => null,
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $admin->account_id,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $admin->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $firstStep->id,
            'actor_id' => $admin->account_id,
            'status' => 'Pending',
            'started_at' => now(),
        ]);

        $targetProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $secondStep->id,
            'actor_id' => $admin->account_id,
            'status' => 'Waiting',
            'started_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin-submissions.index'))
            ->put(route('admin-submissions.approve', ['id' => $targetProgress->id]), [
                'comment' => 'Override despite pending prior step',
            ])
            ->assertRedirect(route('admin-submissions.index'));

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'id' => $targetProgress->id,
            'status' => 'Approved',
            'action_taken' => 'Override-Approve',
            'actor_id' => $admin->account_id,
        ]);
    }

    public function test_admin_override_readiness_treats_skipped_previous_steps_as_completed(): void
    {
        Bus::fake();

        $admin = $this->createUserWithPermissions(['submissions.override']);

        $form = Form::create([
            'form_name' => 'Override Skipped Readiness Form',
            'form_code' => 'OS'.uniqid(),
            'version' => 1,
            'status' => 'Active',
            'created_by' => $admin->account_id,
            'is_locked' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Override Skipped Readiness Workflow',
            'workflow_type' => 'Sequential',
            'version' => 1,
            'effective_from' => now(),
            'effective_to' => null,
            'form_id' => null,
            'description' => null,
            'workflow_settings' => null,
            'status' => 'Active',
            'created_by' => $admin->account_id,
        ]);

        $firstStep = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 1',
            'step_description' => null,
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $admin->account_id,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $secondStep = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 2',
            'step_description' => null,
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $admin->account_id,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $admin->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $firstStep->id,
            'actor_id' => $admin->account_id,
            'status' => 'Skipped',
            'started_at' => now()->subMinutes(5),
            'acted_at' => now()->subMinutes(4),
            'completed_at' => now()->subMinutes(4),
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(4),
        ]);

        $targetProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $secondStep->id,
            'actor_id' => $admin->account_id,
            'status' => 'Pending',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AdminSubmissionsOverrideService::class)->adminOverride(
            progressId: $targetProgress->id,
            adminId: $admin->account_id,
            status: 'Approved',
            comment: 'Override after skipped prior step',
            forceAssignment: true,
            forceReadiness: false,
        );

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'id' => $targetProgress->id,
            'status' => 'Approved',
            'action_taken' => 'Override-Approve',
            'actor_id' => $admin->account_id,
        ]);
    }

    public function test_show_uses_first_pending_step_as_override_progress_id(): void
    {
        $admin = $this->createUserWithPermissions(['submissions.override']);

        $fixture = $this->createCanonicalAdminSubmissionFixture($admin, $admin, true);
        $submissionId = (int) $fixture['submission']->id;
        $firstProgress = $fixture['first_progress'];

        $this->actingAs($admin)
            ->get(route('admin-submissions.show', ['formId' => $fixture['form']->id, 'submissionId' => $submissionId]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-submissions/AdminReviewPage')
                ->where('submission.progress_id', $firstProgress->id)
            );
    }

    public function test_admin_index_reads_canonical_submissions_without_runtime_rows(): void
    {
        $admin = $this->createUserWithPermissions(['submissions.view']);
        $submitter = $this->createUserWithPermissions([]);
        $fixture = $this->createCanonicalAdminSubmissionFixture($admin, $submitter);

        $this->actingAs($admin)
            ->get(route('admin-submissions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-submissions/AdminSubmissionsPage')
                ->has('requests', 1)
                ->where('requests.0.submission_id', $fixture['submission']->id)
                ->where('requests.0.submitter', 'Submitter Person')
            );
    }

    public function test_my_pending_reads_canonical_submissions_without_runtime_rows(): void
    {
        $admin = $this->createUserWithPermissions(['submissions.view']);
        $submitter = $this->createUserWithPermissions([]);
        $fixture = $this->createCanonicalAdminSubmissionFixture($admin, $submitter);

        $this->actingAs($admin)
            ->get(route('admin-submissions.my-pending'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-submissions/components/MyPendingApprovalsPage')
                ->has('requests', 1)
                ->where('requests.0.submission_id', $fixture['submission']->id)
                ->where('requests.0.submitter', 'Submitter Person')
            );
    }

    /**
     * @return array{form: Form, submission: FormSubmission, first_progress: WorkflowStepProgress}
     */
    private function createCanonicalAdminSubmissionFixture(User $admin, User $submitter, bool $withWaitingSecondStep = false): array
    {
        DB::table('tbl_userprofile')->insertOrIgnore([
            [
                'account_id' => $admin->account_id,
                'first_name' => 'Admin',
                'last_name' => 'User',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => $submitter->account_id,
                'first_name' => 'Submitter',
                'last_name' => 'Person',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $form = Form::create([
            'form_name' => 'Admin Canonical Test Form '.uniqid(),
            'form_code' => 'ADMCAN'.uniqid(),
            'description' => 'Admin canonical test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $admin->account_id,
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
            'workflow_name' => 'Admin Canonical Workflow '.uniqid(),
            'workflow_type' => 'Sequential',
            'version' => 1,
            'effective_from' => now(),
            'effective_to' => null,
            'form_id' => $form->id,
            'description' => null,
            'workflow_settings' => null,
            'status' => 'Active',
            'created_by' => $admin->account_id,
        ]);

        $firstStep = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 1',
            'step_description' => null,
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $admin->account_id,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'current_step_id' => $firstStep->id,
            'current_actor_id' => $admin->account_id,
            'payload_json' => ['field_text_name' => 'Sequence Check'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subMinutes(5),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $firstProgress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'step_id' => $firstStep->id,
            'actor_id' => $admin->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        if ($withWaitingSecondStep) {
            $secondStep = WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'step_name' => 'Step 2',
                'step_description' => null,
                'step_order' => 2,
                'step_group' => 1,
                'action_type' => 'Approve',
                'assigned_account_id' => $admin->account_id,
                'max_duration_hours' => null,
                'step_conditions' => null,
                'if_rejected_id' => null,
            ]);

            WorkflowStepProgress::create([
                'form_id' => $form->id,
                'submission_id' => $submission->id,
                'workflow_id' => $workflow->id,
                'workflow_version' => 1,
                'step_id' => $secondStep->id,
                'actor_id' => $admin->account_id,
                'status' => 'Waiting',
                'started_at' => now()->subMinute(),
                'created_at' => now()->subMinute(),
                'updated_at' => now(),
            ]);
        }

        return [
            'form' => $form,
            'submission' => $submission,
            'first_progress' => $firstProgress,
        ];
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
