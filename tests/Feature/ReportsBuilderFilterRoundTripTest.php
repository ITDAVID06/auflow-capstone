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
 * Verifies that builder filters (filters[]) are correctly validated,
 * applied at query time, and echoed back in the response.
 *
 * This test suite encodes the PHP-side contract of the filters[] parameter.
 * The critical invariant: PHP must receive filters as indexed arrays of objects
 * (filters[0][column]=x&filters[0][operator]=eq&filters[0][value]=y)
 * not the broken brackets format produced by Inertia's default queryStringArrayFormat.
 */
class ReportsBuilderFilterRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Validation contract
    // ────────────────────────────────────────────────────────────────────────

    public function test_builder_filter_with_valid_column_and_operator_passes_validation(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);
        $this->createReportSubmission($form, $user, 'Approved');

        $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Approved'],
                ],
            ]))
            ->assertOk();
    }

    public function test_builder_filter_missing_value_for_eq_operator_fails_validation(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'submission_status', 'operator' => 'eq', 'value' => ''],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filters.0.value']);
    }

    public function test_builder_filter_is_null_operator_needs_no_value(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);
        $this->createReportSubmission($form, $user, 'Approved');

        $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'submission_status', 'operator' => 'is_null', 'value' => null],
                ],
            ]))
            ->assertOk();
    }

    public function test_builder_filter_contains_operator_on_form_field_passes_validation(): void
    {
        if (\DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('MySQL JSON path operators (payload_json->) are not supported by SQLite.');
        }

        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);
        $this->createReportSubmission($form, $user, 'Approved');

        $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'field_text_name', 'operator' => 'contains', 'value' => 'Alice'],
                ],
            ]))
            ->assertOk();
    }

    public function test_multiple_builder_filters_are_validated_independently(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        // Second filter has an invalid empty value — first filter is fine
        $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Approved'],
                    ['column' => 'submission_status', 'operator' => 'eq', 'value' => ''],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filters.1.value'])
            ->assertJsonMissingValidationErrors(['filters.0.value']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Query execution — filters produce correct row sets
    // ────────────────────────────────────────────────────────────────────────

    public function test_eq_filter_on_submission_status_returns_only_matching_rows(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        $this->createReportSubmission($form, $user, 'Approved');
        $this->createReportSubmission($form, $user, 'Rejected');
        $this->createReportSubmission($form, $user, 'Approved');

        $response = $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Approved'],
                ],
            ]))
            ->assertOk();

        $submissions = $response->json('submissions');
        $this->assertCount(2, $submissions);

        foreach ($submissions as $submission) {
            $this->assertSame('Approved', $submission['submission_status']);
        }
    }

    public function test_neq_filter_on_submission_status_excludes_matching_rows(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        $this->createReportSubmission($form, $user, 'Approved');
        $this->createReportSubmission($form, $user, 'Rejected');
        $this->createReportSubmission($form, $user, 'Pending');

        $response = $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'submission_status', 'operator' => 'neq', 'value' => 'Approved'],
                ],
            ]))
            ->assertOk();

        $submissions = $response->json('submissions');
        $this->assertCount(2, $submissions);

        foreach ($submissions as $submission) {
            $this->assertNotSame('Approved', $submission['submission_status']);
        }
    }

    public function test_contains_filter_on_form_field_returns_matching_rows(): void
    {
        if (\DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('MySQL JSON path operators (payload_json->) are not supported by SQLite.');
        }

        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        $this->createReportSubmission($form, $user, 'Approved', ['field_text_name' => 'Alice Johnson']);
        $this->createReportSubmission($form, $user, 'Approved', ['field_text_name' => 'Bob Smith']);
        $this->createReportSubmission($form, $user, 'Approved', ['field_text_name' => 'Alice Cooper']);

        $response = $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'field_text_name', 'operator' => 'contains', 'value' => 'alice'],
                ],
            ]))
            ->assertOk();

        $submissions = $response->json('submissions');
        $this->assertCount(2, $submissions);

        foreach ($submissions as $submission) {
            $this->assertStringContainsStringIgnoringCase('Alice', (string) ($submission['field_text_name'] ?? ''));
        }
    }

    public function test_combined_filters_apply_as_and_conditions(): void
    {
        if (\DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('MySQL JSON path operators (payload_json->) are not supported by SQLite.');
        }

        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        // Only this one should match both filters
        $this->createReportSubmission($form, $user, 'Approved', ['field_text_name' => 'Alice']);
        $this->createReportSubmission($form, $user, 'Rejected', ['field_text_name' => 'Alice']);
        $this->createReportSubmission($form, $user, 'Approved', ['field_text_name' => 'Bob']);

        $response = $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Approved'],
                    ['column' => 'field_text_name', 'operator' => 'eq', 'value' => 'Alice'],
                ],
            ]))
            ->assertOk();

        $submissions = $response->json('submissions');
        $this->assertCount(1, $submissions);
        $this->assertSame('Approved', $submissions[0]['submission_status']);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Response echo-back contract
    // ────────────────────────────────────────────────────────────────────────

    public function test_applied_filters_are_echoed_back_in_response(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);
        $this->createReportSubmission($form, $user, 'Approved');

        $response = $this->actingAs($user)
            ->getJson(route('reports.form-submissions', [
                'form_id' => $form->id,
                'filters' => [
                    ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Approved'],
                ],
            ]))
            ->assertOk();

        $echoedFilters = $response->json('filters.filters');
        $this->assertIsArray($echoedFilters);
        $this->assertCount(1, $echoedFilters);
        $this->assertSame('submission_status', $echoedFilters[0]['column']);
        $this->assertSame('eq', $echoedFilters[0]['operator']);
        $this->assertSame('Approved', $echoedFilters[0]['value']);
    }

    public function test_builder_capabilities_are_returned_with_filterable_columns(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        $response = $this->actingAs($user)
            ->getJson(route('reports.form-submissions', ['form_id' => $form->id]))
            ->assertOk();

        $builder = $response->json('builder');
        $this->assertIsArray($builder);
        $this->assertArrayHasKey('filterable_columns', $builder);
        $this->assertArrayHasKey('sortable_columns', $builder);
        $this->assertArrayHasKey('operators_by_column', $builder);

        $filterableKeys = array_column($builder['filterable_columns'], 'key');
        $this->assertContains('submission_status', $filterableKeys);
        $this->assertContains('field_text_name', $filterableKeys);

        // Display-only columns must NOT be in filterable columns
        $this->assertNotContains('submitter_name', $filterableKeys);
        $this->assertNotContains('attachments', $filterableKeys);
        $this->assertNotContains('snapshot', $filterableKeys);
    }

    public function test_operators_by_column_contains_ui_compatible_operators_only(): void
    {
        $user = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createReportForm($user);

        $response = $this->actingAs($user)
            ->getJson(route('reports.form-submissions', ['form_id' => $form->id]))
            ->assertOk();

        $operatorsByColumn = $response->json('builder.operators_by_column');
        $this->assertIsArray($operatorsByColumn);

        // The non-UI operators (in, between) should NOT appear in the operators map
        // returned to the frontend (they are backend-only)
        foreach ($operatorsByColumn as $columnKey => $operators) {
            $this->assertNotContains('in', $operators, "Column '{$columnKey}' should not expose 'in' to UI");
            $this->assertNotContains('between', $operators, "Column '{$columnKey}' should not expose 'between' to UI");
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

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
            'form_name' => 'Filter Test Form '.uniqid(),
            'form_code' => 'FLT'.uniqid(),
            'description' => 'Builder filter round-trip test form',
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
    private function createReportSubmission(
        Form $form,
        User $submitter,
        string $status,
        array $payload = ['field_text_name' => 'Sample value'],
    ): FormSubmission {
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
