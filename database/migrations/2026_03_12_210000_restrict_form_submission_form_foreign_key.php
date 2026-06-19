<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_form_submission', function (Blueprint $table): void {
            $table->dropForeign(['form_id']);

            $table->foreign('form_id')
                ->references('id')
                ->on('tbl_form')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_form_submission', function (Blueprint $table): void {
            $table->dropForeign(['form_id']);

            $table->foreign('form_id')
                ->references('id')
                ->on('tbl_form')
                ->cascadeOnDelete();
        });
    }
};
