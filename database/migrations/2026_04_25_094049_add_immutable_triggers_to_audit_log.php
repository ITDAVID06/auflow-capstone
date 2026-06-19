<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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

        DB::unprepared("
            CREATE TRIGGER prevent_audit_update
            BEFORE UPDATE ON tbl_audit_log
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Audit log is append-only';
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER prevent_audit_delete
            BEFORE DELETE ON tbl_audit_log
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Audit log is append-only';
            END;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS prevent_audit_update');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_audit_delete');
    }
};
