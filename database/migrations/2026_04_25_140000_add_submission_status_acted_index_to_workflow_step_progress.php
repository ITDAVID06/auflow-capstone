<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
            $table->index(
                ['submission_id', 'status', 'acted_at'],
                'idx_wsp_submission_status_acted'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tbl_workflow_step_progress', function (Blueprint $table): void {
            $table->dropIndex('idx_wsp_submission_status_acted');
        });
    }
};
