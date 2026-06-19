<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_notification', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->after('triggered_by');
            $table->unique('idempotency_key', 'tbl_notification_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_notification', function (Blueprint $table) {
            $table->dropUnique('tbl_notification_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
