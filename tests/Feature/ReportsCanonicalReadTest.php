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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportsCanonicalReadTest extends TestCase
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
     * @return array{form: Form, submission: FormSubmission}
     */
    public function test_reports_api_reads_canonical_submissions_without_runtime_rows(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $fixture = $this->createCanonicalReportFixture($viewer);

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', ['form_id' => $fixture['form']->id]))
            ->assertOk()
            ->assertJsonPath('submissions.0.id', $fixture['submission']->id)
            ->assertJsonPath('submissions.0.submitter_name', 'Report Viewer')
            ->assertJsonPath('submissions.0.workflow_status', 'Approved')
            ->assertJsonPath('submissions.0.field_text_name', 'Report Value')
            ->assertJsonPath('submissions.0.snapshot.public_id', 'report-snapshot');
    }

    public function test_reports_api_applies_filters_and_returns_summary_and_pagination(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $alice = $this->createUserWithPermissions([]);
        $bob = $this->createUserWithPermissions([]);

        $this->seedUserProfile($viewer, 'Report', 'Viewer');
        $this->seedUserProfile($alice, 'Alice', 'Walker');
        $this->seedUserProfile($bob, 'Bob', 'Stone');

        $form = $this->createReportForm($viewer);

        $this->createReportSubmission($form, $alice, 'Pending', CarbonImmutable::now()->subDays(3), ['field_text_name' => 'Pending from Alice']);
        $this->createReportSubmission($form, $bob, 'Approved', CarbonImmutable::now()->subDays(2), ['field_text_name' => 'Approved from Bob']);
        $approvedByAlice = $this->createReportSubmission($form, $alice, 'Approved', CarbonImmutable::now()->subDay(), ['field_text_name' => 'Approved from Alice']);

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'submission_status' => 'approved',
                'submitter' => 'alice',
                'date_from' => CarbonImmutable::now()->subDays(2)->toDateString(),
                'date_to' => CarbonImmutable::now()->toDateString(),
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('summary.total_submissions', 1)
            ->assertJsonPath('summary.status_counts.approved', 1)
            ->assertJsonPath('summary.status_counts.pending', 0)
            ->assertJsonPath('submissions.0.id', $approvedByAlice->id)
            ->assertJsonPath('submissions.0.submitter_name', 'Alice Walker')
            ->assertJsonPath('submissions.0.submission_status', 'Approved')
            ->assertJsonPath('filters.submission_status', 'approved')
            ->assertJsonPath('filters.submitter', 'alice');
    }

    public function test_reports_export_uses_active_filters(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $submitter = $this->createUserWithPermissions([]);

        $this->seedUserProfile($viewer, 'Report', 'Viewer');
        $this->seedUserProfile($submitter, 'Export', 'User');

        $form = $this->createReportForm($viewer);

        $pending = $this->createReportSubmission($form, $submitter, 'Pending', CarbonImmutable::now()->subHours(3), ['field_text_name' => 'Pending export row']);
        $approved = $this->createReportSubmission($form, $submitter, 'Approved', CarbonImmutable::now()->subHour(), ['field_text_name' => 'Approved export row']);

        $response = $this->actingAs($viewer)
            ->get(route('reports.export-csv', [
                'form_id' => $form->id,
                'submission_status' => 'approved',
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString((string) $approved->id, $csv);
        $this->assertStringContainsString('Approved export row', $csv);
        $this->assertStringNotContainsString("\n{$pending->id},", $csv);
        $this->assertStringNotContainsString('Pending export row', $csv);
    }

    public function test_reports_api_builds_status_summary_with_subquery_shape_for_strict_group_by(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $submitter = $this->createUserWithPermissions([]);

        $this->seedUserProfile($viewer, 'Report', 'Viewer');
        $this->seedUserProfile($submitter, 'Summary', 'User');

        $form = $this->createReportForm($viewer);
        $this->createReportSubmission($form, $submitter, 'Pending', CarbonImmutable::now()->subHour(), ['field_text_name' => 'Summary row']);

        $queries = [];
        DB::listen(function ($event) use (&$queries): void {
            $queries[] = $event->sql;
        });

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', ['form_id' => $form->id]))
            ->assertOk();

        $summaryQuery = collect($queries)
            ->first(function ($sql): bool {
                $normalized = strtolower($sql);

                return str_contains($normalized, 'status_key') && str_contains($normalized, 'count(*)');
            });

        $this->assertNotNull($summaryQuery);
        $this->assertStringContainsStringIgnoringCase('from (select', (string) $summaryQuery);
    }

    public function test_reports_api_handles_nested_option_payload_values_without_throwing(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $submitter = $this->createUserWithPermissions([]);

        $this->seedUserProfile($viewer, 'Report', 'Viewer');
        $this->seedUserProfile($submitter, 'Nested', 'Payload');

        $form = $this->createReportForm($viewer);
        $field = FormField::query()->where('form_id', $form->id)->firstOrFail();
        $field->update(['data_type' => 'select']);

        $this->createReportSubmission(
            $form,
            $submitter,
            'Approved',
            CarbonImmutable::now()->subMinutes(20),
            [
                'field_text_name' => [
                    [
                        'meta' => [
                            'nested' => 'value',
                            'secondary' => ['x', 'y'],
                        ],
                    ],
                ],
            ]
        );

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', ['form_id' => $form->id]))
            ->assertOk()
            ->assertJsonPath('submissions.0.field_text_name', 'nested: value, secondary: x, y');
    }

    public function test_reports_api_accepts_contains_operator_for_submission_status_builder_filters(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $submitter = $this->createUserWithPermissions([]);

        $this->seedUserProfile($viewer, 'Report', 'Viewer');
        $this->seedUserProfile($submitter, 'Builder', 'User');

        $form = $this->createReportForm($viewer);

        $approved = $this->createReportSubmission(
            $form,
            $submitter,
            'Approved',
            CarbonImmutable::now()->subMinutes(15),
            ['field_text_name' => 'Approved row']
        );

        $this->createReportSubmission(
            $form,
            $submitter,
            'Rejected',
            CarbonImmutable::now()->subMinutes(10),
            ['field_text_name' => 'Rejected row']
        );

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    [
                        'column' => 'submission_status',
                        'operator' => 'contains',
                        'value' => 'prov',
                    ],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('submissions.0.id', $approved->id)
            ->assertJsonPath('submissions.0.submission_status', 'Approved');
    }

    /**
     * @return array{form: Form, submission: FormSubmission}
     */
    private function createCanonicalReportFixture(User $viewer): array
    {
        DB::table('tbl_userprofile')->insertOrIgnore([
            [
                'account_id' => $viewer->account_id,
                'first_name' => 'Report',
                'last_name' => 'Viewer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $form = Form::create([
            'form_name' => 'Report Canonical Form '.uniqid(),
            'form_code' => 'RPT'.uniqid(),
            'description' => 'Report test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $viewer->account_id,
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
            'workflow_name' => 'Report Workflow '.uniqid(),
            'workflow_type' => 'Sequential',
            'version' => 1,
            'effective_from' => now(),
            'effective_to' => null,
            'form_id' => $form->id,
            'description' => null,
            'workflow_settings' => null,
            'status' => 'Active',
            'created_by' => $viewer->account_id,
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Report Approval',
            'step_description' => null,
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $viewer->account_id,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $viewer->account_id,
            'submission_status' => 'Approved',
            'current_workflow_status' => 'Approved',
            'current_step_id' => $step->id,
            'current_actor_id' => $viewer->account_id,
            'payload_json' => ['field_text_name' => 'Report Value'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subHour(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'step_id' => $step->id,
            'actor_id' => $viewer->account_id,
            'action_taken' => 'Approved',
            'comments' => null,
            'acted_at' => now()->subMinutes(30),
            'status' => 'Approved',
            'started_at' => now()->subMinutes(45),
            'completed_at' => now()->subMinutes(30),
            'duration_seconds' => 900,
        ]);

        Snapshot::create([
            'public_id' => 'report-snapshot',
            'submission_id' => $submission->id,
            'form_id' => $form->id,
            'workflow_id' => $workflow->id,
            'step_id' => $step->id,
            'workflow_step' => $step->step_name,
            'status' => 'Approved',
            'approved_by' => $viewer->account_id,
            'approved_at' => now()->subMinutes(30),
            'comment' => null,
            'payload_json' => ['form' => ['id' => $form->id]],
            'action_hash' => 'report-hash',
            'locked' => true,
            'created_at' => now()->subMinutes(29),
        ]);

        return [
            'form' => $form,
            'submission' => $submission,
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

    private function seedUserProfile(User $user, string $firstName, string $lastName): void
    {
        DB::table('tbl_userprofile')->insertOrIgnore([
            'account_id' => $user->account_id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createReportForm(User $creator): Form
    {
        $form = Form::create([
            'form_name' => 'Report Form '.uniqid(),
            'form_code' => 'RPT'.uniqid(),
            'description' => 'Report test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
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

        return $form;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createReportSubmission(Form $form, User $submitter, string $status, CarbonImmutable $submittedAt, array $payload): FormSubmission
    {
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'payload_json' => $payload,
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => $submittedAt,
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }
}
