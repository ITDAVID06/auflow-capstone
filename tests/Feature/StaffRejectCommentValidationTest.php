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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffRejectCommentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_reject_endpoint_requires_comment(): void
    {
        $staff = $this->createUserWithPermissions(['dashboard.staff', 'requests.approve']);
        $submitter = $this->createUserWithPermissions([]);

        $form = Form::query()->create([
            'form_name' => 'Reject Validation Form',
            'form_code' => 'REJ'.uniqid(),
            'description' => 'Reject validation test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $staff->account_id,
            'is_locked' => true,
        ]);

        $workflow = Workflow::query()->create([
            'workflow_name' => 'Reject Validation Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $staff->account_id,
        ]);

        $step = WorkflowStep::query()->create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Validation Step',
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
            'payload_json' => [],
            'schema_snapshot_json' => ['fields' => []],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $progress = WorkflowStepProgress::query()->create([
            'form_id' => $form->id,
            'submission_id' => 9001,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $staff->account_id,
            'status' => 'Pending',
            'started_at' => now()->subMinute(),
        ]);

        $this->actingAs($staff)
            ->putJson(route('staff-dashboard.progress.reject', ['id' => $progress->id]), [
                'comment' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
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

        $role = Role::query()->create([
            'role_name' => 'Role '.uniqid(),
            'description' => 'Test role',
            'is_active' => true,
        ]);
        $role->permissions()->sync($permissionIds);

        $user = User::query()->create([
            'username' => 'staff_'.uniqid(),
            'email' => 'staff_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        UserRole::query()->create([
            'account_id' => $user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active' => true,
            'assigned_by' => $user->account_id,
        ]);

        return $user;
    }
}
