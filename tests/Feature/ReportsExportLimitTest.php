<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies that the export_limit parameter caps the number of rows
 * written to the CSV export without affecting the paginated table view.
 */
class ReportsExportLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_export_with_limit_caps_csv_data_rows(): void
    {
        config()->set('reports.async_export_threshold', 9999);

        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        // Create 5 submissions
        for ($i = 0; $i < 5; $i++) {
            $this->createReportSubmission($form, $user, 'Approved');
        }

        $response = $this->actingAs($user)->get(route('reports.export-csv', [
            'form_id' => $form->id,
            'export_limit' => 2,
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csvContent = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($csvContent)));

        // 1 header row + 2 data rows = 3 lines
        $this->assertCount(3, $lines, 'CSV must contain exactly 1 header + 2 data rows when export_limit=2.');
    }

    public function test_export_without_limit_returns_all_rows(): void
    {
        config()->set('reports.async_export_threshold', 9999);

        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        for ($i = 0; $i < 4; $i++) {
            $this->createReportSubmission($form, $user, 'Approved');
        }

        $response = $this->actingAs($user)->get(route('reports.export-csv', [
            'form_id' => $form->id,
        ]));

        $response->assertOk();

        $csvContent = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($csvContent)));

        // 1 header + 4 data rows = 5 lines
        $this->assertCount(5, $lines, 'CSV without export_limit must include all data rows.');
    }

    public function test_export_limit_zero_is_rejected_by_validation(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        $this->actingAs($user)
            ->getJson(route('reports.export-csv', [
                'form_id' => $form->id,
                'export_limit' => 0,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['export_limit']);
    }

    public function test_export_limit_above_max_is_rejected_by_validation(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        $this->actingAs($user)
            ->getJson(route('reports.export-csv', [
                'form_id' => $form->id,
                'export_limit' => 99999,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['export_limit']);
    }

    public function test_export_limit_all_is_accepted_and_exports_all_rows(): void
    {
        config()->set('reports.async_export_threshold', 9999);

        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        for ($i = 0; $i < 3; $i++) {
            $this->createReportSubmission($form, $user, 'Approved');
        }

        $response = $this->actingAs($user)->get(route('reports.export-csv', [
            'form_id' => $form->id,
            'export_limit' => 'all',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csvContent = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($csvContent)));

        // 'all' converts to 5000 server-side; all 3 rows are below the cap.
        $this->assertCount(4, $lines, "'all' must export all rows (1 header + 3 data rows).");
    }

    public function test_csv_export_with_selected_columns_returns_only_those_headers(): void
    {
        config()->set('reports.async_export_threshold', 9999);

        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);
        $this->createReportSubmission($form, $user, 'Approved');

        $response = $this->actingAs($user)->get(route('reports.export-csv', [
            'form_id' => $form->id,
            'select' => ['id', 'submission_status'],
        ]));

        $response->assertOk();

        $csvContent = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csvContent))));

        // First line is the header row.
        $headers = str_getcsv($lines[0]);
        $this->assertEqualsCanonicalizing(
            ['Submission ID', 'Submission Status'],
            $headers,
            'CSV must only contain the selected columns.',
        );

        // The column `applicant_name` must NOT be present.
        $this->assertNotContains('Applicant Name', $headers);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

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

    private function createReportForm(User $creator): Form
    {
        $form = Form::create([
            'form_name' => 'Export Limit Test Form '.uniqid(),
            'form_code' => 'ELT'.uniqid(),
            'description' => 'Test form for export limit',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'applicant_name',
            'label' => 'Applicant Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        return $form;
    }

    private function createReportSubmission(Form $form, User $submitter, string $status): FormSubmission
    {
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'payload_json' => ['applicant_name' => 'Test Applicant'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subHour(),
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }
}
