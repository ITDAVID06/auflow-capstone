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
            $table->renameColumn('workflow_version', 'workflow_version_id');
        });

        Schema::table('tbl_workflow_step_progress', function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_version_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_workflow_step_progress', function (Blueprint $table) {
            $table->renameColumn('workflow_version_id', 'workflow_version');
        });
    }
};
