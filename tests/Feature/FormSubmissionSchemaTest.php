<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Data\FormSubmissionData;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\Slot;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormSubmissionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_submission_table_contains_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tbl_form_submission'));

        $expectedColumns = [
            'id',
            'form_id',
            'account_id',
            'submission_status',
            'current_workflow_status',
            'current_step_id',
            'current_actor_id',
            'payload_json',
            'schema_snapshot_json',
            'submitted_at',
            'revision_of',
            'root_submission_id',
            'is_latest_revision',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(Schema::hasColumn('tbl_form_submission', $column), "Missing column: {$column}");
        }
    }

    public function test_form_submission_model_and_data_class_share_expected_contract(): void
    {
        $submission = new FormSubmission([
            'form_id' => 10,
            'account_id' => 20,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => ['field_alpha' => 'value'],
            'schema_snapshot_json' => ['fields' => []],
            'submitted_at' => '2026-03-11 00:00:00',
            'is_latest_revision' => true,
        ]);
        $submission->id = 99;

        $data = FormSubmissionData::fromModel($submission);

        $this->assertSame('tbl_form_submission', $submission->getTable());
        $this->assertSame(10, $data->formId);
        $this->assertSame(20, $data->accountId);
        $this->assertSame('Pending', $data->submissionStatus);
        $this->assertSame(['field_alpha' => 'value'], $data->payload);
    }

    public function test_dependent_tables_use_submission_id_as_canonical_fk(): void
    {
        $expectedTables = [
            'tbl_submission_attachment',
            'tbl_slots',
            'tbl_workflow_step_progress',
            'tbl_snapshot',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'submission_id'), "Missing submission_id on: {$table}");
            $this->assertFalse(Schema::hasColumn($table, 'canonical_submission_id'), "Legacy canonical_submission_id still present on: {$table}");
        }
    }

    public function test_dependent_models_allow_mass_assignment_for_submission_id(): void
    {
        $attachment = new SubmissionAttachment(['submission_id' => 11]);
        $slot = new Slot(['submission_id' => 12]);
        $progress = new WorkflowStepProgress(['submission_id' => 13]);
        $snapshot = new Snapshot(['submission_id' => 14]);

        $this->assertSame(11, $attachment->submission_id);
        $this->assertSame(12, $slot->submission_id);
        $this->assertSame(13, $progress->submission_id);
        $this->assertSame(14, $snapshot->submission_id);
    }

    public function test_form_submission_form_foreign_key_restricts_hard_deletes(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $foreignKeys = DB::select("PRAGMA foreign_key_list('tbl_form_submission')");
            $formForeignKey = collect($foreignKeys)->firstWhere('from', 'form_id');

            $this->assertNotNull($formForeignKey);
            $this->assertSame('RESTRICT', strtoupper((string) $formForeignKey->on_delete));

            return;
        }

        $foreignKey = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->select('DELETE_RULE')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'tbl_form_submission')
            ->where('CONSTRAINT_NAME', 'tbl_form_submission_form_id_foreign')
            ->first();

        $this->assertNotNull($foreignKey);
        $this->assertSame('RESTRICT', strtoupper((string) $foreignKey->DELETE_RULE));
    }
}
