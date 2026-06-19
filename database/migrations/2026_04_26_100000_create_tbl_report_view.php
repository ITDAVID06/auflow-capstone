<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_report_view', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->string('name', 255);
            $table->json('filter_state');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('tbl_form')->cascadeOnDelete();
            $table->foreign('created_by')->references('account_id')->on('tbl_user')->cascadeOnDelete();

            $table->index(['form_id', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_report_view');
    }
};
