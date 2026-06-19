<?php

namespace Tests\Feature;

use App\Exceptions\SnapshotImmutableException;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\User;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SnapshotImmutabilityGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_new_locked_snapshot_can_be_created(): void
    {
        [$form, $submission] = $this->makeFormAndSubmission();

        $snapshot = Snapshot::create([
            'public_id' => 'pub-'.uniqid(),
            'submission_id' => $submission->id,
            'form_id' => $form->id,
            'workflow_id' => null,
            'step_id' => null,
            'workflow_step' => 'Step 1',
            'status' => 'Approved',
            'approved_by' => null,
            'approved_at' => now(),
            'comment' => null,
            'payload_json' => ['form' => [], 'fields' => []],
            'action_hash' => hash('sha256', 'test-'.uniqid()),
            'locked' => true,
            'created_at' => now(),
        ]);

        $this->assertTrue($snapshot->locked);
        $this->assertNotNull($snapshot->id);
    }

    public function test_unlocked_snapshot_can_be_updated(): void
    {
        [$form, $submission] = $this->makeFormAndSubmission();

        $snapshot = Snapshot::create([
            'public_id' => 'pub-'.uniqid(),
            'submission_id' => $submission->id,
            'form_id' => $form->id,
            'workflow_id' => null,
            'step_id' => null,
            'workflow_step' => 'Step 1',
            'status' => 'Pending',
            'approved_by' => null,
            'approved_at' => now(),
            'comment' => null,
            'payload_json' => ['form' => [], 'fields' => []],
            'action_hash' => hash('sha256', 'unlocked-'.uniqid()),
            'locked' => false,
            'created_at' => now(),
        ]);

        // Should not throw
        $snapshot->comment = 'Updated comment';
        $snapshot->save();

        $this->assertSame('Updated comment', $snapshot->fresh()->comment);
    }

    public function test_locked_snapshot_cannot_be_modified_via_model(): void
    {
        [$form, $submission] = $this->makeFormAndSubmission();

        $snapshot = Snapshot::create([
            'public_id' => 'pub-'.uniqid(),
            'submission_id' => $submission->id,
            'form_id' => $form->id,
            'workflow_id' => null,
            'step_id' => null,
            'workflow_step' => 'Step 1',
            'status' => 'Approved',
            'approved_by' => null,
            'approved_at' => now(),
            'comment' => null,
            'payload_json' => ['form' => [], 'fields' => []],
            'action_hash' => hash('sha256', 'locked-'.uniqid()),
            'locked' => true,
            'created_at' => now(),
        ]);

        $this->expectException(SnapshotImmutableException::class);

        $snapshot->comment = 'Tampered';
        $snapshot->save();
    }

    public function test_snapshot_immutable_exception_message_contains_snapshot_id(): void
    {
        [$form, $submission] = $this->makeFormAndSubmission();

        $snapshot = Snapshot::create([
            'public_id' => 'pub-'.uniqid(),
            'submission_id' => $submission->id,
            'form_id' => $form->id,
            'workflow_id' => null,
            'step_id' => null,
            'workflow_step' => 'Step 1',
            'status' => 'Approved',
            'approved_by' => null,
            'approved_at' => now(),
            'comment' => null,
            'payload_json' => ['form' => [], 'fields' => []],
            'action_hash' => hash('sha256', 'locked2-'.uniqid()),
            'locked' => true,
            'created_at' => now(),
        ]);

        try {
            $snapshot->status = 'Tampered';
            $snapshot->save();
            $this->fail('Expected SnapshotImmutableException was not thrown.');
        } catch (SnapshotImmutableException $e) {
            $this->assertStringContainsString((string) $snapshot->id, $e->getMessage());
        }
    }

    /** @return array{0: Form, 1: FormSubmission} */
    private function makeFormAndSubmission(): array
    {
        $user = User::create([
            'username' => 'snapuser_'.uniqid(),
            'email' => 'snapuser_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Snap Form '.uniqid(),
            'form_code' => 'SNP'.uniqid(),
            'description' => null,
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
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

        $submission = FormSubmission::create([
            'form_id' => $form->id,
            'account_id' => $user->account_id,
            'submission_status' => 'Approved',
            'current_workflow_status' => 'Approved',
            'payload_json' => ['field_text' => 'test'],
            'schema_snapshot_json' => [],
            'submitted_at' => now()->subMinutes(5),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return [$form, $submission];
    }
}
