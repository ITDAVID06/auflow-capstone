# Reports Backend Security & Correctness Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 6 security/correctness bugs and add 1 new artisan command in the Reports backend, with tests for each.

**Architecture:** All changes are confined to `app/Modules/Reports/`. A shared `ValidatesFilterState` trait extracts the repeated FormField validation logic used across three request classes. No new routes, no schema changes.

**Tech Stack:** Laravel 11, PHP 8.3, PHPUnit, RefreshDatabase

---

## File Map

- Create: `app/Modules/Reports/Requests/Concerns/ValidatesFilterState.php`
- Create: `app/Console/Commands/CleanupAsyncExports.php`
- Create: `tests/Feature/ReportsAttachmentAccessTest.php`
- Create: `tests/Feature/ReportsAttachmentMimeAllowlistTest.php`
- Create: `tests/Feature/ReportsScheduledExportFilterStateValidationTest.php`
- Create: `tests/Feature/ReportsMonthlyFrequencyTest.php`
- Create: `tests/Feature/ReportsCleanupCommandTest.php`
- Modify: `app/Modules/Reports/Controllers/ReportsController.php`
- Modify: `app/Modules/Reports/Requests/StoreScheduledExportRequest.php`
- Modify: `app/Modules/Reports/Requests/UpdateScheduledExportRequest.php`
- Modify: `app/Modules/Reports/Requests/ReportsFilterRequest.php`
- Modify: `app/Modules/Reports/Services/ScheduledExportService.php`
- Modify: `app/Modules/Reports/Services/ReportSummaryService.php`
- Modify: `bootstrap/app.php`

---

## Task 1: IDOR fix for `downloadAttachment`

**Files:**
- Create: `tests/Feature/ReportsAttachmentAccessTest.php`
- Modify: `app/Modules/Reports/Controllers/ReportsController.php`

- [ ] **Step 1.1: Write the failing test**

```php
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

    private function createForm(User $creator): Form
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

    private function createSubmission(Form $form, User $submitter): FormSubmission
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
```

- [ ] **Step 1.2: Run to confirm it fails**

```bash
php artisan test tests/Feature/ReportsAttachmentAccessTest.php --filter=test_user_cannot_download_another_users_attachment
```

Expected: FAIL — the method currently has no ownership check, so any user gets a 200.

- [ ] **Step 1.3: Add ownership check to `downloadAttachment`**

In `app/Modules/Reports/Controllers/ReportsController.php`, replace the `downloadAttachment` method:

```php
/**
 * Download submission attachment.
 *
 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
 */
public function downloadAttachment(int $id)
{
    $attachment = SubmissionAttachment::findOrFail($id);

    $submission = $attachment->canonicalSubmission;
    $user = auth()->user();

    if (! $submission || ($submission->account_id !== $user->account_id && ! $user->hasPermission('submissions.override'))) {
        abort(403);
    }

    if (! Storage::disk('local')->exists($attachment->file_path)) {
        abort(404, 'File not found');
    }

    $fullPath = Storage::disk('local')->path($attachment->file_path);

    return Response::download($fullPath, $attachment->original_name, [
        'Content-Type' => $attachment->mime_type,
        'X-Content-Type-Options' => 'nosniff',
    ]);
}
```

- [ ] **Step 1.4: Run all attachment access tests**

```bash
php artisan test tests/Feature/ReportsAttachmentAccessTest.php
```

Expected: The download tests pass. Preview tests still fail (previewAttachment not yet fixed).

- [ ] **Step 1.5: Commit**

```bash
git add app/Modules/Reports/Controllers/ReportsController.php tests/Feature/ReportsAttachmentAccessTest.php
git commit -m "fix(security): add ownership check to downloadAttachment (IDOR)"
```

---

## Task 2: `previewAttachment` security overhaul

**Files:**
- Create: `tests/Feature/ReportsAttachmentMimeAllowlistTest.php`
- Modify: `app/Modules/Reports/Controllers/ReportsController.php`

- [ ] **Step 2.1: Write the MIME allowlist tests**

```php
<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Extends ReportsAttachmentAccessTest to reuse the helper methods.
class ReportsAttachmentMimeAllowlistTest extends ReportsAttachmentAccessTest
{
    public function test_html_mime_is_served_as_octet_stream_with_attachment_disposition(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'text/html', 'evil.html');
        Storage::disk('local')->put($attachment->file_path, '<script>alert(1)</script>');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertOk();
        $this->assertStringStartsWith('application/octet-stream', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_javascript_mime_is_served_as_octet_stream(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'application/javascript', 'evil.js');
        Storage::disk('local')->put($attachment->file_path, 'alert(1)');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertOk();
        $this->assertStringStartsWith('application/octet-stream', $response->headers->get('Content-Type') ?? '');
    }

    public function test_pdf_mime_is_served_inline(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'application/pdf', 'report.pdf');
        Storage::disk('local')->put($attachment->file_path, '%PDF-1.4');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_image_jpeg_mime_is_served_inline(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'image/jpeg', 'photo.jpg');
        Storage::disk('local')->put($attachment->file_path, 'JFIF');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_x_content_type_options_nosniff_present_on_preview(): void
    {
        $user = $this->createUserWithPermissions(['submissions.view']);
        $form = $this->createForm($user);
        $attachment = $this->createAttachment($form, $user, 'image/png', 'img.png');
        Storage::disk('local')->put($attachment->file_path, 'PNG');

        $response = $this->actingAs($user)
            ->get(route('reports.attachments.preview', $attachment->id));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
```

- [ ] **Step 2.2: Run to confirm tests fail**

```bash
php artisan test tests/Feature/ReportsAttachmentMimeAllowlistTest.php
```

Expected: FAIL — preview method uses `file_get_contents`, no MIME check, no nosniff header.

- [ ] **Step 2.3: Add `PREVIEW_MIME_ALLOWLIST` constant and rewrite `previewAttachment`**

Add this constant just after `SLOW_QUERY_THRESHOLD_MS` in `ReportsController`:

```php
private const SLOW_QUERY_THRESHOLD_MS = 750;

private const PREVIEW_MIME_ALLOWLIST = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
];
```

Add this import at the top of the controller (after existing `use` statements):

```php
use Symfony\Component\HttpFoundation\HeaderUtils;
```

Replace the `previewAttachment` method:

```php
/**
 * Preview submission attachment (inline view).
 *
 * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\StreamedResponse
 */
public function previewAttachment(int $id)
{
    $attachment = SubmissionAttachment::findOrFail($id);

    $submission = $attachment->canonicalSubmission;
    $user = auth()->user();

    if (! $submission || ($submission->account_id !== $user->account_id && ! $user->hasPermission('submissions.override'))) {
        abort(403);
    }

    if (! Storage::disk('local')->exists($attachment->file_path)) {
        abort(404, 'File not found');
    }

    $mimeType = $attachment->mime_type;
    $noSniff = ['X-Content-Type-Options' => 'nosniff'];

    // MIME not in allowlist → force download as safe octet-stream (prevents stored XSS)
    if (! in_array($mimeType, self::PREVIEW_MIME_ALLOWLIST, true)) {
        return Response::download(
            Storage::disk('local')->path($attachment->file_path),
            $attachment->original_name,
            array_merge(['Content-Type' => 'application/octet-stream'], $noSniff)
        );
    }

    // Stream allowed MIME types inline (avoids file_get_contents memory exhaustion)
    return response()->stream(function () use ($attachment): void {
        $stream = Storage::disk('local')->readStream($attachment->file_path);
        if (is_resource($stream)) {
            fpassthru($stream);
            fclose($stream);
        }
    }, 200, array_merge($noSniff, [
        'Content-Type' => $mimeType,
        'Content-Disposition' => HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $attachment->original_name
        ),
    ]));
}
```

- [ ] **Step 2.4: Run all attachment tests**

```bash
php artisan test tests/Feature/ReportsAttachmentAccessTest.php tests/Feature/ReportsAttachmentMimeAllowlistTest.php
```

Expected: All 11 tests pass.

- [ ] **Step 2.5: Commit**

```bash
git add app/Modules/Reports/Controllers/ReportsController.php tests/Feature/ReportsAttachmentMimeAllowlistTest.php
git commit -m "fix(security): overhaul previewAttachment — MIME allowlist, streaming, nosniff"
```

---

## Task 3: `filter_state` deep validation (shared trait + request classes)

**Files:**
- Create: `app/Modules/Reports/Requests/Concerns/ValidatesFilterState.php`
- Create: `tests/Feature/ReportsScheduledExportFilterStateValidationTest.php`
- Modify: `app/Modules/Reports/Requests/StoreScheduledExportRequest.php`
- Modify: `app/Modules/Reports/Requests/UpdateScheduledExportRequest.php`

- [ ] **Step 3.1: Write the validation tests**

```php
<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\Reports\Models\ScheduledExport;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportsScheduledExportFilterStateValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->user = $this->createUserWithPermissions(['submissions.view']);
        $this->form = $this->createFormWithTextField($this->user);
    }

    public function test_store_accepts_null_filter_state(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('reports.scheduled-exports.store'), [
                'form_id' => $this->form->id,
                'recipient_email' => 'test@example.com',
                'frequency' => 'daily',
                'export_type' => 'csv',
                'filter_state' => null,
            ])
            ->assertStatus(201);
    }

    public function test_store_accepts_valid_filter_state_with_leaf_filter(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('reports.scheduled-exports.store'), [
                'form_id' => $this->form->id,
                'recipient_email' => 'test@example.com',
                'frequency' => 'daily',
                'export_type' => 'csv',
                'filter_state' => [
                    'filters' => [
                        ['column' => 'field_text', 'operator' => 'contains', 'value' => 'hello'],
                    ],
                ],
            ])
            ->assertStatus(201);
    }

    public function test_store_rejects_filter_state_with_unknown_column(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('reports.scheduled-exports.store'), [
                'form_id' => $this->form->id,
                'recipient_email' => 'test@example.com',
                'frequency' => 'daily',
                'export_type' => 'csv',
                'filter_state' => [
                    'filters' => [
                        ['column' => 'nonexistent_column', 'operator' => 'eq', 'value' => 'x'],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter_state.filters.0.column']);
    }

    public function test_store_rejects_triple_nested_groups(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('reports.scheduled-exports.store'), [
                'form_id' => $this->form->id,
                'recipient_email' => 'test@example.com',
                'frequency' => 'daily',
                'export_type' => 'csv',
                'filter_state' => [
                    'filters' => [
                        [
                            'logic' => 'and',
                            'filters' => [
                                [
                                    'logic' => 'or',  // group inside group = exceeds depth
                                    'filters' => [
                                        ['column' => 'field_text', 'operator' => 'eq', 'value' => 'x'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter_state']);
    }

    public function test_update_rejects_filter_state_with_invalid_operator(): void
    {
        $export = ScheduledExport::create([
            'form_id' => $this->form->id,
            'recipient_email' => 'test@example.com',
            'frequency' => 'daily',
            'export_type' => 'csv',
            'is_active' => true,
            'created_by' => $this->user->account_id,
        ]);

        $this->actingAs($this->user)
            ->putJson(route('reports.scheduled-exports.update', $export->id), [
                'filter_state' => [
                    'filters' => [
                        ['column' => 'field_text', 'operator' => 'not_a_real_operator', 'value' => 'x'],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['filter_state.filters.0.operator']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

        $role = Role::create(['role_name' => 'Role ' . uniqid(), 'description' => 'Test', 'is_active' => true]);
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

    private function createFormWithTextField(User $creator): Form
    {
        $form = Form::create([
            'form_name' => 'Test Form ' . uniqid(),
            'form_code' => 'TF' . uniqid(),
            'description' => 'Test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text',
            'label' => 'Text Field',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        return $form;
    }
}
```

- [ ] **Step 3.2: Run to confirm tests fail**

```bash
php artisan test tests/Feature/ReportsScheduledExportFilterStateValidationTest.php
```

Expected: `test_store_rejects_filter_state_with_unknown_column` and `test_store_rejects_triple_nested_groups` fail (no validation exists yet).

- [ ] **Step 3.3: Create the `ValidatesFilterState` trait**

Create `app/Modules/Reports/Requests/Concerns/ValidatesFilterState.php`:

```php
<?php

namespace App\Modules\Reports\Requests\Concerns;

use App\Modules\FormBuilder\Models\FormField;
use App\Modules\Reports\Services\ReportQueryBuilderService;
use Illuminate\Validation\Validator;

trait ValidatesFilterState
{
    /**
     * Returns [$selectableColumns, $formFieldTypes] from a single DB query.
     *
     * Replaces the two separate resolveSelectableColumns() / resolveFormFieldTypes() calls
     * that previously each issued their own query.
     *
     * @return array{0: list<string>, 1: array<string, string>}
     */
    protected function resolveFormFieldData(int $formId): array
    {
        $systemColumns = [
            'id', 'account_id', 'username', 'email', 'submitter_name',
            'submission_status', 'workflow_status', 'workflow_action',
            'attachment_count', 'attachments', 'snapshot', 'created_at',
        ];

        $fields = FormField::query()
            ->where('form_id', $formId)
            ->whereNotNull('field_name')
            ->get(['field_name', 'data_type']);

        $fieldColumns = $fields
            ->filter(static fn ($f) => is_string($f->field_name) && $f->field_name !== '')
            ->pluck('field_name')
            ->values()
            ->all();

        $selectableColumns = array_values(array_unique([...$systemColumns, ...$fieldColumns]));

        $formFieldTypes = $fields
            ->filter(static fn ($f) => is_string($f->field_name) && $f->field_name !== '')
            ->mapWithKeys(static fn ($f) => [
                $f->field_name => is_string($f->data_type) ? strtolower($f->data_type) : 'text',
            ])
            ->all();

        return [$selectableColumns, $formFieldTypes];
    }

    /**
     * Returns true when filter_state.filters contains a group nested within another group
     * (exceeds the allowed max depth of: group → leaf).
     *
     * @param array<mixed> $filterState
     */
    protected function filterStateExceedsMaxDepth(array $filterState): bool
    {
        $filters = $filterState['filters'] ?? [];
        if (! is_array($filters)) {
            return false;
        }

        foreach ($filters as $item) {
            if (! is_array($item) || ! isset($item['logic'])) {
                continue;
            }
            foreach ($item['filters'] ?? [] as $child) {
                if (is_array($child) && isset($child['logic'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate filter_state.filters column names and operators against the form's allowed set.
     *
     * @param array<mixed> $filterState
     */
    protected function validateFilterStateContents(
        Validator $validator,
        array $filterState,
        int $formId,
        string $prefix = 'filter_state'
    ): void {
        $filters = $filterState['filters'] ?? null;
        if (! is_array($filters) || $filters === []) {
            return;
        }

        [, $formFieldTypes] = $this->resolveFormFieldData($formId);
        $queryBuilderService = app(ReportQueryBuilderService::class);
        $filterableColumns = $queryBuilderService->resolveFilterableColumns($formFieldTypes);

        foreach ($filters as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['logic'])) {
                foreach ($item['filters'] ?? [] as $leafIndex => $leaf) {
                    if (! is_array($leaf) || isset($leaf['logic'])) {
                        continue;
                    }
                    $this->validateLeafFilter(
                        $validator,
                        $leaf,
                        $prefix . '.filters.' . $index . '.filters.' . $leafIndex,
                        $filterableColumns,
                        $queryBuilderService,
                        $formFieldTypes
                    );
                }
                continue;
            }

            $this->validateLeafFilter(
                $validator,
                $item,
                $prefix . '.filters.' . $index,
                $filterableColumns,
                $queryBuilderService,
                $formFieldTypes
            );
        }
    }

    /**
     * @param array<mixed>        $filter
     * @param list<string>        $filterableColumns
     * @param array<string,string> $formFieldTypes
     */
    protected function validateLeafFilter(
        Validator $validator,
        array $filter,
        string $prefix,
        array $filterableColumns,
        ReportQueryBuilderService $queryBuilderService,
        array $formFieldTypes,
    ): void {
        $column   = $filter['column'] ?? null;
        $operator = $filter['operator'] ?? null;
        $value    = $filter['value'] ?? null;

        if (! is_string($column) || ! in_array($column, $filterableColumns, true)) {
            $validator->errors()->add($prefix . '.column', 'The filter column is not allowed for this form.');
            return;
        }

        if (! is_string($operator)) {
            return;
        }

        $allowedOperators = $queryBuilderService->resolveAllowedOperatorsForColumn($column, $formFieldTypes);

        if (! in_array($operator, $allowedOperators, true)) {
            $validator->errors()->add($prefix . '.operator', 'The operator is not supported for the selected column.');
            return;
        }

        if (! $queryBuilderService->operatorRequiresValue($operator)) {
            return;
        }

        if ($queryBuilderService->operatorRequiresArrayValue($operator)) {
            if (! is_array($value)) {
                $validator->errors()->add($prefix . '.value', 'The value must be an array for the selected operator.');
                return;
            }
            if ($operator === 'in' && $value === []) {
                $validator->errors()->add($prefix . '.value', 'The value must include at least one item for the selected operator.');
                return;
            }
            if ($operator === 'between' && count($value) !== 2) {
                $validator->errors()->add($prefix . '.value', 'The value must include exactly two items for the between operator.');
            }
            return;
        }

        if (is_array($value)) {
            $validator->errors()->add($prefix . '.value', 'The value must be a scalar for the selected operator.');
            return;
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            $validator->errors()->add($prefix . '.value', 'The value is required for the selected operator.');
        }
    }
}
```

- [ ] **Step 3.4: Add `withValidator()` to `StoreScheduledExportRequest`**

Replace the full contents of `app/Modules/Reports/Requests/StoreScheduledExportRequest.php`:

```php
<?php

namespace App\Modules\Reports\Requests;

use App\Modules\Reports\Requests\Concerns\ValidatesFilterState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScheduledExportRequest extends FormRequest
{
    use ValidatesFilterState;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'form_id'         => ['required', 'integer', 'exists:tbl_form,id'],
            'recipient_email' => ['required', 'email', 'max:255'],
            'frequency'       => ['required', 'in:daily,weekly,monthly'],
            'export_type'     => ['required', 'in:csv,pdf'],
            'filter_state'    => ['nullable', 'array'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $filterState = $this->input('filter_state');

            if (! is_array($filterState)) {
                return;
            }

            if ($this->filterStateExceedsMaxDepth($filterState)) {
                $validator->errors()->add('filter_state', 'The filter state nesting exceeds the maximum allowed depth.');
                return;
            }

            $formId = (int) $this->input('form_id');
            if ($formId > 0) {
                $this->validateFilterStateContents($validator, $filterState, $formId);
            }
        });
    }
}
```

- [ ] **Step 3.5: Add `withValidator()` to `UpdateScheduledExportRequest`**

Replace the full contents of `app/Modules/Reports/Requests/UpdateScheduledExportRequest.php`:

```php
<?php

namespace App\Modules\Reports\Requests;

use App\Modules\Reports\Models\ScheduledExport;
use App\Modules\Reports\Requests\Concerns\ValidatesFilterState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateScheduledExportRequest extends FormRequest
{
    use ValidatesFilterState;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_email' => ['sometimes', 'email', 'max:255'],
            'frequency'       => ['sometimes', 'in:daily,weekly,monthly'],
            'export_type'     => ['sometimes', 'in:csv,pdf'],
            'filter_state'    => ['nullable', 'array'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $filterState = $this->input('filter_state');

            if (! is_array($filterState)) {
                return;
            }

            if ($this->filterStateExceedsMaxDepth($filterState)) {
                $validator->errors()->add('filter_state', 'The filter state nesting exceeds the maximum allowed depth.');
                return;
            }

            // Resolve form_id from the existing ScheduledExport (not in request body)
            $exportId = (int) $this->route('id');
            $export   = ScheduledExport::find($exportId);
            $formId   = $export ? (int) $export->form_id : 0;

            if ($formId > 0) {
                $this->validateFilterStateContents($validator, $filterState, $formId);
            }
        });
    }
}
```

- [ ] **Step 3.6: Run validation tests**

```bash
php artisan test tests/Feature/ReportsScheduledExportFilterStateValidationTest.php
```

Expected: All 5 tests pass.

- [ ] **Step 3.7: Commit**

```bash
git add app/Modules/Reports/Requests/Concerns/ValidatesFilterState.php \
        app/Modules/Reports/Requests/StoreScheduledExportRequest.php \
        app/Modules/Reports/Requests/UpdateScheduledExportRequest.php \
        tests/Feature/ReportsScheduledExportFilterStateValidationTest.php
git commit -m "fix(security): validate filter_state depth and column/operator on scheduled exports"
```

---

## Task 4: Monthly scheduled export frequency fix

**Files:**
- Create: `tests/Feature/ReportsMonthlyFrequencyTest.php`
- Modify: `app/Modules/Reports/Services/ScheduledExportService.php`

- [ ] **Step 4.1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\Reports\Models\ScheduledExport;
use App\Modules\Reports\Services\ScheduledExportService;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportsMonthlyFrequencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_monthly_export_is_not_due_after_28_days(): void
    {
        $user = $this->createUser();
        $form = $this->createForm($user);

        ScheduledExport::create([
            'form_id'          => $form->id,
            'recipient_email'  => 'test@example.com',
            'frequency'        => 'monthly',
            'export_type'      => 'csv',
            'is_active'        => true,
            'created_by'       => $user->account_id,
            'last_sent_at'     => now()->subDays(28),
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertCount(0, $due, 'A monthly export sent 28 days ago should not be due yet.');
    }

    public function test_monthly_export_is_due_after_32_days(): void
    {
        $user = $this->createUser();
        $form = $this->createForm($user);

        ScheduledExport::create([
            'form_id'         => $form->id,
            'recipient_email' => 'test@example.com',
            'frequency'       => 'monthly',
            'export_type'     => 'csv',
            'is_active'       => true,
            'created_by'      => $user->account_id,
            'last_sent_at'    => now()->subDays(32),
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertCount(1, $due, 'A monthly export sent 32 days ago should be due.');
    }

    public function test_monthly_export_is_due_when_never_sent(): void
    {
        $user = $this->createUser();
        $form = $this->createForm($user);

        ScheduledExport::create([
            'form_id'         => $form->id,
            'recipient_email' => 'test@example.com',
            'frequency'       => 'monthly',
            'export_type'     => 'csv',
            'is_active'       => true,
            'created_by'      => $user->account_id,
            'last_sent_at'    => null,
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertCount(1, $due);
    }

    private function createUser(): User
    {
        $role = Role::create(['role_name' => 'Role ' . uniqid(), 'description' => 'Test', 'is_active' => true]);

        $user = User::create([
            'username'       => 'user_' . uniqid(),
            'email'          => 'user_' . uniqid() . '@test.com',
            'password'       => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        UserRole::create([
            'account_id'    => $user->account_id,
            'role_id'       => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active'     => true,
            'assigned_by'   => $user->account_id,
        ]);

        return $user;
    }

    private function createForm(User $creator): Form
    {
        $form = Form::create([
            'form_name'  => 'Form ' . uniqid(),
            'form_code'  => 'F' . uniqid(),
            'description' => 'Test',
            'version'    => 1,
            'status'     => 'Active',
            'created_by' => $creator->account_id,
            'is_locked'  => true,
        ]);

        FormField::create([
            'form_id'    => $form->id,
            'field_name' => 'field_text',
            'label'      => 'Text',
            'data_type'  => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        return $form;
    }
}
```

- [ ] **Step 4.2: Run to confirm first test fails**

```bash
php artisan test tests/Feature/ReportsMonthlyFrequencyTest.php --filter=test_monthly_export_is_not_due_after_28_days
```

Expected: FAIL — `subDays(30)` considers a 28-day-old export as not-due, but February has fewer days so this is coincidentally passing. The real failure shows for months shorter than 30 days. Run the full suite to see all three:

```bash
php artisan test tests/Feature/ReportsMonthlyFrequencyTest.php
```

- [ ] **Step 4.3: Fix `subDays(30)` → `subMonth()` in `ScheduledExportService::findDue()`**

In `app/Modules/Reports/Services/ScheduledExportService.php`, replace the monthly condition inside `findDue()`:

```php
                    ->orWhere(function ($q) use ($now) {
                        $q->where('frequency', 'monthly')
                            ->where('last_sent_at', '<=', $now->copy()->subMonth());
                    });
```

(Previously `subDays(30)` — now `subMonth()`.)

- [ ] **Step 4.4: Run tests**

```bash
php artisan test tests/Feature/ReportsMonthlyFrequencyTest.php
```

Expected: All 3 tests pass.

- [ ] **Step 4.5: Commit**

```bash
git add app/Modules/Reports/Services/ScheduledExportService.php \
        tests/Feature/ReportsMonthlyFrequencyTest.php
git commit -m "fix: monthly scheduled export uses subMonth() instead of subDays(30)"
```

---

## Task 5: ReportSummaryService cache key determinism fix

**Files:**
- Modify: `app/Modules/Reports/Services/ReportSummaryService.php`

(The existing `ReportsCanonicalReadTest` exercises this path. No new test needed — just verify existing tests still pass after the change.)

- [ ] **Step 5.1: Replace `serialize()` with `json_encode(JSON_SORT_KEYS)` in `buildSummary()`**

In `app/Modules/Reports/Services/ReportSummaryService.php`, replace this line:

```php
        $cacheKey = sprintf(
            'reports_summary_%d_%s',
            $formId,
            md5(serialize($summaryFilters))
        );
```

with:

```php
        $cacheKey = sprintf(
            'reports_summary_%d_%s',
            $formId,
            md5(json_encode($summaryFilters, JSON_SORT_KEYS))
        );
```

- [ ] **Step 5.2: Run the existing summary-related tests**

```bash
php artisan test tests/Feature/ReportsCanonicalReadTest.php tests/Feature/ReportsAsyncExportStatusTest.php
```

Expected: All pass.

- [ ] **Step 5.3: Commit**

```bash
git add app/Modules/Reports/Services/ReportSummaryService.php
git commit -m "fix: use json_encode(JSON_SORT_KEYS) for summary cache key to ensure determinism"
```

---

## Task 6: `ReportsFilterRequest` — merge double query + `MAX_EXPORT_LIMIT` constant

**Files:**
- Modify: `app/Modules/Reports/Requests/ReportsFilterRequest.php`

- [ ] **Step 6.1: Add `use ValidatesFilterState` and refactor `withValidator()` to call `resolveFormFieldData()`**

Replace the full file contents of `app/Modules/Reports/Requests/ReportsFilterRequest.php`.
The diff of key changes:
- Add `use ValidatesFilterState;`
- Add `private const MAX_EXPORT_LIMIT = 5000;`
- Replace `'max:5000'` rule with `'max:' . self::MAX_EXPORT_LIMIT`
- Replace `? 5000` in `prepareForValidation()` with `? self::MAX_EXPORT_LIMIT`
- In `withValidator()`, replace the two separate resolver calls with `$this->resolveFormFieldData()`
- Remove the private `resolveSelectableColumns()` method
- Remove the private `resolveFormFieldTypes()` method
- Remove the private `validateLeafFilter()` method (now in trait)

Full updated file:

```php
<?php

namespace App\Modules\Reports\Requests;

use App\Modules\Reports\Requests\Concerns\ValidatesFilterState;
use App\Modules\Reports\Services\ReportQueryBuilderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportsFilterRequest extends FormRequest
{
    use ValidatesFilterState;

    private const MAX_EXPORT_LIMIT = 5000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $formIdRule = ['integer', 'exists:tbl_form,id'];

        if ($this->routeIs('reports.index')) {
            array_unshift($formIdRule, 'nullable');
        } else {
            array_unshift($formIdRule, 'required');
        }

        return [
            'form_id'          => $formIdRule,
            'date_from'        => ['nullable', 'date_format:Y-m-d'],
            'date_to'          => ['nullable', 'date_format:Y-m-d'],
            'submission_status' => ['nullable', 'in:pending,approved,rejected,completed'],
            'account_id'       => ['nullable', 'integer', 'exists:tbl_user,account_id'],
            'submitter'        => ['nullable', 'string', 'max:120'],
            'select'           => ['nullable', 'array', 'min:1'],
            'select.*'         => ['required', 'string', 'max:120'],
            'filters'          => ['nullable', 'array'],
            'filters.*'        => ['required', 'array'],
            'sort'             => ['nullable', 'array'],
            'sort.column'      => ['required_with:sort', 'string', 'max:120'],
            'sort.direction'   => ['required_with:sort', 'string', 'in:asc,desc'],
            'per_page'         => ['nullable', 'integer', 'min:1', 'max:100'],
            'export_limit'     => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_EXPORT_LIMIT],
            'page'             => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $submissionStatus = $this->input('submission_status');
        $submitter        = $this->input('submitter');
        $select           = $this->input('select');
        $filters          = $this->input('filters');
        $sort             = $this->input('sort');
        $exportLimit      = $this->input('export_limit');

        $this->merge([
            'submission_status' => is_string($submissionStatus) ? strtolower(trim($submissionStatus)) : $submissionStatus,
            'submitter'         => is_string($submitter) ? trim($submitter) : $submitter,
            'select'            => is_array($select)
                ? array_values(array_map(static fn ($column) => is_string($column) ? trim($column) : $column, $select))
                : $select,
            'filters'           => is_array($filters)
                ? array_values(array_map(static function ($filter) {
                    if (! is_array($filter)) {
                        return $filter;
                    }
                    if (isset($filter['logic'])) {
                        $filter['logic'] = strtolower(trim((string) ($filter['logic'] ?? 'and')));
                        if (isset($filter['filters']) && is_array($filter['filters'])) {
                            $filter['filters'] = array_values(array_map(static function ($leaf) {
                                if (! is_array($leaf)) {
                                    return $leaf;
                                }
                                $column   = $leaf['column'] ?? null;
                                $operator = $leaf['operator'] ?? null;
                                $leaf['column']   = is_string($column) ? trim($column) : $column;
                                $leaf['operator'] = is_string($operator) ? strtolower(trim($operator)) : $operator;
                                return $leaf;
                            }, $filter['filters']));
                        }
                        return $filter;
                    }
                    $column   = $filter['column'] ?? null;
                    $operator = $filter['operator'] ?? null;
                    $filter['column']   = is_string($column) ? trim($column) : $column;
                    $filter['operator'] = is_string($operator) ? strtolower(trim($operator)) : $operator;
                    return $filter;
                }, $filters))
                : $filters,
            'sort'        => is_array($sort)
                ? [
                    'column'    => is_string($sort['column'] ?? null) ? trim((string) $sort['column']) : ($sort['column'] ?? null),
                    'direction' => is_string($sort['direction'] ?? null) ? strtolower(trim((string) $sort['direction'])) : ($sort['direction'] ?? null),
                ]
                : $sort,
            'export_limit' => $exportLimit === 'all'
                ? self::MAX_EXPORT_LIMIT
                : (is_numeric($exportLimit) ? (int) $exportLimit : $exportLimit),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dateFrom = $this->input('date_from');
            $dateTo   = $this->input('date_to');

            if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
                $validator->errors()->add('date_to', 'The date_to must be a date after or equal to date_from.');
            }

            $select  = $this->input('select');
            $filters = $this->input('filters');
            $sort    = $this->input('sort');

            if (! is_array($select) && ! is_array($filters) && ! is_array($sort)) {
                return;
            }

            $formId = $this->input('form_id');

            if (! is_numeric($formId)) {
                return;
            }

            [$allowedColumns, $formFieldTypes] = $this->resolveFormFieldData((int) $formId);
            $queryBuilderService = app(ReportQueryBuilderService::class);
            $filterableColumns   = $queryBuilderService->resolveFilterableColumns($formFieldTypes);
            $sortableColumns     = $queryBuilderService->resolveSortableColumns($formFieldTypes);

            if (is_array($select)) {
                foreach ($select as $index => $column) {
                    if (! is_string($column) || ! in_array($column, $allowedColumns, true)) {
                        $validator->errors()->add('select.' . $index, 'The selected column is not allowed for this form.');
                    }
                }
            }

            if (is_array($filters)) {
                foreach ($filters as $index => $filter) {
                    if (! is_array($filter)) {
                        continue;
                    }

                    if (isset($filter['logic'])) {
                        if (! in_array($filter['logic'], ['and', 'or'], true)) {
                            $validator->errors()->add('filters.' . $index . '.logic', 'The filter group logic must be "and" or "or".');
                        }

                        foreach ($filter['filters'] ?? [] as $leafIndex => $leaf) {
                            if (! is_array($leaf)) {
                                continue;
                            }
                            if (isset($leaf['logic'])) {
                                continue;
                            }
                            $this->validateLeafFilter(
                                $validator,
                                $leaf,
                                'filters.' . $index . '.filters.' . $leafIndex,
                                $filterableColumns,
                                $queryBuilderService,
                                $formFieldTypes,
                            );
                        }

                        continue;
                    }

                    $this->validateLeafFilter(
                        $validator,
                        $filter,
                        'filters.' . $index,
                        $filterableColumns,
                        $queryBuilderService,
                        $formFieldTypes,
                    );
                }
            }

            if (is_array($sort)) {
                $sortColumn = $sort['column'] ?? null;

                if (! is_string($sortColumn) || ! in_array($sortColumn, $sortableColumns, true)) {
                    $validator->errors()->add('sort.column', 'The sort column is not allowed for this form.');
                }
            }
        });
    }
}
```

- [ ] **Step 6.2: Run the full Reports test suite**

```bash
php artisan test tests/Feature/Reports
```

Expected: All existing tests pass (contracts unchanged — just one fewer DB query per validated request).

- [ ] **Step 6.3: Commit**

```bash
git add app/Modules/Reports/Requests/ReportsFilterRequest.php
git commit -m "refactor: merge double FormField query in ReportsFilterRequest, add MAX_EXPORT_LIMIT constant"
```

---

## Task 7: `CleanupAsyncExports` artisan command

**Files:**
- Create: `app/Console/Commands/CleanupAsyncExports.php`
- Create: `tests/Feature/ReportsCleanupCommandTest.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 7.1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportsCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_deletes_files_older_than_ttl(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('exports/async/old-export.csv', 'stale content');

        // Touch the file to make it appear 2 hours old
        $realPath = Storage::disk('local')->path('exports/async/old-export.csv');
        touch($realPath, time() - 7200);
        clearstatcache(true, $realPath);

        config(['reports.async_export_cache_ttl_seconds' => 3600]); // 1 hour TTL

        $this->artisan('reports:cleanup-exports')
            ->assertSuccessful()
            ->expectsOutput('Deleted 1 expired async export file(s).');

        Storage::disk('local')->assertMissing('exports/async/old-export.csv');
    }

    public function test_cleanup_leaves_recently_created_files(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('exports/async/fresh-export.csv', 'fresh content');

        config(['reports.async_export_cache_ttl_seconds' => 3600]); // 1 hour TTL

        $this->artisan('reports:cleanup-exports')
            ->assertSuccessful()
            ->expectsOutput('Deleted 0 expired async export file(s).');

        Storage::disk('local')->assertExists('exports/async/fresh-export.csv');
    }

    public function test_cleanup_is_a_no_op_when_directory_is_empty(): void
    {
        Storage::fake('local');

        config(['reports.async_export_cache_ttl_seconds' => 3600]);

        $this->artisan('reports:cleanup-exports')
            ->assertSuccessful()
            ->expectsOutput('Deleted 0 expired async export file(s).');
    }
}
```

- [ ] **Step 7.2: Run to confirm tests fail**

```bash
php artisan test tests/Feature/ReportsCleanupCommandTest.php
```

Expected: FAIL — command does not exist.

- [ ] **Step 7.3: Create the command**

Create `app/Console/Commands/CleanupAsyncExports.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupAsyncExports extends Command
{
    protected $signature = 'reports:cleanup-exports';

    protected $description = 'Delete async export files under storage/app/exports/async/ older than the configured TTL';

    public function handle(): int
    {
        $ttl    = (int) config('reports.async_export_cache_ttl_seconds', 3600);
        $cutoff = now()->subSeconds($ttl)->getTimestamp();

        $files   = Storage::disk('local')->files('exports/async');
        $deleted = 0;

        foreach ($files as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);

            if ($lastModified < $cutoff) {
                Storage::disk('local')->delete($file);
                $deleted++;
            }
        }

        Log::info('reports:cleanup-exports completed.', ['files_deleted' => $deleted]);

        $this->info("Deleted {$deleted} expired async export file(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 7.4: Run tests**

```bash
php artisan test tests/Feature/ReportsCleanupCommandTest.php
```

Expected: All 3 tests pass.

- [ ] **Step 7.5: Register in the scheduler**

In `bootstrap/app.php`, add the cleanup schedule entry inside `->withSchedule(...)`, after the existing `reports:send-scheduled-exports` entry:

```php
        $schedule->command('reports:cleanup-exports')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/cleanup_exports.log'));
```

- [ ] **Step 7.6: Verify `php artisan list` shows the command**

```bash
php artisan list | grep cleanup
```

Expected output: `reports:cleanup-exports  Delete async export files...`

- [ ] **Step 7.7: Run the full Reports test suite to confirm nothing regressed**

```bash
php artisan test tests/Feature/Reports
```

Expected: All tests pass.

- [ ] **Step 7.8: Commit**

```bash
git add app/Console/Commands/CleanupAsyncExports.php \
        bootstrap/app.php \
        tests/Feature/ReportsCleanupCommandTest.php
git commit -m "feat: add reports:cleanup-exports artisan command to purge stale async export files"
```

---

## Final verification

- [ ] **Run the complete test suite**

```bash
php artisan test
```

Expected: All tests green, no regressions.
