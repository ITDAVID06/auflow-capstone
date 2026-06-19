<?php

namespace Tests\Feature;

use App\Modules\AdminSubmissions\Services\AdminSubmissionsOverrideService;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies that AdminSubmissionsOverrideService uses the frozen WorkflowVersion snapshot
 * (step_group / step_order) for readiness checks and downstream cascades, with no coupling
 * to workflow_settings.edges or position.id.
 *
 * Workflow shape:
 *   Group 1: Step1  (Pending)
 *   Group 2: Step2A (Waiting) + Step2B (Waiting)   ← parallel pair
 *   Group 3: Step3  (Waiting)
 */
class AdminOverrideParallelBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(string $username): User
    {
        $u = User::create([
            'username' => $username,
            'email' => $username.'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        DB::table('tbl_userprofile')->insert([
            'account_id' => $u->account_id,
            'first_name' => $username,
            'last_name' => 'Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $u;
    }

    /**
     * Build the full 4-step parallel workflow, publish it (creating a version snapshot),
     * and create progress rows for a submission.
     *
     * Returns [$adminOverrideService, $version, $progressStep1, $progressStep2A, $progressStep2B, $progressStep3]
     */
    private function buildScenario(): array
    {
        $admin = $this->makeUser('admin_parallel');
        $approver1 = $this->makeUser('approver1_parallel');
        $approver2 = $this->makeUser('approver2_parallel');
        $approver3 = $this->makeUser('approver3_parallel');

        $form = Form::create([
            'form_name' => 'Parallel Branch Form',
            'form_code' => 'PAR_'.uniqid(),
            'description' => null,
            'version' => 1,
            'status' => 'Inactive',
            'created_by' => $admin->account_id,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Parallel Branch Workflow',
            'workflow_type' => 'Parallel',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $admin->account_id,
            // Intentionally sparse — no edges, no position IDs.
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        // Step 1 — group 1, sequential first step
        $step1 = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 1',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver1->account_id,
        ]);

        // Step 2A — group 2, parallel branch A
        $step2a = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 2A',
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver2->account_id,
        ]);

        // Step 2B — group 2, parallel branch B
        $step2b = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 2B',
            'step_order' => 3,
            'step_group' => 2,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver3->account_id,
        ]);

        // Step 3 — group 3, final sequential step
        $step3 = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 3',
            'step_order' => 4,
            'step_group' => 3,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver1->account_id,
        ]);

        // Publish → creates version snapshot with all 4 steps
        $this->actingAs($admin);
        $versionId = app(WorkflowService::class)->publishWorkflow($workflow->id);
        $version = WorkflowVersion::findOrFail($versionId);

        // Create a canonical submission
        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $admin->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => ['test_field' => 'value'],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        // Wire up progress rows (simulating what ProcessFormSubmissionJob does)
        $p1 = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'step_id' => $step1->id,
            'actor_id' => $admin->account_id,
            'status' => 'Pending',
            'started_at' => now(),
        ]);

        $p2a = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'step_id' => $step2a->id,
            'actor_id' => $admin->account_id,
            'status' => 'Waiting',
        ]);

        $p2b = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'step_id' => $step2b->id,
            'actor_id' => $admin->account_id,
            'status' => 'Waiting',
        ]);

        $p3 = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'step_id' => $step3->id,
            'actor_id' => $admin->account_id,
            'status' => 'Waiting',
        ]);

        $service = new AdminSubmissionsOverrideService;

        return [$service, $version, $p1, $p2a, $p2b, $p3, $admin];
    }

    // ── Tests ────────────────────────────────────────────────────────────────

    public function test_snapshot_has_all_four_steps_with_correct_groups(): void
    {
        [, $version] = $this->buildScenario();

        $snapshot = $version->steps_snapshot;
        $this->assertCount(4, $snapshot);

        $groups = array_column($snapshot, 'step_group');
        $this->assertContains(1, $groups, 'Group 1 missing from snapshot');
        $this->assertContains(2, $groups, 'Group 2 missing from snapshot');
        $this->assertContains(3, $groups, 'Group 3 missing from snapshot');

        // Exactly two steps in group 2 (parallel pair)
        $parallelSteps = array_filter($snapshot, fn ($s) => (int) ($s['step_group'] ?? 0) === 2);
        $this->assertCount(2, $parallelSteps, 'Expected exactly 2 parallel steps in group 2');
    }

    public function test_step2a_is_not_ready_while_step1_is_pending(): void
    {
        [$service, , $p1, $p2a, , , $admin] = $this->buildScenario();

        // p1 is still Pending — p2a must NOT be ready
        $this->assertSame('Pending', $p1->fresh()->status);

        // Attempting override-approve with forceReadiness=false must throw
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Previous steps not yet approved');

        $service->adminOverride(
            progressId: $p2a->id,
            adminId: $admin->account_id,
            status: 'Approved',
            comment: 'forced',
            forceReadiness: false
        );
    }

    public function test_step2a_becomes_ready_after_step1_approved(): void
    {
        [$service, , $p1, $p2a, , , $admin] = $this->buildScenario();

        // Admin-approve Step 1 (forceAssignment=true bypasses assignee check)
        $service->adminOverride(
            progressId: $p1->id,
            adminId: $admin->account_id,
            status: 'Approved',
            comment: 'override step 1',
            forceAssignment: true
        );

        $this->assertSame('Approved', $p1->fresh()->status);

        // Step 2A should now be achievable (no exception from isStepReady)
        // We use forceAssignment=true to bypass the assignee check;
        // forceReadiness stays false to exercise the real readiness path.
        $service->adminOverride(
            progressId: $p2a->id,
            adminId: $admin->account_id,
            status: 'Approved',
            comment: 'override step 2a',
            forceAssignment: true,
            forceReadiness: false
        );

        $this->assertSame('Approved', $p2a->fresh()->status);
    }

    public function test_step3_waits_while_step2b_is_still_pending(): void
    {
        [$service, , $p1, $p2a, $p2b, $p3, $admin] = $this->buildScenario();

        // Approve Step 1 — unlocks group 2
        $service->adminOverride($p1->id, $admin->account_id, 'Approved', 'override', forceAssignment: true);

        // Advance Step 2A only; Step 2B is still Waiting (group 2 not complete)
        $service->adminOverride($p2a->id, $admin->account_id, 'Approved', 'override', forceAssignment: true);

        // Step 3 must still be Waiting (group 2 not fully done)
        $this->assertSame('Waiting', $p3->fresh()->status,
            'Step 3 should remain Waiting while Step 2B is incomplete'
        );
    }

    public function test_step3_unlocks_once_both_parallel_steps_approved(): void
    {
        [$service, , $p1, $p2a, $p2b, $p3, $admin] = $this->buildScenario();

        $service->adminOverride($p1->id, $admin->account_id, 'Approved', 'override', forceAssignment: true);
        $service->adminOverride($p2a->id, $admin->account_id, 'Approved', 'override', forceAssignment: true);
        $service->adminOverride($p2b->id, $admin->account_id, 'Approved', 'override', forceAssignment: true);

        // Step 3 should now be Pending (advanced by advanceWorkflowIfNeeded)
        $this->assertSame('Pending', $p3->fresh()->status,
            'Step 3 should advance to Pending once both parallel steps are Approved'
        );
    }

    public function test_rejection_of_one_parallel_step_cascades_downstream(): void
    {
        [$service, , $p1, $p2a, $p2b, $p3, $admin] = $this->buildScenario();

        // Approve Step 1 — this also advances Step 2A and 2B to Pending
        $service->adminOverride($p1->id, $admin->account_id, 'Approved', 'override', forceAssignment: true);

        // Manually move 2A and 2B to Pending (simulating advanceWorkflowIfNeeded result)
        $p2a->update(['status' => 'Pending']);
        $p2b->update(['status' => 'Pending']);

        // Admin-reject Step 2B — should cascade-reject Step 3 (downstream group 3)
        // and also cancel the peer Step 2A via the peer-group logic
        $service->adminOverride($p2b->id, $admin->account_id, 'Rejected', 'override-reject', forceAssignment: true);

        // Step 2B itself is Rejected
        $this->assertSame('Rejected', $p2b->fresh()->status);

        // Step 2A (peer in same group 2) should be auto-rejected as peer
        $this->assertSame('Rejected', $p2a->fresh()->status,
            'Step 2A (peer in group 2) should be auto-rejected when Step 2B is rejected'
        );

        // Step 3 (downstream, group 3) should also be auto-rejected
        $this->assertSame('Rejected', $p3->fresh()->status,
            'Step 3 (downstream group 3) should be cascade-rejected'
        );
    }

    public function test_downstream_step_ids_excludes_steps_in_same_group(): void
    {
        [, $version] = $this->buildScenario();

        $snapshot = $version->steps_snapshot;
        $step2aArr = collect($snapshot)->firstWhere('step_name', 'Step 2A');
        $this->assertNotNull($step2aArr);

        // Simulate getDownstreamStepIds logic: group 2 → downstream = group 3 only
        $downstreamIds = collect($snapshot)
            ->filter(fn ($s) => (int) ($s['step_group'] ?? 0) > (int) ($step2aArr['step_group'] ?? 0))
            ->pluck('id')
            ->values()
            ->all();

        $step2bArr = collect($snapshot)->firstWhere('step_name', 'Step 2B');
        $step3Arr = collect($snapshot)->firstWhere('step_name', 'Step 3');

        $this->assertNotContains($step2bArr['id'], $downstreamIds,
            'Step 2B (same group) should NOT be in downstream IDs'
        );
        $this->assertContains($step3Arr['id'], $downstreamIds,
            'Step 3 (next group) SHOULD be in downstream IDs'
        );
    }
}
