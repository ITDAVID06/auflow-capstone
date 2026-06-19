<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Policies\FormPolicy;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required lookup table
        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Create "Manage Forms" permission
        $permission = Permission::firstOrCreate(
            ['slug' => 'forms.manage'],
            ['permission_name' => 'Manage Forms', 'description' => 'Manage forms', 'resource' => 'forms', 'action' => 'manage']
        );

        // Create admin role with the permission
        $role = Role::create(['role_name' => 'FormAdmin', 'description' => 'Can manage forms', 'is_active' => true]);
        $role->permissions()->attach($permission->id);

        // Admin user (has Manage Forms)
        $this->admin = User::create([
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
        UserRole::create([
            'account_id' => $this->admin->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active' => true,
            'assigned_by' => $this->admin->account_id,
        ]);

        // Guest user (no permissions)
        $this->guest = User::create([
            'username' => 'guest',
            'email' => 'guest@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helper: create a form with optional fields
    // -----------------------------------------------------------------------
    private function createForm(array $overrides = [], int $fieldCount = 0): Form
    {
        static $formCounter = 0;
        $formCounter++;

        $form = Form::create(array_merge([
            'form_name' => 'Test Form',
            'form_code' => 'TESTFORM'.$formCounter,
            'description' => 'A test form',
            'version' => 1,
            'status' => 'Inactive',
            'created_by' => $this->admin->account_id,
            'email_notifications' => false,
        ], $overrides));

        for ($i = 0; $i < $fieldCount; $i++) {
            FormField::create([
                'form_id' => $form->id,
                'field_name' => 'field_'.$i,
                'label' => 'Field '.$i,
                'data_type' => 'text',
                'is_required' => false,
                'field_order' => $i,
            ]);
        }

        return $form->fresh();
    }

    // =====================================================================
    // 1. Store form
    // =====================================================================
    public function test_admin_can_create_form(): void
    {
        $payload = [
            'form_name' => 'New Form',
            'description' => 'Description',
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'submission_limit' => null,
            'permissions' => [],
            'fields' => [
                [
                    'field_name' => 'full_name',
                    'label' => 'Full Name',
                    'data_type' => 'text',
                    'is_required' => true,
                    'options' => [],
                    'field_order' => 0,
                    'placeholder' => 'Enter name',
                    'help_text' => 'Your legal full name',
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->post('/forms', $payload)
            ->assertRedirect();

        $form = Form::where('form_name', 'New Form')->firstOrFail();

        $this->assertMatchesRegularExpression('/^AUF-Form-\d{5}$/', (string) $form->form_family_code);
        $this->assertSame($form->form_family_code.' Rev-01', $form->form_code);

        $this->assertDatabaseHas('tbl_formfield', [
            'field_name' => 'full_name',
            'help_text' => 'Your legal full name',
        ]);
    }

    public function test_admin_can_create_form_when_latest_family_is_soft_deleted(): void
    {
        $archivedLatest = $this->createForm([
            'form_name' => 'Archived Latest Family',
            'form_code' => 'AUF-Form-00006 Rev-01',
            'form_family_code' => 'AUF-Form-00006',
        ]);
        $archivedLatest->delete();

        $payload = [
            'form_name' => 'Fresh Form After Archive',
            'description' => 'Description',
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'submission_limit' => null,
            'permissions' => [],
            'fields' => [
                [
                    'field_name' => 'fresh_name',
                    'label' => 'Fresh Name',
                    'data_type' => 'text',
                    'is_required' => true,
                    'options' => [],
                    'field_order' => 0,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->post('/forms', $payload)
            ->assertRedirect();

        $form = Form::where('form_name', 'Fresh Form After Archive')->firstOrFail();

        $this->assertSame('AUF-Form-00007', $form->form_family_code);
        $this->assertSame('AUF-Form-00007 Rev-01', $form->form_code);
    }

    public function test_active_form_creation_locks_form_without_creating_runtime_table(): void
    {
        $payload = [
            'form_name' => 'Active Runtime Form',
            'description' => 'Runtime test',
            'version' => 1,
            'status' => 'Active',
            'email_notifications' => false,
            'permissions' => [],
            'fields' => [
                [
                    'field_name' => 'student_name',
                    'label' => 'Student Name',
                    'data_type' => 'text',
                    'is_required' => true,
                    'options' => [],
                    'field_order' => 0,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->post('/forms', $payload)
            ->assertRedirect();

        $form = Form::where('form_name', 'Active Runtime Form')->firstOrFail();

        $this->assertSame('Active', $form->status);
        $this->assertTrue((bool) $form->is_locked);
        $this->assertNotNull($form->revision_effective_at);
        $this->assertFalse(Schema::hasTable('tbl_form_'.$form->id));
    }

    public function test_guest_cannot_create_form(): void
    {
        $this->actingAs($this->guest)
            ->post('/forms', [
                'form_name' => 'Forbidden',
                'description' => '',
                'version' => 1,
                'status' => 'Inactive',
                'fields' => [],
            ])
            ->assertForbidden();
    }

    // =====================================================================
    // 2. Update form (diff-based field update)
    // =====================================================================
    public function test_update_preserves_existing_fields_and_adds_new(): void
    {
        $form = $this->createForm([], 2);
        $existingField = $form->fields->first();

        $payload = [
            'form_name' => 'Updated Form',
            'description' => 'Updated',
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'submission_limit' => null,
            'permissions' => [],
            'fields' => [
                // Keep existing field (with id)
                [
                    'id' => $existingField->id,
                    'field_name' => $existingField->field_name,
                    'label' => 'Renamed Label',
                    'data_type' => 'text',
                    'is_required' => true,
                    'options' => [],
                    'field_order' => 0,
                ],
                // New field (no id)
                [
                    'field_name' => 'new_field',
                    'label' => 'Brand New',
                    'data_type' => 'number',
                    'is_required' => false,
                    'options' => [],
                    'field_order' => 1,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->put("/forms/{$form->id}", $payload)
            ->assertRedirect();

        // Original field updated
        $this->assertDatabaseHas('tbl_formfield', [
            'id' => $existingField->id,
            'label' => 'Renamed Label',
        ]);

        // New field created
        $this->assertDatabaseHas('tbl_formfield', [
            'form_id' => $form->id,
            'field_name' => 'new_field',
        ]);

        // Second original field was removed (not in payload)
        $this->assertEquals(2, FormField::where('form_id', $form->id)->count());
    }

    public function test_update_persists_image_field_options(): void
    {
        $form = $this->createForm();

        $imageField = FormField::create([
            'form_id' => $form->id,
            'field_name' => 'hero_image',
            'label' => 'Hero Image',
            'data_type' => 'image',
            'is_required' => false,
            'field_order' => 0,
            'field_options' => [
                'image_url' => '',
                'image_alt' => '',
                'image_alignment' => 'center',
                'image_width' => 'medium',
            ],
        ]);

        $payload = [
            'form_name' => 'Updated Form With Image',
            'description' => 'Updated with image options',
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'submission_limit' => null,
            'permissions' => [],
            'fields' => [
                [
                    'id' => $imageField->id,
                    'field_name' => 'hero_image',
                    'label' => 'Hero Image',
                    'data_type' => 'image',
                    'is_required' => false,
                    'options' => [],
                    'field_order' => 0,
                    'field_options' => [
                        'image_url' => '/files/form_images/form_image_example.png',
                        'image_path' => 'form_images/form_image_example.png',
                        'image_alt' => 'Banner',
                        'image_alignment' => 'center',
                        'image_width' => 'medium',
                    ],
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->put("/forms/{$form->id}", $payload)
            ->assertOk();

        $saved = FormField::findOrFail($imageField->id);

        $this->assertIsArray($saved->field_options);
        $this->assertSame('/files/form_images/form_image_example.png', $saved->field_options['image_url'] ?? null);
        $this->assertSame('form_images/form_image_example.png', $saved->field_options['image_path'] ?? null);
    }

    public function test_active_form_cannot_be_updated_in_place_even_when_unlocked(): void
    {
        $form = $this->createForm(['status' => 'Active', 'is_locked' => false], 1);
        $existing = $form->fields()->firstOrFail();

        $this->actingAs($this->admin)
            ->put("/forms/{$form->id}", [
                'form_name' => $form->form_name,
                'description' => $form->description,
                'version' => $form->version,
                'status' => 'Active',
                'email_notifications' => false,
                'permissions' => [],
                'fields' => [
                    [
                        'id' => $existing->id,
                        'field_name' => 'renamed_field',
                        'label' => $existing->label,
                        'data_type' => $existing->data_type,
                        'is_required' => false,
                        'options' => [],
                        'field_order' => 0,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tbl_formfield', [
            'id' => $existing->id,
            'field_name' => $existing->field_name,
        ]);
    }

    public function test_activation_without_active_workflow_is_blocked_without_creating_runtime_table(): void
    {
        $form = $this->createForm(['status' => 'Inactive', 'is_locked' => false], 1);

        $this->actingAs($this->admin)
            ->from('/admin/forms')
            ->patch("/forms/{$form->id}/status", [
                'status' => 'Active',
            ])
            ->assertRedirect('/admin/forms')
            ->assertSessionHas('error', 'Forms can only be activated by publishing or enabling an associated workflow.')
            ->assertSessionMissing('success');

        $this->assertDatabaseHas('tbl_form', [
            'id' => $form->id,
            'status' => 'Inactive',
            'is_locked' => 0,
        ]);
        $this->assertFalse(Schema::hasTable('tbl_form_'.$form->id));
    }

    public function test_duplicate_active_form_creates_next_inactive_version(): void
    {
        $form = $this->createForm([
            'status' => 'Active',
            'is_locked' => true,
            'description' => 'Original description',
            'email_notifications' => true,
            'submission_limit' => 3,
        ], 1);

        $form->permissions()->sync([Permission::firstOrFail()->id]);

        $this->actingAs($this->admin)
            ->post("/admin/forms/{$form->id}/duplicate")
            ->assertRedirect();

        $this->assertFalse(Schema::hasTable('tbl_form_'.$form->id));

        $duplicated = Form::query()
            ->whereKeyNot($form->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($duplicated);
        $this->assertSame('Test Form - Copy', $duplicated->form_name);
        $this->assertSame(1, $duplicated->version);
        $this->assertSame('Inactive', $duplicated->status);
        $this->assertFalse((bool) $duplicated->is_locked);
        $this->assertNotSame($form->form_family_code, $duplicated->form_family_code);
        $this->assertNull($duplicated->parent_form_id);
        $this->assertSame($duplicated->form_family_code.' Rev-01', $duplicated->form_code);
        $this->assertNull($duplicated->description);
        $this->assertFalse((bool) $duplicated->email_notifications);
        $this->assertNull($duplicated->submission_limit);
        $this->assertCount(0, $duplicated->permissions);
        $this->assertDatabaseHas('tbl_formfield', [
            'form_id' => $duplicated->id,
            'field_name' => 'field_0',
        ]);
    }

    public function test_revision_creates_next_version_in_same_family_with_metadata_preserved(): void
    {
        $source = $this->createForm([
            'form_name' => 'Revision Source',
            'form_code' => 'AUF-Form-00042 Rev-01',
            'form_family_code' => 'AUF-Form-00042',
            'description' => 'Original description',
            'version' => 1,
            'status' => 'Active',
            'email_notifications' => true,
            'submission_limit' => 3,
            'is_locked' => true,
        ], 1);

        $latest = $this->createForm([
            'form_name' => 'Revision Source',
            'form_code' => 'AUF-Form-00042 Rev-02',
            'form_family_code' => 'AUF-Form-00042',
            'parent_form_id' => $source->id,
            'description' => 'Original description',
            'version' => 2,
            'status' => 'Active',
            'email_notifications' => true,
            'submission_limit' => 3,
            'is_locked' => true,
        ], 1);

        $permission = Permission::firstOrFail();
        $latest->permissions()->sync([$permission->id]);

        $this->actingAs($this->admin)
            ->post("/admin/forms/{$latest->id}/revise")
            ->assertRedirect();

        $revision = Form::query()
            ->where('form_code', 'AUF-Form-00042 Rev-03')
            ->firstOrFail();

        $this->assertSame('Revision Source', $revision->form_name);
        $this->assertSame('AUF-Form-00042', $revision->form_family_code);
        $this->assertSame(3, $revision->version);
        $this->assertSame($latest->id, $revision->parent_form_id);
        $this->assertSame('Original description', $revision->description);
        $this->assertSame('Inactive', $revision->status);
        $this->assertFalse((bool) $revision->is_locked);
        $this->assertTrue((bool) $revision->email_notifications);
        $this->assertSame(3, $revision->submission_limit);
        $this->assertNull($revision->revision_effective_at);
        $this->assertCount(1, $revision->permissions);
        $this->assertSame($permission->id, $revision->permissions->first()->id);
        $this->assertDatabaseHas('tbl_formfield', [
            'form_id' => $revision->id,
            'field_name' => 'field_0',
        ]);
    }

    public function test_duplicate_uses_next_family_after_soft_deleted_latest_family(): void
    {
        $archivedLatest = $this->createForm([
            'form_name' => 'Archived Latest Copy Family',
            'form_code' => 'AUF-Form-00006 Rev-01',
            'form_family_code' => 'AUF-Form-00006',
        ]);
        $archivedLatest->delete();

        $form = $this->createForm([
            'form_name' => 'Duplicate Source',
            'form_code' => 'AUF-Form-00005 Rev-01',
            'form_family_code' => 'AUF-Form-00005',
        ], 1);

        $this->actingAs($this->admin)
            ->post("/admin/forms/{$form->id}/duplicate")
            ->assertRedirect();

        $duplicated = Form::query()
            ->where('form_name', 'Duplicate Source - Copy')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('AUF-Form-00007', $duplicated->form_family_code);
        $this->assertSame('AUF-Form-00007 Rev-01', $duplicated->form_code);
    }

    public function test_workflow_available_forms_include_inactive_revisions_without_active_workflows(): void
    {
        $activeRevision = $this->createForm([
            'form_name' => 'Retention Form',
            'form_family_code' => 'AUF-Form-00077',
            'form_code' => 'AUF-Form-00077 Rev-01',
            'status' => 'Active',
            'is_locked' => true,
        ]);

        Workflow::create([
            'workflow_name' => 'Retention Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $activeRevision->id,
            'description' => 'Workflow bound to active revision',
            'status' => 'Active',
            'created_by' => $this->admin->account_id,
            'workflow_settings' => [],
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/forms/{$activeRevision->id}/duplicate")
            ->assertRedirect();

        $duplicated = Form::query()
            ->whereKeyNot($activeRevision->id)
            ->latest('id')
            ->firstOrFail();

        $availableForms = app(WorkflowService::class)->getAvailableForms();
        $availableIds = collect($availableForms)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains($activeRevision->id, $availableIds);
        $this->assertContains($duplicated->id, $availableIds);
    }

    // =====================================================================
    // 3. field_options round-trip
    // =====================================================================
    public function test_field_options_whitelist_preserved_on_save(): void
    {
        $payload = [
            'form_name' => 'Options Test',
            'description' => '',
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'permissions' => [],
            'fields' => [
                [
                    'field_name' => 'table_field',
                    'label' => 'My Table',
                    'data_type' => 'table',
                    'is_required' => false,
                    'options' => [],
                    'field_order' => 0,
                    'field_options' => [
                        'table_columns' => [
                            ['id' => 'col1', 'label' => 'Name', 'type' => 'text', 'required' => false],
                        ],
                        'min_rows' => 1,
                        'max_rows' => 5,
                    ],
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->post('/forms', $payload)
            ->assertRedirect();

        $field = FormField::where('field_name', 'table_field')->first();
        $this->assertNotNull($field);
        $this->assertIsArray($field->field_options);
        $this->assertArrayHasKey('table_columns', $field->field_options);
        $this->assertEquals(1, $field->field_options['min_rows']);
    }

    // =====================================================================
    // 4. Conditions round-trip
    // =====================================================================
    public function test_conditions_saved_and_retrieved(): void
    {
        $form = $this->createForm([], 1);
        $existingField = $form->fields->first();

        $payload = [
            'form_name' => $form->form_name,
            'description' => $form->description,
            'version' => $form->version,
            'status' => $form->status,
            'email_notifications' => false,
            'permissions' => [],
            'fields' => [
                [
                    'id' => $existingField->id,
                    'field_name' => $existingField->field_name,
                    'label' => $existingField->label,
                    'data_type' => 'text',
                    'is_required' => false,
                    'options' => [],
                    'field_order' => 0,
                    'conditions' => [
                        [
                            'field_name' => 'other_field',
                            'operator' => 'equals',
                            'value' => 'yes',
                            'action' => 'show',
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->put("/forms/{$form->id}", $payload)
            ->assertRedirect();

        $field = FormField::find($existingField->id);
        $this->assertIsArray($field->conditions);
        $this->assertCount(1, $field->conditions);
        $this->assertEquals('equals', $field->conditions[0]['operator']);
    }

    // =====================================================================
    // 5. SoftDeletes (archive / restore)
    // =====================================================================
    public function test_archive_soft_deletes_form(): void
    {
        $form = $this->createForm(['status' => 'Active', 'is_locked' => true]);

        $this->actingAs($this->admin)
            ->patch("/admin/forms/{$form->id}/archive")
            ->assertRedirect();

        $this->assertSoftDeleted('tbl_form', ['id' => $form->id]);
        $this->assertDatabaseHas('tbl_form', [
            'id' => $form->id,
            'status' => 'Inactive',
        ]);
    }

    public function test_restore_brings_back_archived_form(): void
    {
        $form = $this->createForm();
        $form->delete(); // soft delete

        $this->actingAs($this->admin)
            ->patch("/admin/forms/{$form->id}/restore")
            ->assertRedirect();

        $this->assertDatabaseHas('tbl_form', [
            'id' => $form->id,
            'status' => 'Inactive',
        ]);
        $this->assertNull(Form::find($form->id)->deleted_at);
    }

    public function test_renderable_scope_excludes_archived_forms(): void
    {
        $form = $this->createForm(['status' => 'Active', 'is_locked' => true]);

        $form->forceFill(['status' => 'Inactive'])->save();
        $form->delete();

        $this->assertFalse(Form::renderable()->whereKey($form->id)->exists());
    }

    public function test_archived_forms_are_hidden_from_user_catalog(): void
    {
        $form = $this->createForm(['status' => 'Active', 'is_locked' => true]);

        $studentAccess = Permission::firstOrCreate(
            ['slug' => 'forms.student-access'],
            ['permission_name' => 'Student Form Access', 'description' => 'Access student forms', 'resource' => 'forms', 'action' => 'student-access']
        );

        $role = Role::firstOrFail();
        $role->permissions()->syncWithoutDetaching([$studentAccess->id]);
        $form->permissions()->sync([$studentAccess->id]);

        $this->actingAs($this->admin)
            ->get('/user/forms')
            ->assertOk()
            ->assertSee('Test Form');

        $form->forceFill(['status' => 'Inactive'])->save();
        $form->delete();

        $this->actingAs($this->admin)
            ->get('/user/forms')
            ->assertOk()
            ->assertDontSee('Test Form');
    }

    public function test_schema_array_includes_lineage_metadata(): void
    {
        $form = $this->createForm([
            'form_family_code' => 'AUF-Form-00042',
            'form_code' => 'AUF-Form-00042 Rev-03',
            'version' => 3,
            'revision_effective_at' => '2026-03-12',
        ], 1)->fresh(['fields', 'permissions']);

        $schema = $form->toSchemaArray();

        $this->assertSame('AUF-Form-00042', $schema['form_family_code']);
        $this->assertSame('AUF-Form-00042 Rev-03', $schema['form_code']);
        $this->assertSame(3, $schema['version']);
        $this->assertSame('2026-03-12', $schema['revision_effective_at']);
    }

    public function test_archived_form_relation_remains_available_from_canonical_submission(): void
    {
        $form = $this->createForm(['status' => 'Active', 'is_locked' => true]);

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $this->admin->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => ['field_0' => 'value'],
            'schema_snapshot_json' => $form->fresh('fields', 'permissions')->toSchemaArray(),
            'submitted_at' => now(),
            'root_submission_id' => null,
            'is_latest_revision' => true,
        ]);

        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        $form->forceFill(['status' => 'Inactive'])->save();
        $form->delete();

        $this->assertNotNull($submission->fresh()->form);
        $this->assertSame($form->id, $submission->fresh()->form?->id);
    }

    public function test_force_delete_policy_is_disabled_as_a_purge_guardrail(): void
    {
        $form = $this->createForm();
        $form->delete();

        $archivedForm = Form::withTrashed()->findOrFail($form->id);

        $policy = new FormPolicy;

        $this->assertFalse($policy->forceDelete($this->admin, $archivedForm));
    }

    public function test_form_cannot_be_activated_without_active_workflow(): void
    {
        $form = $this->createForm(['status' => 'Inactive', 'is_locked' => false], 2);

        $this->actingAs($this->admin)
            ->patch("/forms/{$form->id}/status", ['status' => 'Active'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Forms can only be activated by publishing or enabling an associated workflow.');

        $form->refresh();

        $this->assertSame('Inactive', $form->status);
        $this->assertFalse((bool) $form->is_locked);
        $this->assertNull($form->revision_effective_at);
        $this->assertFalse(Schema::hasTable('tbl_form_'.$form->id));
    }

    public function test_publishing_workflow_activates_bound_form(): void
    {
        $form = $this->createForm(['status' => 'Inactive', 'is_locked' => false], 1);

        $workflow = Workflow::create([
            'workflow_name' => 'Publish Sync Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Draft',
            'created_by' => $this->admin->account_id,
            'workflow_settings' => [],
        ]);

        \App\Modules\WorkflowBuilder\Models\WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Approval',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $this->admin->account_id,
        ]);

        app(WorkflowService::class)->publishWorkflow($workflow->id);

        $form->refresh();
        $workflow->refresh();

        $this->assertSame('Active', $workflow->status);
        $this->assertSame('Active', $form->status);
        $this->assertTrue((bool) $form->is_locked);
        $this->assertNotNull($form->revision_effective_at);
    }

    // =====================================================================
    // 6. Draft save
    // =====================================================================
    public function test_draft_save_stores_data(): void
    {
        $form = $this->createForm();

        $draftData = [
            'form_name' => 'Draft Name',
            'fields' => [['field_name' => 'draft_field', 'label' => 'Draft']],
        ];

        $this->actingAs($this->admin)
            ->putJson("/forms/{$form->id}/draft", ['draft_data' => $draftData])
            ->assertOk()
            ->assertJsonStructure(['saved_at']);

        $form->refresh();
        $this->assertIsArray($form->draft_data);
        $this->assertEquals('Draft Name', $form->draft_data['form_name']);
    }

    // =====================================================================
    // 7. Policy enforcement
    // =====================================================================
    public function test_unauthorized_user_cannot_update_form(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->guest)
            ->put("/forms/{$form->id}", [
                'form_name' => 'Hacked',
                'description' => '',
                'version' => 1,
                'status' => 'Inactive',
                'fields' => [],
            ])
            ->assertForbidden();
    }

    public function test_locked_form_cannot_be_updated(): void
    {
        $form = $this->createForm(['is_locked' => true], 1);

        $this->actingAs($this->admin)
            ->put("/forms/{$form->id}", [
                'form_name' => 'Changed',
                'description' => '',
                'version' => 1,
                'status' => 'Inactive',
                'email_notifications' => false,
                'permissions' => [],
                'fields' => [
                    [
                        'field_name' => 'some_field',
                        'label' => 'Some Field',
                        'data_type' => 'text',
                        'is_required' => false,
                        'options' => [],
                        'field_order' => 0,
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_active_form_edit_page_is_forbidden_until_duplicated(): void
    {
        $form = $this->createForm(['status' => 'Active', 'is_locked' => false], 1);

        $this->actingAs($this->admin)
            ->get("/admin/forms/{$form->id}/edit")
            ->assertForbidden();
    }

    // =====================================================================
    // 9. help_text validation
    // =====================================================================
    public function test_help_text_exceeding_max_fails_validation(): void
    {
        $payload = [
            'form_name' => 'Validation Test',
            'description' => '',
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'permissions' => [],
            'fields' => [
                [
                    'field_name' => 'f1',
                    'label' => 'F1',
                    'data_type' => 'text',
                    'is_required' => false,
                    'options' => [],
                    'field_order' => 0,
                    'help_text' => str_repeat('x', 501),
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->post('/forms', $payload)
            ->assertSessionHasErrors('fields.0.help_text');
    }

    // =====================================================================
    // 10. Duplicate field_name validation
    // =====================================================================
    public function test_duplicate_field_names_rejected(): void
    {
        $payload = [
            'form_name' => 'Dup Test',
            'description' => '',
            'version' => 1,
            'status' => 'Inactive',
            'email_notifications' => false,
            'permissions' => [],
            'fields' => [
                [
                    'field_name' => 'same_name',
                    'label' => 'First',
                    'data_type' => 'text',
                    'is_required' => false,
                    'options' => [],
                    'field_order' => 0,
                ],
                [
                    'field_name' => 'same_name',
                    'label' => 'Second',
                    'data_type' => 'text',
                    'is_required' => false,
                    'options' => [],
                    'field_order' => 1,
                ],
            ],
        ];

        $this->actingAs($this->admin)
            ->post('/forms', $payload)
            ->assertSessionHasErrors('fields.1.field_name');
    }

    // =====================================================================
    // 11. Form builder image upload API
    // =====================================================================
    public function test_image_upload_returns_json_validation_error_when_missing_file(): void
    {
        $response = $this->actingAs($this->admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post('/admin/forms/upload-image', []);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_image_upload_stores_file_and_returns_json_success(): void
    {
        Storage::fake('private');

        $file = UploadedFile::fake()->image('builder-image.png', 300, 300);

        $response = $this->actingAs($this->admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post('/admin/forms/upload-image', [
                'image' => $file,
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'url',
                'path',
            ]);

        $path = $response->json('path');
        $url = $response->json('url');

        $this->assertIsString($path);
        $this->assertIsString($url);
        $this->assertStringStartsWith('form_images/', $path);
        $this->assertStringStartsWith('/files/form_images/', $url);
        Storage::disk('private')->assertExists($path);
    }
}
