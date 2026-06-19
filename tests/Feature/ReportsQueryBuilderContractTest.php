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

class ReportsQueryBuilderContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_reports_query_rejects_unknown_selected_columns(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);
        $this->createReportSubmission($form, $viewer, 'Approved');

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'select' => ['id', 'definitely_not_a_real_column'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['select.1']);
    }

    public function test_reports_query_rejects_unsupported_filter_operator(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);
        $this->createReportSubmission($form, $viewer, 'Approved');

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    [
                        'column' => 'submission_status',
                        'operator' => 'between',
                        'value' => ['approved', 'rejected'],
                    ],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filters.0.operator']);
    }

    public function test_reports_query_projects_only_selected_columns(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($viewer);
        $this->createReportSubmission($form, $viewer, 'Approved');

        $response = $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'select' => ['id', 'submission_status'],
            ]))
            ->assertOk();

        $this->assertSame(['id', 'submission_status'], array_column($response->json('columns'), 'key'));

        $firstSubmission = $response->json('submissions.0');
        $this->assertIsArray($firstSubmission);
        $this->assertArrayHasKey('id', $firstSubmission);
        $this->assertArrayHasKey('submission_status', $firstSubmission);
        $this->assertArrayNotHasKey('field_text_name', $firstSubmission);
        $this->assertArrayNotHasKey('attachments', $firstSubmission);
    }

    public function test_reports_query_rejects_filter_columns_that_are_not_queryable(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);
        $this->createReportSubmission($form, $viewer, 'Approved');

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    [
                        'column' => 'submitter_name',
                        'operator' => 'contains',
                        'value' => 'viewer',
                    ],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filters.0.column']);
    }

    public function test_reports_query_rejects_sort_columns_that_are_not_queryable(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);
        $this->createReportSubmission($form, $viewer, 'Approved');

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'sort' => [
                    'column' => 'submitter_name',
                    'direction' => 'asc',
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort.column']);
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

    private function createReportSubmission(Form $form, User $submitter, string $status): FormSubmission
    {
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'payload_json' => ['field_text_name' => 'Sample value'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subHour(),
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }
}
