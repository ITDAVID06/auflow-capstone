<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_error_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('message', 2000);
            $table->longText('stack');
            $table->string('url', 2048);
            $table->string('user_agent', 512);
            $table->text('comment')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('account_id')
                ->on('tbl_user')
                ->onDelete('set null');

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_error_reports');
    }
};
