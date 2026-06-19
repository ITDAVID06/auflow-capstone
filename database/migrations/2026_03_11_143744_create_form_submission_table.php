<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_form_submission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('account_id');
            $table->string('submission_status', 32)->default('Pending');
            $table->string('current_workflow_status', 32)->default('Pending');
            $table->unsignedBigInteger('current_step_id')->nullable();
            $table->unsignedBigInteger('current_actor_id')->nullable();
            $table->json('payload_json');
            $table->json('schema_snapshot_json');
            $table->timestamp('submitted_at');
            $table->unsignedBigInteger('revision_of')->nullable();
            $table->unsignedBigInteger('root_submission_id')->nullable();
            $table->boolean('is_latest_revision')->default(true);
            $table->string('legacy_form_table')->nullable();
            $table->unsignedBigInteger('legacy_submission_id')->nullable();
            $table->timestamps();

            $table->index('form_id');
            $table->index('account_id');
            $table->index('submitted_at');
            $table->index('submission_status');
            $table->index('current_workflow_status');
            $table->index('current_actor_id');
            $table->index('current_step_id');
            $table->index('revision_of');
            $table->index('root_submission_id');
            $table->index('is_latest_revision');
            $table->index(['form_id', 'is_latest_revision', 'submitted_at'], 'idx_form_latest_submitted');
            $table->index(['account_id', 'submitted_at'], 'idx_account_submitted');
            $table->index(['form_id', 'current_workflow_status', 'submitted_at'], 'idx_form_workflow_status_submitted');
            $table->unique(['legacy_form_table', 'legacy_submission_id'], 'uq_legacy_form_submission');

            $table->foreign('form_id')
                ->references('id')
                ->on('tbl_form')
                ->cascadeOnDelete();
            $table->foreign('account_id')
                ->references('account_id')
                ->on('tbl_user')
                ->restrictOnDelete();
            $table->foreign('revision_of')
                ->references('id')
                ->on('tbl_form_submission')
                ->nullOnDelete();
            $table->foreign('root_submission_id')
                ->references('id')
                ->on('tbl_form_submission')
                ->nullOnDelete();
            $table->foreign('current_step_id')
                ->references('id')
                ->on('tbl_workflow_step')
                ->nullOnDelete();
            $table->foreign('current_actor_id')
                ->references('account_id')
                ->on('tbl_user')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_form_submission');
    }
};
