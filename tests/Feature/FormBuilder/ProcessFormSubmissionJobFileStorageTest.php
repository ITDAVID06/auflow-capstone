<?php

namespace Tests\Feature\FormBuilder;

use App\Actions\FormBuilder\FindSubmissionByIdempotencyKeyAction;
use App\Actions\FormBuilder\WriteCanonicalSubmissionAction;
use App\Jobs\ProcessFormSubmissionJob;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessFormSubmissionJobFileStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Form-field file uploads (submissions_uploads) must land on the private/default
     * disk and must NOT be accessible via the public storage disk.
     */
    public function test_form_field_uploads_are_promoted_to_default_disk_not_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::create([
            'username' => 'submitter_'.uniqid(),
            'email' => 'sub_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Storage Test Form',
            'form_code' => 'STF-'.uniqid(),
            'status' => 'Active',
            'version' => 1,
            'is_locked' => true,
            'created_by' => $user->account_id,
        ]);

        // Simulate a temp file stored on the default (private) disk, as the fixed services will do.
        $tempPath = 'submission-temp/submissions_uploads/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($tempPath, 'fake-pdf-content');

        $fakeSubmission = new FormSubmission;
        $fakeSubmission->id = 9999;

        $findAction = $this->createMock(FindSubmissionByIdempotencyKeyAction::class);
        $findAction->method('execute')->willReturn(null);

        $writeAction = $this->createMock(WriteCanonicalSubmissionAction::class);
        $writeAction->method('execute')->willReturn($fakeSubmission);

        $job = new ProcessFormSubmissionJob(
            formId: $form->id,
            accountId: $user->account_id,
            payload: ['upload_field' => $tempPath],
            schemaSnapshot: [],
            idempotencyKey: 'test-'.Str::uuid()->toString(),
            currentStepId: null,
            currentActorId: null,
            submittedAt: now()->toIso8601String(),
            attachmentPayloads: [],
            slotPayloads: [],
            workflowProgressPayloads: [],
            workflowId: null,
            workflowType: null,
        );

        $job->handle($writeAction, $findAction);

        // The promoted file must exist on the default (private) disk under submissions_uploads/.
        $this->assertCount(1, Storage::disk('local')->files('submissions_uploads'));

        // The temp path must be gone (moved, not copied).
        Storage::disk('local')->assertMissing($tempPath);

        // The public disk must remain completely empty — submissions_uploads must never go there.
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}
