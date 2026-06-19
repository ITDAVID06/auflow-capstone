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

class ReportsNestedFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_flat_filter_array_still_works_backward_compat(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);

        $this->createSubmission($form, $viewer, 'Approved', 'Alice');
        $this->createSubmission($form, $viewer, 'Pending', 'Bob');

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Approved'],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'submissions');
    }

    public function test_or_group_filter_returns_union_of_both_branches(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);

        $this->createSubmission($form, $viewer, 'Approved', 'Alice');
        $this->createSubmission($form, $viewer, 'Rejected', 'Bob');
        $this->createSubmission($form, $viewer, 'Pending', 'Charlie');

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    [
                        'logic' => 'or',
                        'filters' => [
                            ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Approved'],
                            ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Rejected'],
                        ],
                    ],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'submissions');
    }

    public function test_and_within_or_group_applies_both_conditions(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);

        // Row matching: Approved AND contains 'Ali'
        $this->createSubmission($form, $viewer, 'Approved', 'Alicia');
        // Row matching status only
        $this->createSubmission($form, $viewer, 'Approved', 'Bob');
        // Row in a sibling OR branch
        $this->createSubmission($form, $viewer, 'Rejected', 'Charlie');

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    [
                        'logic' => 'or',
                        'filters' => [
                            [
                                'logic' => 'and',
                                'filters' => [
                                    ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Approved'],
                                    ['column' => 'field_text_name', 'operator' => 'contains', 'value' => 'Ali'],
                                ],
                            ],
                            ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Rejected'],
                        ],
                    ],
                ],
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'submissions');
    }

    public function test_group_with_unknown_logic_value_is_rejected(): void
    {
        $viewer = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($viewer);

        $this->actingAs($viewer)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    [
                        'logic' => 'xor',
                        'filters' => [
                            ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Approved'],
                        ],
                    ],
                ],
            ]))
            ->assertStatus(422);
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

    private function createSubmission(Form $form, User $submitter, string $status, string $name = 'User'): FormSubmission
    {
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'payload_json' => ['field_text_name' => $name],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subHour(),
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }
}
