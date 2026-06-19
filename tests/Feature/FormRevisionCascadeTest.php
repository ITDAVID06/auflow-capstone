<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FormRevisionCascadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_creating_revision_archives_previous_form_and_workflow(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $formV1 = $this->createFormWithField($user->account_id, 'Rev Test', 'AUF-Form-99901', 1);
        $workflowV1 = $this->createWorkflow($formV1, $user->account_id, 'Active');

        // Verify v1 is active
        $this->assertSame('Active', $formV1->fresh()->status);
        $this->assertSame('Active', $workflowV1->fresh()->status);

        // Create revision
        $response = $this->post("/admin/forms/{$formV1->id}/revise");
        $response->assertRedirect(route('admin.forms.index'));

        // Previous form should be archived (soft-deleted)
        $this->assertSoftDeleted('tbl_form', ['id' => $formV1->id]);

        // Previous workflow should be archived
        $this->assertSame('Archived', $workflowV1->fresh()->status);

        // New form rev should exist
        $formV2 = Form::where('form_family_code', 'AUF-Form-99901')
            ->where('version', 2)
            ->first();
        $this->assertNotNull($formV2);
        $this->assertSame('Inactive', $formV2->status);
        $this->assertFalse((bool) $formV2->is_locked);

        // Workflow should be cloned and bound to new form rev
        $workflowV2 = Workflow::where('form_id', $formV2->id)->first();
        $this->assertNotNull($workflowV2, 'Workflow was not cloned for new revision');
        $this->assertSame('Draft', $workflowV2->status);
    }

    public function test_cloned_workflow_has_same_steps(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $formV1 = $this->createFormWithField($user->account_id, 'Step Clone Test', 'AUF-Form-99902', 1);
        $workflow = $this->createWorkflow($formV1, $user->account_id, 'Active');

        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step A',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $user->account_id,
        ]);
        WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Step B',
            'step_order' => 2,
            'step_group' => 2,
            'action_type' => 'Review',
            'assigned_account_id' => $user->account_id,
        ]);

        $this->post("/admin/forms/{$formV1->id}/revise")
            ->assertRedirect(route('admin.forms.index'));

        $formV2 = Form::where('form_family_code', 'AUF-Form-99902')
            ->where('version', 2)
            ->first();
        $this->assertNotNull($formV2, 'Form v2 was not created');

        $clonedWorkflow = Workflow::where('form_id', $formV2->id)->first();
        $this->assertNotNull($clonedWorkflow);
        $this->assertSame(2, $clonedWorkflow->steps()->count());
        $this->assertSame('Step A', $clonedWorkflow->steps()->orderBy('step_order')->first()->step_name);
    }

    public function test_publishing_workflow_archives_sibling_revision(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        // Create two forms in same family
        $formV1 = $this->createFormWithField($user->account_id, 'Sibling Test', 'AUF-Form-99903', 1);
        $workflowV1 = $this->createWorkflow($formV1, $user->account_id, 'Active');

        $formV2 = $this->createFormWithField($user->account_id, 'Sibling Test', 'AUF-Form-99903', 2, $formV1->id);
        $workflowV2 = $this->createWorkflow($formV2, $user->account_id, 'Draft');

        WorkflowStep::create([
            'workflow_id' => $workflowV2->id,
            'step_name' => 'Approval',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $user->account_id,
        ]);

        // Publish v2 workflow — should archive v1's form + workflow
        $service = app(WorkflowService::class);
        $service->publishWorkflow($workflowV2->id);

        // v1 form should be soft-deleted (archived)
        $this->assertSoftDeleted('tbl_form', ['id' => $formV1->id]);
        $this->assertSame('Archived', $workflowV1->fresh()->status);

        // v2 should be active
        $this->assertSame('Active', $workflowV2->fresh()->status);
        $this->assertSame('Active', $formV2->fresh()->status);
    }

    public function test_enabling_archived_workflow_archives_sibling_revision(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        $formV1 = $this->createFormWithField($user->account_id, 'Enable Test', 'AUF-Form-99904', 1);
        $workflowV1 = $this->createWorkflow($formV1, $user->account_id, 'Active');

        $formV2 = $this->createFormWithField($user->account_id, 'Enable Test', 'AUF-Form-99904', 2, $formV1->id);
        $workflowV2 = $this->createWorkflow($formV2, $user->account_id, 'Archived');

        // Enable v2 workflow — should archive v1
        $service = app(WorkflowService::class);
        $service->enableWorkflow($workflowV2->id);

        $this->assertSoftDeleted('tbl_form', ['id' => $formV1->id]);
        $this->assertSame('Archived', $workflowV1->fresh()->status);
        $this->assertSame('Active', $workflowV2->fresh()->status);
    }

    public function test_revision_cascade_does_not_affect_unrelated_forms(): void
    {
        $user = $this->createAdminUser();
        $this->actingAs($user);

        // Form in different family
        $unrelatedForm = $this->createFormWithField($user->account_id, 'Unrelated', 'AUF-Form-99999', 1);
        $unrelatedWorkflow = $this->createWorkflow($unrelatedForm, $user->account_id, 'Active');

        // Form to revise
        $formV1 = $this->createFormWithField($user->account_id, 'Cascade Test', 'AUF-Form-99905', 1);
        $this->createWorkflow($formV1, $user->account_id, 'Active');

        $this->post("/admin/forms/{$formV1->id}/revise");

        // Unrelated form and workflow should be untouched
        $this->assertSame('Active', $unrelatedForm->fresh()->status);
        $this->assertNull($unrelatedForm->fresh()->deleted_at);
        $this->assertSame('Active', $unrelatedWorkflow->fresh()->status);
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function createFormWithField(int $accountId, string $name, string $familyCode, int $version, ?int $parentId = null): Form
    {
        $form = Form::create([
            'form_name' => $name,
            'form_code' => $familyCode.' Rev-'.str_pad((string) $version, 2, '0', STR_PAD_LEFT),
            'form_family_code' => $familyCode,
            'parent_form_id' => $parentId,
            'description' => 'Test form',
            'version' => $version,
            'status' => $version === 1 ? 'Active' : 'Inactive',
            'is_locked' => $version === 1,
            'created_by' => $accountId,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_details',
            'label' => 'Details',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        return $form->fresh('fields');
    }

    private function createWorkflow(Form $form, int $actorId, string $status): Workflow
    {
        return Workflow::create([
            'workflow_name' => $form->form_name.' Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => 'Test workflow',
            'status' => $status,
            'created_by' => $actorId,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);
    }

    private function createAdminUser(): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'forms.manage'],
            [
                'permission_name' => 'Manage Forms',
                'description' => 'Test permission',
                'resource' => 'forms',
                'action' => 'manage',
            ]
        );

        $role = Role::create([
            'role_name' => 'Admin '.uniqid(),
            'description' => 'Admin role',
            'is_active' => true,
        ]);
        $role->permissions()->sync([$permission->id]);

        $user = User::create([
            'username' => 'admin_'.uniqid(),
            'email' => 'admin_'.uniqid().'@test.com',
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
