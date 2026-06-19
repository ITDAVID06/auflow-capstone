<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\Reports\Models\ReportView;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportsSavedViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Disable CSRF middleware — this environment does not set APP_ENV=testing so
        // VerifyCsrfToken is not auto-bypassed for non-GET requests.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_user_can_create_and_retrieve_saved_view(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $filterState = ['submission_status' => 'Approved', 'filters' => []];

        $this->actingAs($user)
            ->postJson(route('reports.views.store'), [
                'form_id' => $form->id,
                'name' => 'My View',
                'filter_state' => $filterState,
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'My View']);

        $this->actingAs($user)
            ->getJson(route('reports.views.index', ['form_id' => $form->id]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'My View']);
    }

    public function test_filter_state_round_trips_correctly(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $filterState = [
            'submission_status' => 'Pending',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
            'filters' => [
                ['column' => 'submission_status', 'operator' => 'eq', 'value' => 'Pending'],
            ],
        ];

        $createResponse = $this->actingAs($user)
            ->postJson(route('reports.views.store'), [
                'form_id' => $form->id,
                'name' => 'Round Trip',
                'filter_state' => $filterState,
            ])
            ->assertCreated();

        $viewId = $createResponse->json('id');

        $listResponse = $this->actingAs($user)
            ->getJson(route('reports.views.index', ['form_id' => $form->id]))
            ->assertOk();

        $savedFilterState = collect($listResponse->json())->firstWhere('id', $viewId)['filter_state'];

        $this->assertEquals($filterState, $savedFilterState);
    }

    public function test_user_can_update_own_saved_view(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $view = ReportView::create([
            'form_id' => $form->id,
            'name' => 'Old Name',
            'filter_state' => ['submission_status' => 'Approved'],
            'created_by' => $user->account_id,
        ]);

        $this->actingAs($user)
            ->putJson(route('reports.views.update', $view->id), [
                'name' => 'New Name',
                'filter_state' => ['submission_status' => 'Rejected'],
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);
    }

    public function test_user_cannot_update_another_users_view(): void
    {
        $owner = $this->createUserWithPermissions(['submissions.view']);
        $other = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($owner);

        $view = ReportView::create([
            'form_id' => $form->id,
            'name' => 'Owner View',
            'filter_state' => [],
            'created_by' => $owner->account_id,
        ]);

        $this->actingAs($other)
            ->putJson(route('reports.views.update', $view->id), [
                'name' => 'Hijacked',
                'filter_state' => [],
            ])
            ->assertStatus(403);
    }

    public function test_user_can_delete_own_saved_view(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($user);

        $view = ReportView::create([
            'form_id' => $form->id,
            'name' => 'To Delete',
            'filter_state' => [],
            'created_by' => $user->account_id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('reports.views.destroy', $view->id))
            ->assertNoContent();

        $this->assertDatabaseMissing('tbl_report_view', ['id' => $view->id]);
    }

    public function test_user_cannot_delete_another_users_view(): void
    {
        $owner = $this->createUserWithPermissions(['submissions.view']);
        $other = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createReportForm($owner);

        $view = ReportView::create([
            'form_id' => $form->id,
            'name' => 'Owner View',
            'filter_state' => [],
            'created_by' => $owner->account_id,
        ]);

        $this->actingAs($other)
            ->deleteJson(route('reports.views.destroy', $view->id))
            ->assertStatus(403);

        $this->assertDatabaseHas('tbl_report_view', ['id' => $view->id]);
    }

    public function test_unauthenticated_user_cannot_access_views(): void
    {
        $this->getJson(route('reports.views.index', ['form_id' => 1]))
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
}
