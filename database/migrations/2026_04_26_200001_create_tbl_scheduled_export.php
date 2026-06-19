<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_scheduled_export', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->string('recipient_email', 255);
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->enum('export_type', ['csv', 'pdf']);
            $table->json('filter_state');
            $table->timestamp('last_sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('tbl_form')->cascadeOnDelete();
            $table->foreign('created_by')->references('account_id')->on('tbl_user')->cascadeOnDelete();

            $table->index(['created_by', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_scheduled_export');
    }
};
