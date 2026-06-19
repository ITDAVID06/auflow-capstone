<?php

namespace Tests\Feature\FormBuilder;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Object-level authorization for the three form "view" endpoints.
 *
 * The route middleware only verifies the user is authenticated and holds the
 * dashboard permission. These tests verify that the object-level check
 * (FormPolicy::viewAsSubmitter) is also enforced, including role expiry.
 */
class FormViewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Form $form;

    /** Permission that the form requires */
    private Permission $formPermission;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->formPermission = Permission::firstOrCreate(
            ['slug' => 'forms.student-access'],
            ['permission_name' => 'Student Form Access', 'description' => 'Access student forms', 'resource' => 'forms', 'action' => 'student-access']
        );

        $this->form = Form::create([
            'form_name' => 'Access Controlled Form',
            'form_code' => 'ACF-001 Rev-01',
            'form_family_code' => 'ACF-001',
            'status' => 'Active',
            'is_locked' => true,
            'version' => 1,
            'created_by' => 1,
        ]);

        FormField::create([
            'form_id' => $this->form->id,
            'field_name' => 'name',
            'label' => 'Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 0,
        ]);

        $this->form->permissions()->sync([$this->formPermission->id]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Create a user whose roles carry the given permission slugs. */
    private function makeUser(array $permissionSlugs, ?string $expiry = null): User
    {
        static $n = 0;
        $n++;

        $user = User::create([
            'username' => 'user_'.$n,
            'email' => 'user_'.$n.'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['permission_name' => $slug, 'description' => $slug, 'resource' => 'test', 'action' => 'test']
            );

            $role = Role::create([
                'role_name' => 'Role-'.$slug.'-'.$n,
                'description' => 'Auto role',
                'is_active' => true,
            ]);
            $role->permissions()->attach($permission->id);

            UserRole::create([
                'account_id' => $user->account_id,
                'role_id' => $role->id,
                'assigned_date' => now()->toDateString(),
                'expiry_date' => $expiry,
                'is_active' => true,
                'assigned_by' => $user->account_id,
            ]);
        }

        return $user;
    }

    // =========================================================================
    // RequestFormController::show  →  GET /user/forms/{id}
    // =========================================================================

    public function test_user_forms_show_allows_user_with_form_permission(): void
    {
        $user = $this->makeUser(['forms.student-access']);

        $this->actingAs($user)
            ->get("/user/forms/{$this->form->id}")
            ->assertOk();
    }

    public function test_user_forms_show_blocks_user_without_form_permission(): void
    {
        $user = $this->makeUser([]); // no permissions at all

        $this->actingAs($user)
            ->get("/user/forms/{$this->form->id}")
            ->assertForbidden();
    }

    public function test_user_forms_show_blocks_user_with_expired_role(): void
    {
        $user = $this->makeUser(['forms.student-access'], now()->subDay()->toDateString());

        $this->actingAs($user)
            ->get("/user/forms/{$this->form->id}")
            ->assertForbidden();
    }

    // =========================================================================
    // StudentDashboardController::viewForm  →  GET /student-dashboard/forms/{id}
    // =========================================================================

    public function test_student_dashboard_view_form_allows_user_with_both_permissions(): void
    {
        // dashboard.student to pass route middleware + forms.student-access for the form
        $user = $this->makeUser(['dashboard.student', 'forms.student-access']);

        $this->actingAs($user)
            ->get("/student-dashboard/forms/{$this->form->id}")
            ->assertOk();
    }

    public function test_student_dashboard_view_form_blocks_user_without_form_permission(): void
    {
        // Has dashboard access but NOT the form-specific permission
        $user = $this->makeUser(['dashboard.student']);

        $this->actingAs($user)
            ->get("/student-dashboard/forms/{$this->form->id}")
            ->assertForbidden();
    }

    public function test_student_dashboard_view_form_blocks_user_with_expired_form_permission(): void
    {
        // dashboard.student is active; form permission role is expired
        $user = $this->makeUser(['dashboard.student']);

        // Attach an expired role that carries forms.student-access
        $role = Role::create(['role_name' => 'ExpiredFormRole-S', 'description' => '', 'is_active' => true]);
        $role->permissions()->attach($this->formPermission->id);
        UserRole::create([
            'account_id' => $user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->subMonth()->toDateString(),
            'expiry_date' => now()->subDay()->toDateString(), // EXPIRED
            'is_active' => true,
            'assigned_by' => $user->account_id,
        ]);

        $this->actingAs($user)
            ->get("/student-dashboard/forms/{$this->form->id}")
            ->assertForbidden();
    }

    // =========================================================================
    // StaffDashboardController::viewForm  →  GET /staff-dashboard/forms/{id}
    // =========================================================================

    public function test_staff_dashboard_view_form_allows_user_with_both_permissions(): void
    {
        $user = $this->makeUser(['dashboard.staff', 'forms.student-access']);

        $this->actingAs($user)
            ->get("/staff-dashboard/forms/{$this->form->id}")
            ->assertOk();
    }

    public function test_staff_dashboard_view_form_blocks_user_without_form_permission(): void
    {
        $user = $this->makeUser(['dashboard.staff']);

        $this->actingAs($user)
            ->get("/staff-dashboard/forms/{$this->form->id}")
            ->assertForbidden();
    }

    public function test_staff_dashboard_view_form_blocks_user_with_expired_form_permission(): void
    {
        $user = $this->makeUser(['dashboard.staff']);

        $role = Role::create(['role_name' => 'ExpiredFormRole-T', 'description' => '', 'is_active' => true]);
        $role->permissions()->attach($this->formPermission->id);
        UserRole::create([
            'account_id' => $user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->subMonth()->toDateString(),
            'expiry_date' => now()->subDay()->toDateString(), // EXPIRED
            'is_active' => true,
            'assigned_by' => $user->account_id,
        ]);

        $this->actingAs($user)
            ->get("/staff-dashboard/forms/{$this->form->id}")
            ->assertForbidden();
    }
}
