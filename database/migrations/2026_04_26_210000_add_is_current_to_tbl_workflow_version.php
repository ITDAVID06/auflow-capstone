<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_workflow_version', function (Blueprint $table) {
            $table->boolean('is_current')->default(false)->after('steps_snapshot');
        });

        // Backfill: mark the highest version_number per workflow as the current one.
        DB::statement('
            UPDATE tbl_workflow_version wv
            INNER JOIN (
                SELECT workflow_id, MAX(version_number) AS max_version
                FROM tbl_workflow_version
                GROUP BY workflow_id
            ) latest
            ON wv.workflow_id = latest.workflow_id
            AND wv.version_number = latest.max_version
            SET wv.is_current = 1
        ');
    }

    public function down(): void
    {
        Schema::table('tbl_workflow_version', function (Blueprint $table) {
            $table->dropColumn('is_current');
        });
    }
};
