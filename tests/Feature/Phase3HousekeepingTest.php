<?php

namespace Tests\Feature;

use App\Actions\FormBuilder\DuplicateFormAction;
use App\Actions\FormBuilder\ReviseFormAction;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF for non-GET requests (project-wide test convention)
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $permission = Permission::firstOrCreate(
            ['slug' => 'forms.manage'],
            ['permission_name' => 'Manage Forms', 'description' => 'Manage forms', 'resource' => 'forms', 'action' => 'manage']
        );

        $role = Role::create(['role_name' => 'FormAdmin', 'description' => 'Can manage forms', 'is_active' => true]);
        $role->permissions()->attach($permission->id);

        $this->admin = User::create([
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
            'email_verified_at' => now(),
        ]);
        UserRole::create([
            'account_id' => $this->admin->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active' => true,
            'assigned_by' => $this->admin->account_id,
        ]);
    }

    // -----------------------------------------------------------------------
    // M9 – Image delete must reject paths containing ".."
    // -----------------------------------------------------------------------

    public function test_image_delete_rejects_path_with_double_dot(): void
    {
        Storage::fake('private');

        $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->delete(route('admin.forms.delete-image'), [
                'path' => 'form_images/../../config/app.php',
            ])
            ->assertStatus(422);
    }

    public function test_image_delete_rejects_path_with_encoded_traversal(): void
    {
        Storage::fake('private');

        $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->delete(route('admin.forms.delete-image'), [
                'path' => 'form_images/../etc/passwd',
            ])
            ->assertStatus(422);
    }

    public function test_image_delete_accepts_valid_form_images_path(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('form_images/form_image_abc123.png', 'fake');

        $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->delete(route('admin.forms.delete-image'), [
                'path' => 'form_images/form_image_abc123.png',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_image_delete_rejects_path_outside_form_images(): void
    {
        Storage::fake('private');

        $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->delete(route('admin.forms.delete-image'), [
                'path' => 'other_folder/secret.txt',
            ])
            ->assertStatus(400);
    }

    private static int $formSeq = 0;

    private function createForm(array $overrides = []): Form
    {
        self::$formSeq++;

        return Form::create(array_merge([
            'form_name' => 'Test Form '.self::$formSeq,
            'form_code' => 'AUF-P3TEST-'.self::$formSeq,
            'form_family_code' => 'AUF-P3FAM-'.self::$formSeq,
            'version' => 1,
            'status' => 'Inactive',
            'is_locked' => false,
            'email_notifications' => false,
            'created_by' => $this->admin->account_id,
        ], $overrides));
    }

    // -----------------------------------------------------------------------
    // M1 – DuplicateFormAction creates a copy with a unique name
    // -----------------------------------------------------------------------

    public function test_duplicate_form_action_creates_copy_with_copy_suffix(): void
    {
        $original = $this->createForm(['form_name' => 'My Form']);

        $this->actingAs($this->admin);
        $copy = app(DuplicateFormAction::class)->execute($original);

        $this->assertSame('My Form - Copy', $copy->form_name);
        $this->assertSame(1, $copy->version);
        $this->assertSame('Inactive', $copy->status);
        $this->assertNotSame($original->id, $copy->id);
        $this->assertNotSame($original->form_family_code, $copy->form_family_code);
    }

    public function test_duplicate_form_action_avoids_name_collision(): void
    {
        // Pre-create the "Report - Copy" name to cause collision
        $this->createForm(['form_name' => 'Report - Copy']);

        $original = $this->createForm(['form_name' => 'Report']);

        $this->actingAs($this->admin);
        $copy = app(DuplicateFormAction::class)->execute($original);

        $this->assertSame('Report - Copy 2', $copy->form_name);
    }

    // -----------------------------------------------------------------------
    // M1 – ReviseFormAction archives siblings and creates new version
    // -----------------------------------------------------------------------

    public function test_revise_form_action_increments_version_and_archives_original(): void
    {
        self::$formSeq++;
        $familyCode = 'AUF-P3REV-'.self::$formSeq;

        $original = Form::create([
            'form_name' => 'Annual Report',
            'form_code' => $familyCode.'-v1',
            'form_family_code' => $familyCode,
            'version' => 1,
            'status' => 'Inactive',
            'is_locked' => false,
            'email_notifications' => false,
            'created_by' => $this->admin->account_id,
        ]);

        $this->actingAs($this->admin);
        $revision = app(ReviseFormAction::class)->execute($original);

        $this->assertSame(2, $revision->version);
        $this->assertSame($familyCode, $revision->form_family_code);
        $this->assertSame('Inactive', $revision->status);
        $this->assertSoftDeleted('tbl_form', ['id' => $original->id]);
    }

    // -----------------------------------------------------------------------
    // M2 – FormRequests validate fields correctly
    // -----------------------------------------------------------------------

    public function test_update_form_status_rejects_invalid_status(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(route('forms.updateStatus', $form->id), ['status' => 'Deleted'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_save_form_draft_rejects_missing_draft_data(): void
    {
        $form = $this->createForm();

        $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->put(route('forms.draft', $form->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['draft_data']);
    }

    // -----------------------------------------------------------------------
    // M3 – Authorization enforced via route middleware (can() gate)
    // -----------------------------------------------------------------------

    public function test_unauthorized_user_cannot_archive_form(): void
    {
        $noPerms = User::create([
            'username' => 'noauth',
            'email' => 'noauth@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
            'email_verified_at' => now(),
        ]);

        $form = $this->createForm();

        $this->actingAs($noPerms)
            ->patch(route('admin.forms.archive', $form->id))
            ->assertStatus(403);
    }

    // -----------------------------------------------------------------------
    // M11 – Metrics query returns correct counts
    // -----------------------------------------------------------------------

    public function test_form_index_returns_correct_metrics(): void
    {
        // 2 active, 3 inactive non-archived
        for ($i = 0; $i < 2; $i++) {
            $this->createForm(['status' => 'Active']);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->createForm(['status' => 'Inactive']);
        }
        // 1 archived (soft-deleted)
        $archived = $this->createForm(['status' => 'Inactive']);
        $archived->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.forms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('metrics.total', 5)
                ->where('metrics.active', 2)
                ->where('metrics.inactive', 3)
                ->where('metrics.archived', 1)
            );
    }
}
