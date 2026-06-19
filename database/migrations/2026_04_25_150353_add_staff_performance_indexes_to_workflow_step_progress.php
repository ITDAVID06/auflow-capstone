<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tbl_workflow_step_progress', function (Blueprint $table) {
            $table->index(['actor_id', 'status', 'acted_at', 'duration_seconds'], 'wsp_staff_perf_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_step_progress', function (Blueprint $table) {
            //
        });
    }
};
