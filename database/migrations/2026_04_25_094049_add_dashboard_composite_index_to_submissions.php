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
        Schema::table('tbl_form_submission', function (Blueprint $table) {
            $table->index(['current_actor_id', 'current_workflow_status', 'submitted_at'], 'idx_dashboard_pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_form_submission', function (Blueprint $table) {
            $table->dropIndex('idx_dashboard_pending');
        });
    }
};
