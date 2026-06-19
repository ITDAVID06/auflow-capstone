<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tbl_form_submission')) {
            return;
        }

        Schema::table('tbl_form_submission', function (Blueprint $table): void {
            if (! Schema::hasColumn('tbl_form_submission', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('account_id');
            }
        });

        Schema::table('tbl_form_submission', function (Blueprint $table): void {
            $table->unique('idempotency_key', 'tbl_form_submission_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tbl_form_submission')) {
            return;
        }

        Schema::table('tbl_form_submission', function (Blueprint $table): void {
            $table->dropUnique('tbl_form_submission_idempotency_key_unique');

            if (Schema::hasColumn('tbl_form_submission', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
