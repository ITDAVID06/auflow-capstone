<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('tbl_form_submission', function (Blueprint $table) {
            $table->string('v_student_id')->virtualAs("JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.student_id'))")->nullable();
            $table->string('v_department_code')->virtualAs("JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.department_code'))")->nullable();

            $table->index('v_student_id', 'idx_virtual_student_id');
            $table->index('v_department_code', 'idx_virtual_department_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('tbl_form_submission', function (Blueprint $table) {
            $table->dropIndex('idx_virtual_student_id');
            $table->dropIndex('idx_virtual_department_code');
            $table->dropColumn(['v_student_id', 'v_department_code']);
        });
    }
};
