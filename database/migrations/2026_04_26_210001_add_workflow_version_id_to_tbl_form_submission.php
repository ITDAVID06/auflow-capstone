<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_form_submission', function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_version_id')->nullable()->after('form_id');

            $table->foreign('workflow_version_id')
                ->references('id')
                ->on('tbl_workflow_version')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_form_submission', function (Blueprint $table) {
            $table->dropForeign(['workflow_version_id']);
            $table->dropColumn('workflow_version_id');
        });
    }
};
