<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportsAttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Storage::fake('local');
    }

    public function test_user_cannot_download_another_users_attachment(): void
    {
        $userA = $this->createUserWithPermissions(['submissions.view']);
        $userB = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($userA);
        $attachment = $this->createAttachment($form, $userB);

        $this->actingAs($userA)
            ->get(route('reports.attachments.download', $attachment->id))
            ->assertForbidden();
    }

    public function test_user_can_download_own_attachment(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user);
        Storage::disk('local')->put($attachment->file_path, 'dummy content');

        $this->actingAs($user)
            ->get(route('reports.attachments.download', $attachment->id))
            ->assertOk();
    }

    public function test_override_user_can_download_any_attachment(): void
    {
        $owner = $this->createUserWithPermissions(['submissions.view']);
        $overrideUser = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createForm($owner);
        $attachment = $this->createAttachment($form, $owner);
        Storage::disk('local')->put($attachment->file_path, 'dummy content');

        $this->actingAs($overrideUser)
            ->get(route('reports.attachments.download', $attachment->id))
            ->assertOk();
    }

    public function test_user_cannot_preview_another_users_attachment(): void
    {
        $userA = $this->createUserWithPermissions(['submissions.view']);
        $userB = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($userA);
        $attachment = $this->createAttachment($form, $userB);

        $this->actingAs($userA)
            ->get(route('reports.attachments.preview', $attachment->id))
            ->assertForbidden();
    }

    public function test_user_can_preview_own_attachment(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user);
        Storage::disk('local')->put($attachment->file_path, 'dummy content');

        $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id))
            ->assertOk();
    }

    public function test_override_user_can_preview_any_attachment(): void
    {
        $owner = $this->createUserWithPermissions(['submissions.view']);
        $overrideUser = $this->createUserWithPermissions(['submissions.override']);
        $form = $this->createForm($owner);
        $attachment = $this->createAttachment($form, $owner);
        Storage::disk('local')->put($attachment->file_path, 'dummy content');

        $this->actingAs($overrideUser)
            ->get(route('reports.attachments.preview', $attachment->id))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Helpers (reused by ReportsAttachmentMimeAllowlistTest too — copy to that file)
    // -------------------------------------------------------------------------

    protected function createUserWithPermissions(array $permissionSlugs): User
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
            'role_name' => 'Role ' . uniqid(),
            'description' => 'Test role',
            'is_active' => true,
        ]);
        $role->permissions()->sync($permissionIds);

        $user = User::create([
            'username' => 'user_' . uniqid(),
            'email' => 'user_' . uniqid() . '@test.com',
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

    protected function createForm(User $creator): Form
    {
        $form = Form::create([
            'form_name' => 'Form ' . uniqid(),
            'form_code' => 'F' . uniqid(),
            'description' => 'Test form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text',
            'label' => 'Text',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        return $form;
    }

    protected function createSubmission(Form $form, User $submitter): FormSubmission
    {
        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $submitter->account_id,
            'submission_status' => 'pending',
            'current_workflow_status' => 'pending',
            'payload_json' => ['field_text' => 'value'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }

    protected function createAttachment(Form $form, User $owner, string $mimeType = 'image/png', string $filename = 'test.png'): SubmissionAttachment
    {
        $submission = $this->createSubmission($form, $owner);

        return SubmissionAttachment::create([
            'submission_id' => $submission->id,
            'file_path' => 'exports/test/' . uniqid() . '.png',
            'original_name' => $filename,
            'mime_type' => $mimeType,
            'uploaded_by' => $owner->account_id,
        ]);
    }
}
