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
            Schema::table('tbl_submission_attachment', function (Blueprint $table) {
                if (! Schema::hasColumn('tbl_submission_attachment', 'file_path')) {
                    $table->string('file_path')->nullable();
                }
                if (! Schema::hasColumn('tbl_submission_attachment', 'mime_type')) {
                    $table->string('mime_type')->nullable();
                }
                if (! Schema::hasColumn('tbl_submission_attachment', 'uploaded_by')) {
                    $table->unsignedBigInteger('uploaded_by')->nullable();
                }
            });

            return;
        }

        if (Schema::hasColumn('tbl_submission_attachment', 'field_name') || Schema::hasColumn('tbl_submission_attachment', 'stored_path')) {
            Schema::table('tbl_submission_attachment', function (Blueprint $table) {
                if (Schema::hasColumn('tbl_submission_attachment', 'field_name')) {
                    $table->dropColumn('field_name');
                }
                if (Schema::hasColumn('tbl_submission_attachment', 'stored_path')) {
                    $table->dropColumn('stored_path');
                }
            });
        }

        Schema::table('tbl_submission_attachment', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_submission_attachment', 'file_path')) {
                $table->string('file_path')->nullable();
            }
            if (! Schema::hasColumn('tbl_submission_attachment', 'mime_type')) {
                $table->string('mime_type')->nullable();
            }
            if (! Schema::hasColumn('tbl_submission_attachment', 'uploaded_by')) {
                $table->unsignedBigInteger('uploaded_by')->nullable();
            }
        });

        if (Schema::hasColumn('tbl_submission_attachment', 'stored_path')) {
            DB::statement('UPDATE `tbl_submission_attachment` SET `file_path` = `stored_path` WHERE `file_path` IS NULL OR `file_path` = ""');
        }

        DB::statement('UPDATE `tbl_submission_attachment` SET `uploaded_by` = 1 WHERE `uploaded_by` IS NULL');

        DB::statement('ALTER TABLE `tbl_submission_attachment` MODIFY `file_path` varchar(255) NOT NULL');
        DB::statement('ALTER TABLE `tbl_submission_attachment` MODIFY `uploaded_by` bigint unsigned NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('tbl_submission_attachment', function (Blueprint $table) {
            if (! Schema::hasColumn('tbl_submission_attachment', 'field_name')) {
                $table->string('field_name')->nullable();
            }
            if (! Schema::hasColumn('tbl_submission_attachment', 'stored_path')) {
                $table->string('stored_path')->nullable();
            }
        });

        if (Schema::hasColumn('tbl_submission_attachment', 'file_path')) {
            DB::statement('UPDATE `tbl_submission_attachment` SET `stored_path` = `file_path` WHERE `stored_path` IS NULL OR `stored_path` = ""');
        }

        Schema::table('tbl_submission_attachment', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_submission_attachment', 'file_path')) {
                $table->dropColumn('file_path');
            }
            if (Schema::hasColumn('tbl_submission_attachment', 'mime_type')) {
                $table->dropColumn('mime_type');
            }
            if (Schema::hasColumn('tbl_submission_attachment', 'uploaded_by')) {
                $table->dropColumn('uploaded_by');
            }
        });
    }
};
