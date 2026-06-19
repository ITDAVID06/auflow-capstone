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
        if (Schema::hasColumn('tbl_role', 'role_type')) {
            Schema::table('tbl_role', function (Blueprint $table) {
                $table->dropColumn('role_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('tbl_role', 'role_type')) {
            Schema::table('tbl_role', function (Blueprint $table) {
                $table->enum('role_type', ['Requester', 'Approver', 'Verifier', 'Admin', 'Auditor'])->default('Requester')->after('description');
            });
        }
    }
};
