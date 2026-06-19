<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the `rendered_html` column from tbl_snapshot.
 *
 * The column was never populated by SnapshotService and is therefore dead code.
 * Removing it eliminates any risk of it being mistaken for an integrity-protected
 * field (it was never covered by the action_hash HMAC).
 *
 * Safe to run: SELECT COUNT(*) FROM tbl_snapshot WHERE rendered_html IS NOT NULL
 * should return 0. Verify before running in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_snapshot', function (Blueprint $table) {
            $table->dropColumn('rendered_html');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_snapshot', function (Blueprint $table) {
            $table->mediumText('rendered_html')->nullable()->after('action_hash');
        });
    }
};
