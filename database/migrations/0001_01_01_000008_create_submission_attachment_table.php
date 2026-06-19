<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_submission_attachment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->string('form_table');
            $table->string('field_name');
            $table->string('original_name');
            $table->string('stored_path');
            $table->timestamps();

            $table->index(['submission_id', 'form_table']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_submission_attachment');
    }
};
