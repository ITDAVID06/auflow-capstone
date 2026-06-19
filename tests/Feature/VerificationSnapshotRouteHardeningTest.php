<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VerificationSnapshotRouteHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_sensitive_snapshot_endpoints_require_submissions_permissions(): void
    {
        $withoutPermission = $this->createUserWithPermissions(['forms.view']);
        $withPermission = $this->createUserWithPermissions(['submissions.view']);

        $this->get(route('snapshots.verify.submission', ['submission_id' => 1]))
            ->assertRedirect(route('login'));

        $this->actingAs($withoutPermission)
            ->get(route('snapshots.progress.snapshot', ['id' => 1]))
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->get(route('snapshots.verify.submission', ['submission_id' => 1]))
            ->assertForbidden();

        $this->actingAs($withPermission)
            ->get(route('snapshots.progress.snapshot', ['id' => 1]))
            ->assertNotFound();

        $this->actingAs($withPermission)
            ->get(route('snapshots.verify.submission', ['submission_id' => 1]))
            ->assertOk()
            ->assertJsonFragment([
                'submission_id' => 1,
                'total_snapshots' => 0,
            ]);
    }

    public function test_latest_snapshot_endpoint_returns_latest_for_submission_and_form_even_if_workflow_differs(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);

        $form = Form::create([
            'form_name' => 'Snapshot Lookup Form',
            'form_code' => 'SNAP'.uniqid(),
            'description' => 'Snapshot lookup form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Snapshot Lookup Workflow',
            'workflow_type' => 'Sequential',
            'version' => 1,
            'effective_from' => now(),
            'effective_to' => null,
            'form_id' => $form->id,
            'description' => null,
            'workflow_settings' => null,
            'status' => 'Active',
            'created_by' => $user->account_id,
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step 1',
            'step_description' => null,
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $user->account_id,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $progress = WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => 123,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'step_id' => $step->id,
            'actor_id' => $user->account_id,
            'status' => 'Pending',
            'started_at' => now(),
        ]);

        Snapshot::create([
            'public_id' => str_repeat('a', 32),
            'submission_id' => 123,
            'form_id' => $form->id,
            'workflow_id' => $workflow->id + 999,
            'step_id' => $step->id,
            'workflow_step' => 'Step 1',
            'status' => 'Approved',
            'approved_by' => $user->account_id,
            'approved_at' => now(),
            'comment' => 'snapshot one',
            'payload_json' => ['form' => ['id' => $form->id], 'submission' => ['id' => 123], 'fields' => []],
            'action_hash' => str_repeat('b', 64),
            'locked' => true,
            'created_at' => now()->subMinute(),
        ]);

        $latest = Snapshot::create([
            'public_id' => str_repeat('c', 32),
            'submission_id' => 123,
            'form_id' => $form->id,
            'workflow_id' => $workflow->id + 1000,
            'step_id' => $step->id,
            'workflow_step' => 'Step 1',
            'status' => 'Approved',
            'approved_by' => $user->account_id,
            'approved_at' => now(),
            'comment' => 'snapshot two',
            'payload_json' => ['form' => ['id' => $form->id], 'submission' => ['id' => 123], 'fields' => []],
            'action_hash' => str_repeat('d', 64),
            'locked' => true,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('snapshots.progress.snapshot', ['id' => $progress->id]))
            ->assertOk()
            ->assertJson([
                'exists' => true,
                'public_id' => $latest->public_id,
                'short_code' => substr($latest->public_id, -6),
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
