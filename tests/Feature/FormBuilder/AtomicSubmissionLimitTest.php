<?php

namespace Tests\Feature\FormBuilder;

use App\Actions\FormBuilder\WriteCanonicalSubmissionAction;
use App\Exceptions\SubmissionLimitExceededException;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AtomicSubmissionLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_throws_when_submission_limit_is_reached(): void
    {
        $submitter = $this->createUser('limit-test@example.com');
        $form = $this->createFormWithLimit($submitter, limit: 2);

        // Insert 2 existing submissions (at limit)
        $this->insertExistingSubmissions($form, $submitter, count: 2);

        $this->expectException(SubmissionLimitExceededException::class);

        app(WriteCanonicalSubmissionAction::class)->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_x' => 'value'],
            schemaSnapshot: ['fields' => []],
        );
    }

    public function test_action_succeeds_when_below_submission_limit(): void
    {
        $submitter = $this->createUser('limit-ok@example.com');
        $form = $this->createFormWithLimit($submitter, limit: 3);

        // Insert 2 existing submissions (below limit of 3)
        $this->insertExistingSubmissions($form, $submitter, count: 2);

        $submission = app(WriteCanonicalSubmissionAction::class)->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_x' => 'value'],
            schemaSnapshot: ['fields' => []],
        );

        $this->assertDatabaseHas('tbl_form_submission', ['id' => $submission->id]);
    }

    public function test_action_ignores_limit_when_submission_limit_is_null(): void
    {
        $submitter = $this->createUser('no-limit@example.com');
        $form = $this->createFormWithLimit($submitter, limit: null);

        $this->insertExistingSubmissions($form, $submitter, count: 100);

        $submission = app(WriteCanonicalSubmissionAction::class)->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_x' => 'value'],
            schemaSnapshot: ['fields' => []],
        );

        $this->assertDatabaseHas('tbl_form_submission', ['id' => $submission->id]);
    }

    public function test_action_ignores_limit_when_submission_limit_is_zero(): void
    {
        $submitter = $this->createUser('zero-limit@example.com');
        $form = $this->createFormWithLimit($submitter, limit: 0);

        $this->insertExistingSubmissions($form, $submitter, count: 50);

        $submission = app(WriteCanonicalSubmissionAction::class)->execute(
            form: $form,
            accountId: (int) $submitter->account_id,
            payload: ['field_x' => 'value'],
            schemaSnapshot: ['fields' => []],
        );

        $this->assertDatabaseHas('tbl_form_submission', ['id' => $submission->id]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(string $email): User
    {
        $statusId = DB::table('tbl_user_status')->insertGetId([
            'status_name' => 'Active',
            'description' => 'Active test status',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->create([
            'username' => strstr($email, '@', true) ?: 'tester',
            'email' => $email,
            'password' => bcrypt('password'),
            'user_status_id' => $statusId,
            'must_change_password' => false,
        ]);
    }

    private function createFormWithLimit(User $creator, ?int $limit): Form
    {
        return Form::query()->create([
            'form_name' => 'Limit Test Form '.uniqid(),
            'form_code' => 'LTF-'.uniqid(),
            'version' => 1,
            'status' => 'Active',
            'submission_limit' => $limit,
            'is_locked' => true,
            'created_by' => $creator->account_id,
        ]);
    }

    private function insertExistingSubmissions(Form $form, User $submitter, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            FormSubmission::query()->create([
                'form_id' => $form->id,
                'account_id' => $submitter->account_id,
                'idempotency_key' => 'key-existing-'.$i.'-'.uniqid(),
                'submission_status' => 'Pending',
                'current_workflow_status' => 'Pending',
                'payload_json' => [],
                'schema_snapshot_json' => [],
                'submitted_at' => now(),
                'is_latest_revision' => true,
            ]);
        }
    }
}
