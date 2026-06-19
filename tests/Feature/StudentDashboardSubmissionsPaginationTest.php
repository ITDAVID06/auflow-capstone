<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\Slot;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
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

class StudentDashboardSubmissionsPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_submissions_endpoint_returns_paginated_shape(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);

        $this->actingAs($user)
            ->getJson(route('student-dashboard.submissions'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_submissions_endpoint_validates_per_page_upper_bound(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);

        $this->actingAs($user)
            ->getJson(route('student-dashboard.submissions', ['per_page' => 51]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_student_submission_reads_use_canonical_rows_without_runtime_tables(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);
        $form = $this->createCanonicalForm($user->account_id);

        $approvedSubmission = $this->createCanonicalSubmission($form, $user->account_id, 'Approved', 2);
        $rejectedSubmission = $this->createCanonicalSubmission($form, $user->account_id, 'Rejected', 1);

        DB::table('tbl_submission_attachment')->insert([
            'submission_id' => $approvedSubmission->id,
            'file_path' => 'private/evidence.pdf',
            'original_name' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
            'uploaded_by' => $user->account_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Slot::query()->create([
            'form_id' => $form->id,
            'submission_id' => $approvedSubmission->id,
            'account_id' => $user->account_id,
            'facility_id' => null,
            'date' => '2026-07-01',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Pending',
        ]);

        $this->actingAs($user)
            ->getJson(route('student-dashboard.submissions'))
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $rejectedSubmission->id)
            ->assertJsonPath('data.1.id', $approvedSubmission->id)
            ->assertJsonPath('data.1.attachments', 1)
            ->assertJsonPath('data.1.status_normalized', 'approved');

        $this->actingAs($user)
            ->getJson(route('student-dashboard.metrics'))
            ->assertOk()
            ->assertJson([
                'total' => 2,
                'approved' => 1,
                'pending' => 0,
                'rejected' => 1,
                'revision' => 0,
            ]);
    }

    public function test_student_submission_pagination_uses_db_paginate_and_minimal_queries(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);
        $form = $this->createCanonicalForm($user->account_id);

        for ($i = 0; $i < 25; $i++) {
            $this->createCanonicalSubmission($form, $user->account_id, 'Pending', $i + 1);
        }

        /** @var StudentSubmissionService $service */
        $service = app(StudentSubmissionService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = $service->getPaginatedSubmissionSummaries(
            accountId: (int) $user->account_id,
            status: 'all',
            search: '',
            page: 2,
            perPage: 10,
        );

        $queryCount = count(DB::getQueryLog());

        $this->assertCount(10, $result['data']);
        $this->assertSame(2, $result['meta']['current_page']);
        $this->assertSame(3, $result['meta']['last_page']);
        $this->assertSame(10, $result['meta']['per_page']);
        $this->assertSame(25, $result['meta']['total']);
        $this->assertLessThanOrEqual(8, $queryCount);
    }

    public function test_student_submission_details_include_image_field_options(): void
    {
        $user = $this->createUserWithPermissions(['dashboard.student']);
        $form = Form::query()->create([
            'form_name' => 'Image Detail Form',
            'form_code' => 'IMG'.uniqid(),
            'description' => 'Form with image field',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        FormField::query()->create([
            'form_id' => $form->id,
            'field_name' => 'hero_image',
            'label' => 'Hero Image',
            'data_type' => 'image',
            'is_required' => false,
            'field_order' => 1,
            'field_options' => [
                'image_url' => '/files/form_images/sample.png',
                'image_path' => 'form_images/sample.png',
                'image_alt' => 'Sample image',
            ],
        ]);

        $form = $form->fresh('fields');

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $user->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => [],
            'schema_snapshot_json' => $form->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $details = app(StudentSubmissionService::class)->getSubmissionDetails($form->id, $submission->id, $user->account_id);

        $this->assertNotNull($details);
        $this->assertSame('image', $details['form_fields'][0]['data_type']);
        $this->assertSame('/files/form_images/sample.png', $details['form_fields'][0]['field_options']['image_url'] ?? null);
        $this->assertSame('form_images/sample.png', $details['form_fields'][0]['field_options']['image_path'] ?? null);
        $this->assertSame('Sample image', $details['form_fields'][0]['field_options']['image_alt'] ?? null);
    }

    private function createCanonicalForm(int $accountId): Form
    {
        $form = Form::query()->create([
            'form_name' => 'Canonical Student Form',
            'form_code' => 'CST'.uniqid(),
            'description' => 'Canonical student form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $accountId,
            'is_locked' => true,
        ]);

        FormField::query()->create([
            'form_id' => $form->id,
            'field_name' => 'field_details',
            'label' => 'Details',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        return $form->fresh('fields');
    }

    private function createCanonicalSubmission(Form $form, int $accountId, string $status, int $minutesAgo = 1): FormSubmission
    {
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $accountId,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'payload_json' => ['field_details' => 'Submission '.uniqid()],
            'schema_snapshot_json' => $form->toSchemaArray(),
            'submitted_at' => now()->subMinutes($minutesAgo),
            'is_latest_revision' => true,
        ]);

        $submission->forceFill([
            'root_submission_id' => $submission->id,
        ])->save();

        $workflow = Workflow::query()->firstOrCreate(
            ['form_id' => $form->id, 'status' => 'Active', 'version' => 1],
            [
                'workflow_name' => 'Dashboard WF '.uniqid(),
                'workflow_type' => 'Sequential',
                'description' => 'Test workflow',
                'created_by' => $accountId,
            ]
        );

        $step = WorkflowStep::query()->firstOrCreate(
            ['workflow_id' => $workflow->id, 'step_order' => 1],
            [
                'step_name' => 'Review',
                'step_description' => 'Review step',
                'step_group' => 1,
                'action_type' => 'Approve',
                'assigned_account_id' => $accountId,
            ]
        );

        WorkflowStepProgress::query()->create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'step_id' => $step->id,
            'actor_id' => $accountId,
            'status' => $status,
            'started_at' => now()->subHour(),
            'acted_at' => now()->subMinutes(5),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subMinutes(5),
        ]);

        $submission->forceFill([
            'current_step_id' => $step->id,
            'current_actor_id' => $accountId,
        ])->save();

        return $submission->fresh();
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
