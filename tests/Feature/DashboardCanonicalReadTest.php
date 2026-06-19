<?php

namespace Tests\Feature;

use App\Modules\Dashboard\Controllers\AdminDashboardController;
use App\Modules\FormBuilder\Models\Form;
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

class DashboardCanonicalReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_dashboard_pending_approvals_use_canonical_requester_name_without_runtime_rows(): void
    {
        $admin = $this->createUserWithPermissions(['dashboard.admin']);
        $submitter = $this->createUserWithPermissions(['dashboard.staff']);

        DB::table('tbl_userprofile')->insert([
            'account_id' => $submitter->account_id,
            'first_name' => 'Pending',
            'last_name' => 'Requester',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $form = Form::create([
            'form_name' => 'Dashboard Canonical Form',
            'form_code' => 'DASH_CANONICAL',
            'description' => 'Dashboard canonical requester test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $admin->account_id,
            'email_notifications' => false,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Dashboard Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $admin->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Admin Review',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $admin->account_id,
            'step_conditions' => ['watch_fields' => []],
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'current_step_id' => $step->id,
            'current_actor_id' => $admin->account_id,
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
            'step_id' => $step->id,
            'actor_id' => $admin->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinute(),
        ]);

        $controller = app(AdminDashboardController::class);
        $reflection = new \ReflectionMethod($controller, 'resolveRequesterName');
        $reflection->setAccessible(true);

        $requesterName = $reflection->invoke($controller, WorkflowStepProgress::query()->firstOrFail());

        $this->assertSame('Pending Requester', $requesterName);
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
