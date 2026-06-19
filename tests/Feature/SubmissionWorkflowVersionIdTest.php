<?php

namespace Tests\Feature;

use App\Actions\FormBuilder\WriteCanonicalSubmissionAction;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubmissionWorkflowVersionIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function makeFormAndUser(): array
    {
        $user = User::create([
            'username' => 'sub_versionid_'.uniqid(),
            'email' => 'sub_versionid_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'VersionIdForm_'.uniqid(),
            'form_code' => 'VID_'.uniqid(),
            'description' => null,
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
        ]);

        return [$form, $user];
    }

    public function test_write_canonical_submission_stores_workflow_version_id(): void
    {
        [$form, $user] = $this->makeFormAndUser();

        $workflow = Workflow::create([
            'workflow_name' => 'VersionIdWorkflow_'.uniqid(),
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version_number' => 1,
            'steps_snapshot' => [],
            'published_at' => now(),
            'is_current' => true,
        ]);

        $submission = app(WriteCanonicalSubmissionAction::class)->execute(
            form: $form,
            accountId: $user->account_id,
            payload: ['field_a' => 'value'],
            schemaSnapshot: [],
            idempotencyKey: 'test-idem-'.uniqid(),
            workflowVersionId: $version->id,
        );

        $this->assertInstanceOf(FormSubmission::class, $submission);
        $this->assertSame((int) $version->id, (int) $submission->workflow_version_id);
        $this->assertDatabaseHas('tbl_form_submission', [
            'id' => $submission->id,
            'workflow_version_id' => $version->id,
        ]);
    }

    public function test_write_canonical_submission_accepts_null_workflow_version_id(): void
    {
        [$form, $user] = $this->makeFormAndUser();

        $submission = app(WriteCanonicalSubmissionAction::class)->execute(
            form: $form,
            accountId: $user->account_id,
            payload: ['field_a' => 'value'],
            schemaSnapshot: [],
            idempotencyKey: 'test-idem-null-'.uniqid(),
            workflowVersionId: null,
        );

        $this->assertInstanceOf(FormSubmission::class, $submission);
        $this->assertNull($submission->workflow_version_id);
    }
}
