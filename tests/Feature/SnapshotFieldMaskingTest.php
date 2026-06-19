<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SnapshotFieldMaskingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * When a form field has is_sensitive=true and a user saves the form via the
     * FormBuilder, the flag must be persisted to the database.
     */
    public function test_form_save_persists_is_sensitive_flag(): void
    {
        $admin = $this->makeAdminUser();

        $payload = $this->baseFormPayload([
            [
                'field_name' => 'id_number',
                'label' => 'ID Number',
                'data_type' => 'text',
                'is_required' => false,
                'options' => [],
                'field_order' => 0,
                'is_sensitive' => true,
                'is_publicly_verifiable' => true,
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('forms.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_formfield', [
            'field_name' => 'id_number',
            'is_sensitive' => true,
            'is_publicly_verifiable' => true,
        ]);
    }

    /**
     * When a form field has is_publicly_verifiable=false and the form is saved,
     * the flag must be persisted to the database.
     */
    public function test_form_save_persists_is_publicly_verifiable_flag(): void
    {
        $admin = $this->makeAdminUser();

        $payload = $this->baseFormPayload([
            [
                'field_name' => 'private_field',
                'label' => 'Private Field',
                'data_type' => 'text',
                'is_required' => false,
                'options' => [],
                'field_order' => 0,
                'is_sensitive' => false,
                'is_publicly_verifiable' => false,
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('forms.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_formfield', [
            'field_name' => 'private_field',
            'is_publicly_verifiable' => false,
        ]);
    }

    /**
     * A non-submitter viewing a snapshot with a sensitive field should see
     * the value partially masked (first char + *** + last char).
     */
    public function test_sensitive_field_is_masked_for_non_submitter(): void
    {
        $submitter = $this->makeBasicUser();
        $viewer = $this->makeBasicUser();

        $snapshot = $this->makeSnapshotWithSensitiveField(
            submitter: $submitter,
            fieldValue: 'John Doe',
            isSensitive: true,
        );

        $this->actingAs($viewer)
            ->get(route('snapshots.show', $snapshot->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('snapshots/Show')
                ->where('snapshot.fields.0.value', 'J***e')
            );
    }

    /**
     * The submitter themselves should see the unmasked value in their own snapshot.
     */
    public function test_sensitive_field_is_not_masked_for_submitter(): void
    {
        $submitter = $this->makeBasicUser();

        $snapshot = $this->makeSnapshotWithSensitiveField(
            submitter: $submitter,
            fieldValue: 'John Doe',
            isSensitive: true,
        );

        $this->actingAs($submitter)
            ->get(route('snapshots.show', $snapshot->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('snapshots/Show')
                ->where('snapshot.fields.0.value', 'John Doe')
            );
    }

    /**
     * Staff with submissions.view permission should see unmasked values.
     */
    public function test_sensitive_field_is_not_masked_for_staff(): void
    {
        $submitter = $this->makeBasicUser();
        $staff = $this->makeUserWithPermissions(['submissions.view']);

        $snapshot = $this->makeSnapshotWithSensitiveField(
            submitter: $submitter,
            fieldValue: 'John Doe',
            isSensitive: true,
        );

        $this->actingAs($staff)
            ->get(route('snapshots.show', $snapshot->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('snapshots/Show')
                ->where('snapshot.fields.0.value', 'John Doe')
            );
    }

    /**
     * A staff member with requests.approve (workflow approver role) should
     * see unmasked values — they are not granted submissions.view.
     */
    public function test_sensitive_field_is_not_masked_for_approver_staff(): void
    {
        $submitter = $this->makeBasicUser();
        $approver = $this->makeUserWithPermissions(['requests.approve']);

        $snapshot = $this->makeSnapshotWithSensitiveField(
            submitter: $submitter,
            fieldValue: 'John Doe',
            isSensitive: true,
        );

        $this->actingAs($approver)
            ->get(route('snapshots.show', $snapshot->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('snapshots/Show')
                ->where('snapshot.fields.0.value', 'John Doe')
            );
    }

    /**
     * A staff member with requests.view should see unmasked values.
     */
    public function test_sensitive_field_is_not_masked_for_requests_viewer(): void
    {
        $submitter = $this->makeBasicUser();
        $viewer = $this->makeUserWithPermissions(['requests.view']);

        $snapshot = $this->makeSnapshotWithSensitiveField(
            submitter: $submitter,
            fieldValue: 'John Doe',
            isSensitive: true,
        );

        $this->actingAs($viewer)
            ->get(route('snapshots.show', $snapshot->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('snapshots/Show')
                ->where('snapshot.fields.0.value', 'John Doe')
            );
    }

    /**
     * A field that is not publicly verifiable should be fully redacted for a non-submitter.
     */
    public function test_non_public_field_is_redacted_for_non_submitter(): void
    {
        $submitter = $this->makeBasicUser();
        $viewer = $this->makeBasicUser();

        $snapshot = $this->makeSnapshotWithSensitiveField(
            submitter: $submitter,
            fieldValue: 'secret info',
            isSensitive: false,
            isPubliclyVerifiable: false,
        );

        $this->actingAs($viewer)
            ->get(route('snapshots.show', $snapshot->public_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('snapshots/Show')
                ->where('snapshot.fields.0.value', '[ Redacted for Privacy ]')
            );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeSnapshotWithSensitiveField(
        User $submitter,
        string $fieldValue,
        bool $isSensitive,
        bool $isPubliclyVerifiable = true,
    ): Snapshot {
        $admin = $this->makeAdminUser();

        $form = Form::create([
            'form_name' => 'Mask Test Form '.uniqid(),
            'form_code' => 'MASK'.uniqid(),
            'description' => 'Masking test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $admin->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'sensitive_field',
            'label' => 'Sensitive Field',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 0,
            'is_sensitive' => $isSensitive,
            'is_publicly_verifiable' => $isPubliclyVerifiable,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Mask Workflow '.uniqid(),
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

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'Approved',
            'current_workflow_status' => 'Approved',
            'current_step_id' => $step->id,
            'current_actor_id' => $admin->account_id,
            'payload_json' => ['sensitive_field' => $fieldValue],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subMinutes(10),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'step_id' => $step->id,
            'actor_id' => $admin->account_id,
            'action_taken' => 'Approved',
            'comments' => '',
            'acted_at' => now()->subMinutes(2),
            'status' => 'Approved',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(2),
            'duration_seconds' => 180,
        ]);

        return Snapshot::create([
            'public_id' => 'snap_'.uniqid(),
            'submission_id' => $submission->id,
            'form_id' => $form->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'workflow_step' => $step->step_name,
            'status' => 'Approved',
            'approved_by' => $admin->account_id,
            'approved_at' => now()->subMinutes(2),
            'comment' => '',
            'payload_json' => [
                'status' => 'Approved',
                'workflow_step' => $step->step_name,
                'form' => ['id' => $form->id, 'code' => $form->form_code, 'name' => $form->form_name, 'version' => 1],
                'submission' => ['id' => $submission->id, 'created_at' => now()->subMinutes(10)->toDateTimeString()],
                'approval' => ['approved_by' => 'Admin User'],
                'fields' => [
                    [
                        'name' => 'sensitive_field',
                        'label' => 'Sensitive Field',
                        'type' => 'text',
                        'value' => $fieldValue,
                        'isFile' => false,
                        'is_sensitive' => $isSensitive,
                        'is_publicly_verifiable' => $isPubliclyVerifiable,
                    ],
                ],
                'attachments' => [],
                'approval_history' => [],
                'is_workflow_complete' => true,
            ],
            'action_hash' => 'hash_'.uniqid(),
            'locked' => true,
            'created_at' => now()->subMinute(),
        ]);
    }

    private function baseFormPayload(array $fields): array
    {
        return [
            'form_name' => 'Masking Form '.uniqid(),
            'description' => 'Field masking test form',
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'submission_limit' => null,
            'permissions' => [],
            'fields' => $fields,
        ];
    }

    private function makeAdminUser(): User
    {
        return $this->makeUserWithPermissions(['forms.manage']);
    }

    private function makeBasicUser(): User
    {
        $user = User::create([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        return $user;
    }

    private function makeUserWithPermissions(array $slugs): User
    {
        $permissionIds = [];
        foreach ($slugs as $slug) {
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

        $role = Role::create(['role_name' => 'Role '.uniqid(), 'description' => 'Test', 'is_active' => true]);
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
