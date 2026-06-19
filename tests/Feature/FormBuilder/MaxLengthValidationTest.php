<?php

namespace Tests\Feature\FormBuilder;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MaxLengthValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_text_field_exceeding_10000_chars_fails_validation(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $user = $this->createUser();
        $form = $this->createFormWithField($user->account_id, 'field_text', 'text');

        $response = $this->actingAs($user)
            ->post(route('user.form.submit', ['id' => $form->id]), [
                'field_text' => str_repeat('a', 10001),
            ]);

        $response->assertSessionHasErrors('field_text');
    }

    public function test_text_field_within_10000_chars_passes_validation(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $user = $this->createUser();
        $form = $this->createFormWithField($user->account_id, 'field_text', 'text', false);

        $response = $this->actingAs($user)
            ->post(route('user.form.submit', ['id' => $form->id]), [
                'field_text' => str_repeat('a', 10000),
            ]);

        $response->assertSessionDoesntHaveErrors('field_text');
    }

    public function test_textarea_field_exceeding_100000_chars_fails_validation(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $user = $this->createUser();
        $form = $this->createFormWithField($user->account_id, 'field_textarea', 'textarea');

        $response = $this->actingAs($user)
            ->post(route('user.form.submit', ['id' => $form->id]), [
                'field_textarea' => str_repeat('a', 100001),
            ]);

        $response->assertSessionHasErrors('field_textarea');
    }

    public function test_email_field_exceeding_10000_chars_fails_validation(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $user = $this->createUser();
        $form = $this->createFormWithField($user->account_id, 'field_email', 'email');

        $response = $this->actingAs($user)
            ->post(route('user.form.submit', ['id' => $form->id]), [
                'field_email' => str_repeat('a', 9990).'@example.com',
            ]);

        $response->assertSessionHasErrors('field_email');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'forms.view'],
            ['permission_name' => 'View Forms', 'description' => 'View forms', 'resource' => 'forms', 'action' => 'view']
        );

        $role = Role::create(['role_name' => 'Viewer_'.uniqid(), 'description' => 'Test', 'is_active' => true]);
        $role->permissions()->sync([$permission->id]);

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

    private function createFormWithField(
        int $actorId,
        string $fieldName,
        string $dataType,
        bool $isRequired = false,
    ): Form {
        $form = Form::create([
            'form_name' => 'MaxLen Form '.uniqid(),
            'form_code' => 'MLF-'.uniqid(),
            'status' => 'Active',
            'version' => 1,
            'is_locked' => true,
            'created_by' => $actorId,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => $fieldName,
            'label' => ucfirst($dataType).' Field',
            'data_type' => $dataType,
            'is_required' => $isRequired,
            'field_order' => 0,
        ]);

        // An active workflow is required to pass the availability check and reach validation
        Workflow::create([
            'form_id' => $form->id,
            'workflow_name' => 'MaxLen Workflow',
            'workflow_type' => 'standard',
            'status' => 'Active',
            'created_by' => $actorId,
        ]);

        return $form->fresh('fields');
    }
}
