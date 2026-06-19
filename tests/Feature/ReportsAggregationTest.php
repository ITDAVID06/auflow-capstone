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

class ReportsAggregationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_aggregate_count_groups_by_status(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);

        $this->createSubmission($form, $viewer, 'Approved');
        $this->createSubmission($form, $viewer, 'Approved');
        $this->createSubmission($form, $viewer, 'Rejected');

        $response = $this->actingAs($viewer)
            ->getJson(route('reports.aggregate', [
                'form_id' => $form->id,
                'group_by' => 'submission_status',
                'function' => 'count',
            ]))
            ->assertOk()
            ->assertJsonStructure(['data' => [['group_value', 'aggregate_value']]]);

        $data = collect($response->json('data'));

        $approved = $data->firstWhere('group_value', 'Approved');
        $rejected = $data->firstWhere('group_value', 'Rejected');

        $this->assertNotNull($approved);
        $this->assertNotNull($rejected);
        $this->assertEquals(2, $approved['aggregate_value']);
        $this->assertEquals(1, $rejected['aggregate_value']);
    }

    public function test_aggregate_requires_group_by_and_function(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);

        $this->actingAs($viewer)
            ->getJson(route('reports.aggregate', ['form_id' => $form->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['group_by', 'function']);
    }

    public function test_aggregate_rejects_unknown_function(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);

        $this->actingAs($viewer)
            ->getJson(route('reports.aggregate', [
                'form_id' => $form->id,
                'group_by' => 'submission_status',
                'function' => 'median',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['function']);
    }

    public function test_aggregate_is_forbidden_without_permission(): void
    {
        $user = $this->createUserWithPermissions([]);
        $form = $this->createReportForm($user);

        $this->actingAs($user)
            ->getJson(route('reports.aggregate', [
                'form_id' => $form->id,
                'group_by' => 'submission_status',
                'function' => 'count',
            ]))
            ->assertStatus(403);
    }

    public function test_aggregate_is_forbidden_for_unauthenticated_users(): void
    {
        $owner = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($owner);

        $this->getJson(route('reports.aggregate', [
            'form_id' => $form->id,
            'group_by' => 'submission_status',
            'function' => 'count',
        ]))
            ->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createUserWithPermissions(array $slugs): User
    {
        $ids = [];
        foreach ($slugs as $slug) {
            $ids[] = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'permission_name' => ucwords(str_replace(['.', '-'], ' ', $slug)),
                    'description' => 'Test',
                    'resource' => explode('.', $slug)[0] ?? 'test',
                    'action' => explode('.', $slug)[1] ?? 'access',
                ]
            )->id;
        }

        $role = Role::create(['role_name' => 'Role '.uniqid(), 'description' => 'Test', 'is_active' => true]);
        $role->permissions()->sync($ids);

        $user = User::create([
            'username' => 'u_'.uniqid(),
            'email' => 'u_'.uniqid().'@test.com',
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
            'form_name' => 'Form '.uniqid(),
            'form_code' => 'F'.uniqid(),
            'description' => 'Test',
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

    private function createSubmission(Form $form, User $submitter, string $status): FormSubmission
    {
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'payload_json' => ['field_text_name' => 'Value'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subHour(),
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }
}
