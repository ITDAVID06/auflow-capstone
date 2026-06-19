<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = Schema::getIndexes('tbl_form_submission');
        $hasIndex = collect($indexes)->contains('name', 'uq_legacy_form_submission');

        Schema::table('tbl_form_submission', function (Blueprint $table) use ($hasIndex): void {
            if ($hasIndex) {
                $table->dropUnique('uq_legacy_form_submission');
            }
            $columns = [];
            if (Schema::hasColumn('tbl_form_submission', 'legacy_form_table')) {
                $columns[] = 'legacy_form_table';
            }
            if (Schema::hasColumn('tbl_form_submission', 'legacy_submission_id')) {
                $columns[] = 'legacy_submission_id';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        // ── tbl_workflow_step_progress ────────────────────────────────────────
        $foreignKeys1 = Schema::getForeignKeys('tbl_workflow_step_progress');
        $indexes1 = Schema::getIndexes('tbl_workflow_step_progress');

        Schema::table('tbl_workflow_step_progress', function (Blueprint $table) use ($foreignKeys1, $indexes1): void {
            foreach ($foreignKeys1 as $fk) {
                if (in_array('canonical_submission_id', $fk['columns'])) {
                    $table->dropForeign($fk['name']);
                }
            }
            // Drop any plain indexes covering the column before dropping it (SQLite requires this)
            foreach ($indexes1 as $idx) {
                $cols = $idx['columns'] ?? [];
                if (in_array('canonical_submission_id', $cols)) {
                    $table->dropIndex($idx['name']);
                }
            }
            if (Schema::hasColumn('tbl_workflow_step_progress', 'canonical_submission_id')) {
                $table->dropColumn('canonical_submission_id');
            }
        });

        // ── tbl_snapshot ──────────────────────────────────────────────────────
        $foreignKeys2 = Schema::getForeignKeys('tbl_snapshot');
        $indexes2 = Schema::getIndexes('tbl_snapshot');

        Schema::table('tbl_snapshot', function (Blueprint $table) use ($foreignKeys2, $indexes2): void {
            foreach ($foreignKeys2 as $fk) {
                if (in_array('canonical_submission_id', $fk['columns'])) {
                    $table->dropForeign($fk['name']);
                }
            }
            foreach ($indexes2 as $idx) {
                if (in_array('canonical_submission_id', $idx['columns'] ?? [])) {
                    $table->dropIndex($idx['name']);
                }
            }
            if (Schema::hasColumn('tbl_snapshot', 'canonical_submission_id')) {
                $table->dropColumn('canonical_submission_id');
            }
        });

        // ── tbl_submission_attachment ─────────────────────────────────────────
        $foreignKeys3 = Schema::getForeignKeys('tbl_submission_attachment');
        $indexes3 = Schema::getIndexes('tbl_submission_attachment');

        Schema::table('tbl_submission_attachment', function (Blueprint $table) use ($foreignKeys3, $indexes3): void {
            foreach ($foreignKeys3 as $fk) {
                if (in_array('canonical_submission_id', $fk['columns'])) {
                    $table->dropForeign($fk['name']);
                }
            }
            foreach ($indexes3 as $idx) {
                $cols = $idx['columns'] ?? [];
                if (in_array('canonical_submission_id', $cols) || in_array('form_table', $cols)) {
                    $table->dropIndex($idx['name']);
                }
            }
            $columns = [];
            if (Schema::hasColumn('tbl_submission_attachment', 'canonical_submission_id')) {
                $columns[] = 'canonical_submission_id';
            }
            if (Schema::hasColumn('tbl_submission_attachment', 'form_table')) {
                $columns[] = 'form_table';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        // ── tbl_slots ─────────────────────────────────────────────────────────
        $foreignKeys4 = Schema::getForeignKeys('tbl_slots');
        $indexes4 = Schema::getIndexes('tbl_slots');

        Schema::table('tbl_slots', function (Blueprint $table) use ($foreignKeys4, $indexes4): void {
            foreach ($foreignKeys4 as $fk) {
                if (in_array('canonical_submission_id', $fk['columns'])) {
                    $table->dropForeign($fk['name']);
                }
            }
            foreach ($indexes4 as $idx) {
                if (in_array('canonical_submission_id', $idx['columns'] ?? [])) {
                    $table->dropIndex($idx['name']);
                }
            }
            if (Schema::hasColumn('tbl_slots', 'canonical_submission_id')) {
                $table->dropColumn('canonical_submission_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_form_submission', function (Blueprint $table): void {
            $table->string('legacy_form_table')->nullable();
            $table->unsignedBigInteger('legacy_submission_id')->nullable();
        });

        Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
            $table->unsignedBigInteger('canonical_submission_id')->nullable();
            $table->foreign('canonical_submission_id')->references('id')->on('tbl_form_submission')->nullOnDelete();
        });

        Schema::table('tbl_snapshot', function (Blueprint $table): void {
            $table->unsignedBigInteger('canonical_submission_id')->nullable();
            $table->foreign('canonical_submission_id')->references('id')->on('tbl_form_submission')->nullOnDelete();
        });

        Schema::table('tbl_submission_attachment', function (Blueprint $table): void {
            $table->unsignedBigInteger('canonical_submission_id')->nullable();
            $table->string('form_table')->nullable();
            $table->foreign('canonical_submission_id')->references('id')->on('tbl_form_submission')->nullOnDelete();
        });

        Schema::table('tbl_slots', function (Blueprint $table): void {
            $table->unsignedBigInteger('canonical_submission_id')->nullable();
            $table->foreign('canonical_submission_id')->references('id')->on('tbl_form_submission')->nullOnDelete();
        });
    }
};
