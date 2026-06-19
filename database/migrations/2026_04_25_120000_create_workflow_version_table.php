<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_workflow_version', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('workflow_id');
            $table->unsignedInteger('version_number');

            // Frozen copy of all tbl_workflow_step rows (with approvers) at publish time.
            $table->json('steps_snapshot');

            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['workflow_id', 'version_number'], 'uq_workflow_version');

            $table->foreign('workflow_id')
                ->references('id')
                ->on('tbl_workflow')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_workflow_version');
    }
};
