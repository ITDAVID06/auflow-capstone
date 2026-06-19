<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add rendered_html_path to tbl_snapshot.
 *
 * New snapshots that generate HTML will store the content on S3 (or the
 * configured SNAPSHOT_STORAGE_DISK) and record the object key here.
 * The rendered_html DB column was already removed; this column is its
 * off-database successor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_snapshot', function (Blueprint $table) {
            $table->string('rendered_html_path')->nullable()->after('action_hash');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_snapshot', function (Blueprint $table) {
            $table->dropColumn('rendered_html_path');
        });
    }
};
