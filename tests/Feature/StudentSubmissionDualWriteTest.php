<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentSubmissionDualWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_handle_submission_writes_canonical_records_without_runtime_rows(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = $this->createUserWithPermissions(['forms.view']);
        $form = $this->createForm($user->account_id);
        [$workflow, $step] = $this->createActiveWorkflow($form, $user->account_id);

        $this->actingAs($user);

        $attachment = UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf');
        $request = Request::create(
            '/user/forms/'.$form->id.'/submit',
            'POST',
            [
                'field_details' => 'Dual write payload',
                'slots' => [
                    [
                        'date' => '2026-03-12',
                        'start_time' => '09:00',
                        'end_time' => '10:00',
                    ],
                ],
            ],
            [],
            ['attachments' => [$attachment]]
        );

        $notifier = $this->createMock(NotificationService::class);
        $notifier->expects($this->never())->method('notifyFirstSequentialApprovers');
        $notifier->expects($this->never())->method('notifyAllParallelApprovers');

        $service = new StudentSubmissionService($notifier);
        $response = $service->handleSubmission($request, $form->id);

        $this->assertNotNull($response);

        $canonicalSubmission = FormSubmission::query()->where('form_id', $form->id)->first();

        $this->assertNotNull($canonicalSubmission);
        $this->assertSame($form->id, $canonicalSubmission->form_id);
        $this->assertSame($user->account_id, $canonicalSubmission->account_id);
        $this->assertSame($step->id, $canonicalSubmission->current_step_id);
        $this->assertSame($user->account_id, $canonicalSubmission->current_actor_id);
        $this->assertFalse(Schema::hasTable('tbl_form_'.$form->id));

        $this->assertDatabaseHas('tbl_submission_attachment', [
            'submission_id' => $canonicalSubmission->id,
            'original_name' => 'evidence.pdf',
        ]);

        $this->assertDatabaseHas('tbl_slots', [
            'submission_id' => $canonicalSubmission->id,
            'form_id' => $form->id,
            'account_id' => $user->account_id,
            'date' => '2026-03-12 00:00:00',
        ]);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'submission_id' => $canonicalSubmission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'actor_id' => $user->account_id,
            'status' => 'Pending',
        ]);
    }

    public function test_handle_submission_captures_workflow_version_snapshot(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = $this->createUserWithPermissions(['forms.view']);
        $form = $this->createForm($user->account_id);

        // Create the workflow as Draft so publishWorkflow accepts it
        [$workflow, $step] = $this->createDraftWorkflow($form, $user->account_id);

        $this->actingAs($user);

        // Publish it — this creates the version snapshot and transitions the workflow to Active
        $versionId = app(\App\Modules\WorkflowBuilder\Services\WorkflowService::class)->publishWorkflow($workflow->id);

        $request = Request::create(
            '/user/forms/'.$form->id.'/submit',
            'POST',
            [
                'field_details' => 'Testing version capture',
            ],
            [],
            []
        );

        $notifier = $this->createMock(NotificationService::class);
        $notifier->expects($this->never())->method('notifyFirstSequentialApprovers');
        $notifier->expects($this->never())->method('notifyAllParallelApprovers');

        $service = new StudentSubmissionService($notifier);
        $service->handleSubmission($request, $form->id);

        $canonicalSubmission = FormSubmission::query()->where('form_id', $form->id)->first();
        $this->assertNotNull($canonicalSubmission);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'submission_id' => $canonicalSubmission->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $versionId,
            'step_id' => $step->id,
            'actor_id' => $user->account_id,
            'status' => 'Pending',
        ]);
    }

    public function test_handle_submission_persists_mixed_field_payloads_to_canonical_storage(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = $this->createUserWithPermissions(['forms.view']);
        $form = $this->createMixedForm($user->account_id);
        [$workflow, $step] = $this->createActiveWorkflow($form, $user->account_id);

        $this->actingAs($user);

        $attachment = UploadedFile::fake()->create('mixed-proof.pdf', 128, 'application/pdf');
        $request = Request::create(
            '/user/forms/'.$form->id.'/submit',
            'POST',
            [
                'field_details' => 'Need tables and chairs',
                'field_choices' => ['alpha', 'beta'],
                'field_priority' => 'high',
                'field_items' => json_encode([
                    ['value' => 'chairs', 'qty' => 2],
                    ['value' => 'tables', 'qty' => 1],
                ]),
                'date_ranges' => [
                    ['from' => '2026-04-01', 'to' => '2026-04-03'],
                ],
                'slots' => [
                    ['date' => '2026-04-01'],
                ],
            ],
            [],
            ['attachments' => [$attachment]]
        );

        $notifier = $this->createMock(NotificationService::class);
        $notifier->expects($this->never())->method('notifyFirstSequentialApprovers');
        $notifier->expects($this->never())->method('notifyAllParallelApprovers');

        $service = new StudentSubmissionService($notifier);
        $service->handleSubmission($request, $form->id);

        $canonicalSubmission = $this->findCanonicalSubmission($form, 1);
        $payload = $canonicalSubmission->payload_json;

        $this->assertSame('Need tables and chairs', $payload['field_details']);
        $this->assertSame(['alpha', 'beta'], $payload['field_choices']);
        $this->assertSame('high', $payload['field_priority']);
        $this->assertEquals([
            ['value' => 'chairs', 'qty' => 2],
            ['value' => 'tables', 'qty' => 1],
        ], $payload['field_items']);
        $this->assertArrayNotHasKey('field_trip_date', $payload);
        $this->assertEquals([
            ['start_date' => '2026-04-01', 'end_date' => '2026-04-03'],
        ], $payload['date_ranges']);
        $this->assertEquals([
            ['date' => '2026-04-01', 'start_time' => null, 'end_time' => null, 'facility_id' => null],
        ], $payload['slots']);
        $this->assertCount(1, $payload['attachments']);
        $this->assertSame('mixed-proof.pdf', $payload['attachments'][0]['original_name']);

        $this->assertSame($step->id, $canonicalSubmission->current_step_id);
        $this->assertSame($user->account_id, $canonicalSubmission->current_actor_id);
    }

    public function test_update_submission_dual_writes_canonical_revision_and_kept_attachments(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = $this->createUserWithPermissions(['forms.view']);
        $form = $this->createForm($user->account_id);
        [$workflow, $step] = $this->createActiveWorkflow($form, $user->account_id);

        $this->actingAs($user);

        $notifier = $this->createMock(NotificationService::class);
        $notifier->expects($this->once())
            ->method('notifyFirstSequentialApprovers')
            ->with(
                $this->callback(fn ($actualWorkflow): bool => $actualWorkflow instanceof Workflow && (int) $actualWorkflow->id === (int) $workflow->id),
                $this->callback(fn ($val) => is_int($val)),
                $this->callback(fn ($actualForm): bool => $actualForm instanceof Form && (int) $actualForm->id === (int) $form->id),
            );

        $service = new StudentSubmissionService($notifier);

        $createRequest = Request::create(
            '/user/forms/'.$form->id.'/submit',
            'POST',
            [
                'field_details' => 'Original details',
                'slots' => [
                    ['date' => '2026-05-01', 'start_time' => '09:00', 'end_time' => '10:00'],
                ],
            ],
            [],
            ['attachments' => [UploadedFile::fake()->create('original-proof.pdf', 64, 'application/pdf')]]
        );

        $service->handleSubmission($createRequest, $form->id);

        $originalCanonicalSubmission = $this->findCanonicalSubmission($form, 1);
        $originalAttachment = SubmissionAttachment::query()
            ->where('submission_id', $originalCanonicalSubmission->id)
            ->first();

        $this->assertNotNull($originalAttachment);

        WorkflowStepProgress::query()
            ->where('submission_id', $originalCanonicalSubmission->id)
            ->update([
                'status' => 'Rejected',
                'acted_at' => now(),
                'updated_at' => now(),
            ]);

        $updateRequest = Request::create('/user/forms/'.$form->id.'/submit', 'PUT', [
            'field_details' => 'Updated details',
            'slots' => [
                ['date' => '2026-05-02', 'start_time' => '11:00', 'end_time' => '12:00'],
            ],
            'keep_attachments' => json_encode([$originalAttachment->id]),
        ]);

        $response = $service->updateSubmission($updateRequest, $form->id, $originalCanonicalSubmission->id);
        $this->assertNotNull($response);

        $revisedCanonicalSubmission = $this->findCanonicalSubmission($form, 2);

        $this->assertSame($originalCanonicalSubmission->id, $revisedCanonicalSubmission->revision_of);
        $this->assertSame($originalCanonicalSubmission->id, $revisedCanonicalSubmission->root_submission_id);
        $this->assertFalse((bool) $originalCanonicalSubmission->fresh()->is_latest_revision);
        $this->assertTrue((bool) $revisedCanonicalSubmission->is_latest_revision);
        $this->assertSame('Updated details', $revisedCanonicalSubmission->payload_json['field_details']);
        $this->assertCount(1, $revisedCanonicalSubmission->payload_json['attachments']);
        $this->assertSame('original-proof.pdf', $revisedCanonicalSubmission->payload_json['attachments'][0]['original_name']);

        $this->assertDatabaseHas('tbl_submission_attachment', [
            'submission_id' => $revisedCanonicalSubmission->id,
            'original_name' => 'original-proof.pdf',
        ]);

        $this->assertDatabaseHas('tbl_slots', [
            'submission_id' => $originalCanonicalSubmission->id,
            'status' => 'Rejected',
        ]);

        $this->assertDatabaseHas('tbl_slots', [
            'submission_id' => $revisedCanonicalSubmission->id,
            'status' => 'Pending',
        ]);

        $this->assertDatabaseHas('tbl_workflow_step_progress', [
            'submission_id' => $revisedCanonicalSubmission->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'status' => 'Pending',
        ]);
    }

    public function test_handle_submission_enforces_global_submission_limit_from_canonical_rows_without_runtime_rows(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = $this->createUserWithPermissions(['forms.view']);
        $form = $this->createForm($user->account_id);
        $form->forceFill(['submission_limit' => 1])->save();

        $existingSubmission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $user->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => ['field_details' => 'Existing canonical only submission'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subMinute(),
            'is_latest_revision' => true,
        ]);
        $existingSubmission->forceFill(['root_submission_id' => $existingSubmission->id])->save();

        $this->actingAs($user);

        $request = Request::create('/user/forms/'.$form->id.'/submit', 'POST', [
            'field_details' => 'Blocked by canonical limit',
        ]);

        $notifier = $this->createMock(NotificationService::class);
        $notifier->expects($this->never())->method('notifyFirstSequentialApprovers');
        $notifier->expects($this->never())->method('notifyAllParallelApprovers');

        $response = (new StudentSubmissionService($notifier))->handleSubmission($request, $form->id);

        $this->assertNotNull($response);
        $this->assertSame(1, FormSubmission::query()->where('form_id', $form->id)->count());
        $this->assertFalse(Schema::hasTable('tbl_form_'.$form->id));
    }

    private function createForm(int $accountId): Form
    {
        $form = Form::create([
            'form_name' => 'Student Dual Write Form',
            'form_code' => 'DUAL'.uniqid(),
            'description' => 'Test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $accountId,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_details',
            'label' => 'Details',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_date_booking',
            'label' => 'Booking Date',
            'data_type' => 'date',
            'is_required' => false,
            'use_slots' => true,
            'date_mode' => 'single',
            'field_order' => 2,
        ]);

        return $form->fresh('fields');
    }

    private function createMixedForm(int $accountId): Form
    {
        $form = Form::create([
            'form_name' => 'Student Mixed Dual Write Form',
            'form_code' => 'MIXED'.uniqid(),
            'description' => 'Test mixed-field form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $accountId,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_details',
            'label' => 'Details',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_choices',
            'label' => 'Choices',
            'data_type' => 'checkbox',
            'is_required' => false,
            'options' => ['alpha', 'beta', 'gamma'],
            'field_order' => 2,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_priority',
            'label' => 'Priority',
            'data_type' => 'select',
            'is_required' => false,
            'options' => ['low', 'medium', 'high'],
            'field_order' => 3,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_items',
            'label' => 'Items',
            'data_type' => 'table',
            'is_required' => false,
            'field_order' => 4,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_trip_date',
            'label' => 'Trip Date',
            'data_type' => 'date',
            'is_required' => false,
            'date_mode' => 'range',
            'field_order' => 5,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_slot_date',
            'label' => 'Slot Date',
            'data_type' => 'date',
            'is_required' => false,
            'use_slots' => true,
            'date_mode' => 'single',
            'field_order' => 6,
        ]);

        return $form->fresh('fields');
    }

    /**
     * @return array{0: Workflow, 1: WorkflowStep}
     */
    private function createActiveWorkflow(Form $form, int $actorId): array
    {
        $workflow = Workflow::create([
            'workflow_name' => 'Student Submit Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => 'Workflow for dual-write test',
            'status' => 'Active',
            'created_by' => $actorId,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Initial Review',
            'step_description' => 'Review submission',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $actorId,
        ]);

        WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'steps_snapshot' => [[
                'id' => $step->id,
                'step_name' => $step->step_name,
                'step_order' => $step->step_order,
                'step_group' => $step->step_group,
                'action_type' => $step->action_type,
                'assigned_account_id' => $step->assigned_account_id,
            ]],
            'published_at' => now(),
            'published_by' => $actorId,
        ]);

        return [$workflow, $step];
    }

    /**
     * @return array{0: Workflow, 1: WorkflowStep}
     */
    private function createDraftWorkflow(Form $form, int $actorId): array
    {
        $workflow = Workflow::create([
            'workflow_name' => 'Student Submit Workflow (Draft)',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => 'Workflow for version snapshot test',
            'status' => 'Draft',
            'created_by' => $actorId,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Initial Review',
            'step_description' => 'Review submission',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $actorId,
        ]);

        return [$workflow, $step];
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

    private function findCanonicalSubmission(Form $form, int $nthSubmission): FormSubmission
    {
        return FormSubmission::query()
            ->where('form_id', $form->id)
            ->orderBy('id')
            ->skip($nthSubmission - 1)
            ->firstOrFail();
    }
}
