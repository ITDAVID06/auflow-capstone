<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_audit_log', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['user_action', 'system_event', 'security']);
            $table->string('action', 120);
            $table->string('status', 40)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable()->index();
            $table->string('actor_role')->nullable();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('snapshot_id')->nullable()->index();
            $table->char('snapshot_public_id', 32)->nullable();
            $table->string('qr_payload')->nullable();
            $table->string('qr_image_path')->nullable();
            $table->string('verification_result', 32)->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['category', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('category');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_audit_log');
    }
};
