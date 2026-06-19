<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCanonicalSubmissionId(
            table: 'tbl_submission_attachment',
            columnAfter: 'submission_id',
            foreignKeyName: 'fk_submission_attachment_canonical_submission',
            indexName: 'idx_submission_attachment_canonical_submission'
        );

        $this->addCanonicalSubmissionId(
            table: 'tbl_slots',
            columnAfter: 'submission_id',
            foreignKeyName: 'fk_slots_canonical_submission',
            indexName: 'idx_slots_canonical_submission'
        );

        $this->addCanonicalSubmissionId(
            table: 'tbl_workflow_step_progress',
            columnAfter: 'submission_id',
            foreignKeyName: 'fk_wsp_canonical_submission',
            indexName: 'idx_wsp_canonical_submission'
        );

        $this->addCanonicalSubmissionId(
            table: 'tbl_snapshot',
            columnAfter: 'submission_id',
            foreignKeyName: 'fk_snapshot_canonical_submission',
            indexName: 'idx_snapshot_canonical_submission'
        );
    }

    public function down(): void
    {
        $this->dropCanonicalSubmissionId(
            table: 'tbl_snapshot',
            foreignKeyName: 'fk_snapshot_canonical_submission',
            indexName: 'idx_snapshot_canonical_submission'
        );

        $this->dropCanonicalSubmissionId(
            table: 'tbl_workflow_step_progress',
            foreignKeyName: 'fk_wsp_canonical_submission',
            indexName: 'idx_wsp_canonical_submission'
        );

        $this->dropCanonicalSubmissionId(
            table: 'tbl_slots',
            foreignKeyName: 'fk_slots_canonical_submission',
            indexName: 'idx_slots_canonical_submission'
        );

        $this->dropCanonicalSubmissionId(
            table: 'tbl_submission_attachment',
            foreignKeyName: 'fk_submission_attachment_canonical_submission',
            indexName: 'idx_submission_attachment_canonical_submission'
        );
    }

    private function addCanonicalSubmissionId(
        string $table,
        string $columnAfter,
        string $foreignKeyName,
        string $indexName,
    ): void {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'canonical_submission_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($columnAfter): void {
            $column = $tableBlueprint->unsignedBigInteger('canonical_submission_id')->nullable();

            if (DB::getDriverName() !== 'sqlite') {
                $column->after($columnAfter);
            }
        });

        if (! $this->hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($indexName): void {
                $tableBlueprint->index('canonical_submission_id', $indexName);
            });
        }

        if (DB::getDriverName() === 'sqlite' || $this->hasForeignKey($table, $foreignKeyName)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($foreignKeyName): void {
            $tableBlueprint->foreign('canonical_submission_id', $foreignKeyName)
                ->references('id')
                ->on('tbl_form_submission')
                ->nullOnDelete();
        });
    }

    private function dropCanonicalSubmissionId(
        string $table,
        string $foreignKeyName,
        string $indexName,
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'canonical_submission_id')) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite' && $this->hasForeignKey($table, $foreignKeyName)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($foreignKeyName): void {
                $tableBlueprint->dropForeign($foreignKeyName);
            });
        }

        if ($this->hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($indexName): void {
                $tableBlueprint->dropIndex($indexName);
            });
        }

        Schema::table($table, function (Blueprint $tableBlueprint): void {
            $tableBlueprint->dropColumn('canonical_submission_id');
        });
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.table_constraints
             WHERE constraint_schema = ?
               AND table_name = ?
               AND constraint_name = ?
               AND constraint_type = "FOREIGN KEY"',
            [DB::getDatabaseName(), $table, $constraintName]
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?',
            [DB::getDatabaseName(), $table, $indexName]
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }
};
