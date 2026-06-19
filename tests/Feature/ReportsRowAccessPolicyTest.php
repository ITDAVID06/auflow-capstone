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

class ReportsRowAccessPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_submissions_view_permission_is_limited_to_own_rows(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $otherSubmitter = $this->createUserWithPermissions([]);
        $form = $this->createReportForm($viewer);

        $viewerSubmission = $this->createReportSubmission($form, $viewer, 'Approved', now()->subMinutes(5));
        $this->createReportSubmission($form, $otherSubmitter, 'Approved', now()->subMinutes(10));

        $response = $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
            ]))
            ->assertOk();

        $this->assertSame(1, (int) $response->json('pagination.total'));
        $this->assertCount(1, $response->json('submissions'));
        $this->assertSame($viewerSubmission->id, (int) $response->json('submissions.0.id'));
    }

    public function test_submissions_override_permission_can_view_all_rows_for_form(): void
    {
        $overrideUser = $this->createUserWithPermissions(['submissions.override']);
        $submitterA = $this->createUserWithPermissions([]);
        $submitterB = $this->createUserWithPermissions([]);
        $form = $this->createReportForm($overrideUser);

        $this->createReportSubmission($form, $submitterA, 'Approved', now()->subMinutes(15));
        $this->createReportSubmission($form, $submitterB, 'Approved', now()->subMinutes(20));

        $this->actingAs($overrideUser)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
            ]))
            ->assertOk()
            ->assertJsonPath('pagination.total', 2);
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

    private function createReportSubmission(Form $form, User $submitter, string $status, \DateTimeInterface $submittedAt): FormSubmission
    {
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'payload_json' => ['field_text_name' => 'Row value'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => $submittedAt,
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }
}
