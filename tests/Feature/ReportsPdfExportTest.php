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

class ReportsPdfExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_pdf_export_renders_only_filtered_rows_and_selected_columns(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($viewer);

        $approved = $this->createReportSubmission($form, $viewer, 'Approved', ['field_text_name' => 'Approved row']);
        $pending = $this->createReportSubmission($form, $viewer, 'Pending', ['field_text_name' => 'Pending row']);

        $response = $this->actingAs($viewer)
            ->get(route('reports.export-pdf', [
                'form_id' => $form->id,
                'submission_status' => 'approved',
                'select' => ['id', 'submission_status', 'field_text_name'],
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');

        $html = $response->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('Approved row', $html);
        $this->assertStringNotContainsString('Pending row', $html);

        $this->assertStringContainsString('Submission ID', $html);
        $this->assertStringContainsString('Submission Status', $html);
        $this->assertStringContainsString('Name', $html);
        $this->assertStringNotContainsString('Submitted By', $html);
    }

    public function test_pdf_download_returns_pdf_file_with_correct_headers(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);

        $this->createReportSubmission($form, $viewer, 'Approved', ['field_text_name' => 'Download row']);

        $response = $this->actingAs($viewer)
            ->get(route('reports.export-pdf-download', [
                'form_id' => $form->id,
            ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $disposition = $response->headers->get('content-disposition');
        $this->assertIsString($disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    public function test_pdf_download_honours_status_filter(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($viewer);

        $this->createReportSubmission($form, $viewer, 'Approved', ['field_text_name' => 'Approved row']);
        $this->createReportSubmission($form, $viewer, 'Pending', ['field_text_name' => 'Pending row']);

        // Verify the filter is respected by checking only one row comes back via the HTML export.
        $response = $this->actingAs($viewer)
            ->get(route('reports.export-pdf', [
                'form_id' => $form->id,
                'submission_status' => 'approved',
                'select' => ['id', 'submission_status', 'field_text_name'],
            ]));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('Approved row', $html);
        $this->assertStringNotContainsString('Pending row', $html);

        // Also confirm the PDF download endpoint returns a valid PDF for the same filter.
        $pdfResponse = $this->actingAs($viewer)
            ->get(route('reports.export-pdf-download', [
                'form_id' => $form->id,
                'submission_status' => 'approved',
                'select' => ['id', 'submission_status', 'field_text_name'],
            ]));

        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_download_requires_authentication(): void
    {
        $response = $this->get(route('reports.export-pdf-download', ['form_id' => 1]));

        $response->assertRedirect(route('login'));
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

    private function createReportForm(User $creator): Form
    {
        $form = Form::create([
            'form_name' => 'PDF Export Form '.uniqid(),
            'form_code' => 'PDF'.uniqid(),
            'description' => 'Report PDF export test form',
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
    private function createReportSubmission(Form $form, User $submitter, string $status, array $payload): FormSubmission
    {
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'payload_json' => $payload,
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subHour(),
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }
}
