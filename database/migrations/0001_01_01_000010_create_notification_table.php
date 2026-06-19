<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_notification', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('type', 50);
            $table->string('title', 255);
            $table->text('message');
            $table->string('action_url', 500)->nullable();
            $table->string('action_text', 100)->nullable();
            $table->string('related_type', 50)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('icon', 50)->default('bell');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['account_id', 'is_read', 'created_at'], 'idx_account_unread');
            $table->index('created_at', 'idx_created');
            $table->index(['related_type', 'related_id'], 'idx_related');

            $table->foreign('account_id')
                ->references('account_id')
                ->on('tbl_user')
                ->cascadeOnDelete();

            $table->foreign('triggered_by')
                ->references('account_id')
                ->on('tbl_user')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_notification');
    }
};
