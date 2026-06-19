<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tbl_formfield', function (Blueprint $table) {
            $table->boolean('is_publicly_verifiable')->default(true)->after('conditions');
            $table->boolean('is_sensitive')->default(false)->after('is_publicly_verifiable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_formfield', function (Blueprint $table) {
            $table->dropColumn(['is_publicly_verifiable', 'is_sensitive']);
        });
    }
};
