<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Workflows
        Schema::create('tbl_workflow', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_name');
            $table->string('workflow_type', 100);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->text('description')->nullable();
            $table->longText('workflow_settings')->nullable();
            $table->enum('status', ['Active', 'Draft', 'Archived'])->default('Draft');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique(['form_id', 'status', 'version']);

            $table->foreign('form_id')
                ->references('id')
                ->on('tbl_form')
                ->cascadeOnDelete();
        });

        // Workflow Steps
        Schema::create('tbl_workflow_step', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->string('step_name');
            $table->text('step_description')->nullable();
            $table->integer('step_order')->default(0);
            $table->integer('step_group')->default(0);
            $table->enum('action_type', ['Approve', 'Review', 'Verify', 'Noted', 'Notify']);
            $table->unsignedBigInteger('assigned_account_id')->nullable();
            $table->integer('max_duration_hours')->nullable();
            $table->longText('step_conditions')->nullable();
            $table->unsignedBigInteger('if_rejected_id')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')
                ->references('id')
                ->on('tbl_workflow')
                ->cascadeOnDelete();
        });

        // Workflow Step Approvers pivot
        Schema::create('tbl_workflow_step_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('step_id');
            $table->unsignedBigInteger('account_id');
            $table->enum('condition', ['primary', 'or'])->default('primary');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->unique(['step_id', 'account_id'], 'unique_step_approver');
            $table->index(['step_id', 'condition'], 'idx_step_condition');

            $table->foreign('step_id')
                ->references('id')
                ->on('tbl_workflow_step')
                ->cascadeOnDelete();
            $table->foreign('account_id')
                ->references('account_id')
                ->on('tbl_user')
                ->cascadeOnDelete();
        });

        // Workflow Step Progress
        Schema::create('tbl_workflow_step_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedInteger('workflow_version')->default(1);
            $table->unsignedBigInteger('step_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('action_taken', 100)->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Waiting', 'Skipped'])->default('Pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'submission_id']);
            $table->index(['step_id', 'submission_id']);
            $table->index('status');
            $table->index('actor_id');
            $table->index(['status', 'started_at'], 'wsp_status_started_idx');
        });

        // Workflow Step Progress Comment Attachments
        Schema::create('tbl_workflow_step_progress_comment_attachment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('progress_id')->index();
            $table->unsignedBigInteger('uploaded_by')->index();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_workflow_step_progress_comment_attachment');
        Schema::dropIfExists('tbl_workflow_step_progress');
        Schema::dropIfExists('tbl_workflow_step_approvers');
        Schema::dropIfExists('tbl_workflow_step');
        Schema::dropIfExists('tbl_workflow');
    }
};
