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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Verifies that RequestFormController::show() includes submission_availability
 * in the Inertia response so the submit button state is correctly driven.
 */
class RequestFormControllerAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Form $form;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $formPermission = Permission::firstOrCreate(
            ['slug' => 'forms.student-access'],
            ['permission_name' => 'Student Form Access', 'description' => 'Access student forms', 'resource' => 'forms', 'action' => 'student-access']
        );

        $this->form = Form::create([
            'form_name' => 'Availability Test Form',
            'form_code' => 'AVT-001 Rev-01',
            'form_family_code' => 'AVT-001',
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

        $this->form->permissions()->sync([$formPermission->id]);

        $this->user = User::create([
            'username' => 'avail_user',
            'email' => 'avail@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $role = Role::create([
            'role_name' => 'Student Role',
            'description' => 'Student role',
            'is_active' => true,
        ]);
        $role->permissions()->attach($formPermission->id);

        UserRole::create([
            'account_id' => $this->user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'expiry_date' => null,
            'is_active' => true,
            'assigned_by' => $this->user->account_id,
        ]);
    }

    public function test_show_includes_submission_availability_in_form_prop(): void
    {
        $this->actingAs($this->user)
            ->withSession(['_token' => csrf_token()])
            ->get("/user/forms/{$this->form->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('form-submission/FormSubmissionPage')
                ->has('form.submission_availability')
                ->has('form.submission_availability.can_submit')
            );
    }

    public function test_show_includes_submission_limit_reached_in_form_prop(): void
    {
        $this->actingAs($this->user)
            ->withSession(['_token' => csrf_token()])
            ->get("/user/forms/{$this->form->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('form.submission_limit_reached')
            );
    }
}
