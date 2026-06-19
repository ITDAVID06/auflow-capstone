<?php

namespace Tests\Feature\FormBuilder;

use App\Actions\FormBuilder\WriteCanonicalSubmissionAction;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Requests\StoreFormRequest;
use App\Modules\FormBuilder\Services\FormAuthoringService;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SensitiveFieldsEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $permission = Permission::firstOrCreate(
            ['slug' => 'forms.manage'],
            ['permission_name' => 'Manage Forms', 'description' => 'Manage forms', 'resource' => 'forms', 'action' => 'manage']
        );

        $role = Role::create(['role_name' => 'FormAdmin', 'description' => 'Admin', 'is_active' => true]);
        $role->permissions()->attach($permission->id);

        $this->admin = User::create([
            'username' => 'admin_sensitive',
            'email' => 'admin_sensitive@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'user_status_id' => 1,
        ]);

        UserRole::create([
            'account_id' => $this->admin->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active' => true,
            'assigned_by' => $this->admin->account_id,
        ]);
    }

    public function test_sensitive_fields_column_is_persisted_on_create(): void
    {
        $service = app(FormAuthoringService::class);
        $service->create([
            'form_name' => 'Sensitive Form',
            'form_code' => 'AUF-Form-99999 Rev-01',
            'form_family_code' => 'AUF-Form-99999',
            'description' => null,
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'submission_limit' => null,
            'permissions' => [],
            'sensitive_fields' => ['ssn', 'gpa'],
            'fields' => [
                [
                    'field_name' => 'full_name',
                    'label' => 'Full Name',
                    'data_type' => 'text',
                    'is_required' => true,
                    'options' => [],
                    'field_order' => 0,
                ],
            ],
        ], actorId: (int) $this->admin->account_id);

        $form = Form::where('form_name', 'Sensitive Form')->firstOrFail();
        $this->assertSame(['ssn', 'gpa'], $form->sensitive_fields);
    }

    public function test_sensitive_fields_column_is_persisted_on_update(): void
    {
        $form = Form::create([
            'form_name' => 'Update Test Form',
            'form_code' => 'AUF-Form-88888 Rev-01',
            'form_family_code' => 'AUF-Form-88888',
            'status' => 'Inactive',
            'version' => 1,
            'created_by' => $this->admin->account_id,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'personal_info',
            'label' => 'Personal Info',
            'data_type' => 'text',
            'is_required' => true,
            'field_order' => 0,
        ]);

        $service = app(FormAuthoringService::class);
        $service->update($form, [
            'form_name' => 'Update Test Form',
            'description' => null,
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'submission_limit' => null,
            'permissions' => [],
            'sensitive_fields' => ['bank_account'],
            'fields' => [
                [
                    'id' => $form->fields()->first()->id,
                    'field_name' => 'personal_info',
                    'label' => 'Personal Info',
                    'data_type' => 'text',
                    'is_required' => true,
                    'options' => [],
                    'field_order' => 0,
                ],
            ],
        ]);

        $this->assertSame(['bank_account'], $form->fresh()->sensitive_fields);
    }

    public function test_store_form_request_rules_include_sensitive_fields(): void
    {
        $request = new StoreFormRequest;
        $rules = $request->rules();

        $this->assertArrayHasKey('sensitive_fields', $rules);
        $this->assertArrayHasKey('sensitive_fields.*', $rules);
        $this->assertStringContainsString('nullable', (string) $rules['sensitive_fields']);
        $this->assertStringContainsString('array', (string) $rules['sensitive_fields']);
    }

    public function test_write_canonical_action_stores_payload_as_plain_json(): void
    {
        $form = Form::create([
            'form_name' => 'Plain JSON Form',
            'form_code' => 'AUF-Form-77777 Rev-01',
            'form_family_code' => 'AUF-Form-77777',
            'status' => 'Inactive',
            'version' => 1,
            'created_by' => $this->admin->account_id,
            'sensitive_fields' => ['custom_secret'],
        ]);

        $submission = app(WriteCanonicalSubmissionAction::class)->execute(
            form: $form,
            accountId: (int) $this->admin->account_id,
            payload: ['full_name' => 'John', 'custom_secret' => 'topsecret'],
            schemaSnapshot: [],
        );

        // payload_json is now stored as plain JSON — the value must be readable directly.
        $rawJson = \Illuminate\Support\Facades\DB::table('tbl_form_submission')
            ->where('id', $submission->id)
            ->value('payload_json');

        $this->assertIsString($rawJson);
        $this->assertStringContainsString('topsecret', $rawJson);

        // Round-trip via the model's native array cast must also recover the value.
        $recovered = FormSubmission::findOrFail($submission->id);
        $this->assertSame('topsecret', ($recovered->payload_json)['custom_secret']);
    }
}
