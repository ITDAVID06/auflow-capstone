<?php

namespace Tests\Feature\FormBuilder;

use App\Exceptions\WorkflowVersionNotFoundException;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegacyWorkflowFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_handle_submission_throws_when_active_workflow_has_no_published_version(): void
    {
        $user = $this->createUser();
        $form = $this->createFormWithField($user->account_id);

        // Active workflow – deliberately has NO WorkflowVersion (mimics Audit C2 state)
        $workflow = Workflow::create([
            'workflow_name' => 'No-Version Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Review',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $user->account_id,
        ]);

        $this->actingAs($user);

        $request = Request::create('/user/forms/'.$form->id.'/submit', 'POST', [
            'field_details' => 'Test payload',
        ]);

        $notifier = $this->createMock(NotificationService::class);
        $service = new StudentSubmissionService($notifier);

        $this->expectException(WorkflowVersionNotFoundException::class);

        $service->handleSubmission($request, $form->id);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'forms.view'],
            ['permission_name' => 'View Forms', 'description' => 'View forms', 'resource' => 'forms', 'action' => 'view']
        );

        $role = Role::create(['role_name' => 'Viewer_'.uniqid(), 'description' => 'Test', 'is_active' => true]);
        $role->permissions()->sync([$permission->id]);

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

    private function createFormWithField(int $actorId): Form
    {
        $form = Form::create([
            'form_name' => 'LegacyFallback Form',
            'form_code' => 'LFB-'.uniqid(),
            'status' => 'Active',
            'version' => 1,
            'is_locked' => true,
            'created_by' => $actorId,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_details',
            'label' => 'Details',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 0,
        ]);

        return $form->fresh('fields');
    }
}
