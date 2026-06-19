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
        Schema::table('tbl_snapshot', function (Blueprint $table): void {
            $table->unsignedBigInteger('workflow_id')->nullable()->change();
            $table->unsignedBigInteger('step_id')->nullable()->change();
            $table->unsignedBigInteger('approved_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_snapshot', function (Blueprint $table): void {
            $table->unsignedBigInteger('workflow_id')->nullable(false)->change();
            $table->unsignedBigInteger('step_id')->nullable(false)->change();
            $table->unsignedBigInteger('approved_by')->nullable(false)->change();
        });
    }
};
