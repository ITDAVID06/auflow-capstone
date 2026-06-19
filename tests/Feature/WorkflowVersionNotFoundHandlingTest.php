<?php

namespace Tests\Feature;

use App\Exceptions\WorkflowVersionNotFoundException;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\StaffDashboard\Services\StaffSubmissionService;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowVersionNotFoundHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_student_submit_catches_workflow_version_not_found_and_redirects_with_error(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = $this->createUserWithPermissions(['dashboard.student']);
        $form = $this->createFormWithNoRequiredFields($user->account_id);

        $this->mock(StudentSubmissionService::class)
            ->shouldReceive('handleSubmission')
            ->once()
            ->andThrow(new WorkflowVersionNotFoundException(
                'No published workflow version found for this form. Please contact your administrator.'
            ));

        $response = $this->actingAs($user)
            ->post(route('student-dashboard.forms.submit', $form->id));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'No published workflow version found for this form. Please contact your administrator.');
    }

    public function test_staff_submit_catches_workflow_version_not_found_and_redirects_with_error(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = $this->createUserWithPermissions(['dashboard.staff']);
        $form = $this->createFormWithNoRequiredFields($user->account_id);

        $this->mock(StaffSubmissionService::class)
            ->shouldReceive('handleSubmission')
            ->once()
            ->andThrow(new WorkflowVersionNotFoundException(
                'No published workflow version found for this form. Please contact your administrator.'
            ));

        $response = $this->actingAs($user)
            ->post(route('staff-dashboard.forms.submit', $form->id));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'No published workflow version found for this form. Please contact your administrator.');
    }

    public function test_user_form_submit_catches_workflow_version_not_found_and_redirects_with_error(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = $this->createUserWithPermissions(['forms.view']);
        $form = $this->createFormWithNoRequiredFields($user->account_id);

        $this->mock(StudentSubmissionService::class)
            ->shouldReceive('handleSubmission')
            ->once()
            ->andThrow(new WorkflowVersionNotFoundException(
                'No published workflow version found for this form. Please contact your administrator.'
            ));

        $response = $this->actingAs($user)
            ->post(route('user.form.submit', $form->id));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'No published workflow version found for this form. Please contact your administrator.');
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

    private function createFormWithNoRequiredFields(int $actorId): Form
    {
        $form = Form::create([
            'form_name' => 'Test Form '.uniqid(),
            'form_code' => 'TF-'.uniqid(),
            'status' => 'Active',
            'version' => 1,
            'is_locked' => true,
            'created_by' => $actorId,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'notes',
            'label' => 'Notes',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 0,
        ]);

        return $form;
    }
}
