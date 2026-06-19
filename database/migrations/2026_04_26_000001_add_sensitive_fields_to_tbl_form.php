<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_form', function (Blueprint $table): void {
            $table->json('sensitive_fields')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_form', function (Blueprint $table): void {
            $table->dropColumn('sensitive_fields');
        });
    }
};
