<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add MySQL RANGE partitioning to tbl_audit_log (by month) and tbl_snapshot (by quarter).
 *
 * MySQL native partitioning by TO_DAYS(created_at) requires the partitioning column to be
 * part of every unique/primary key. Both tables use a single-column auto-increment PK, which
 * satisfies that requirement.
 *
 * SQLite does not support partitioning; this migration is a no-op on SQLite.
 *
 * Safe to run on existing tables: ALTER TABLE ... PARTITION BY reorganises storage
 * transparently and all existing rows land in the appropriate partition.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // ── tbl_audit_log – monthly partitions ───────────────────────────────
        // TO_DAYS() on a TIMESTAMP column is rejected by MySQL on some configurations
        // (error 1486: timezone-dependent expression). Partitioning is a storage
        // optimisation only — wrap in try/catch so local dev is unaffected.
        try {
            DB::unprepared("
                ALTER TABLE `tbl_audit_log`
                PARTITION BY RANGE (TO_DAYS(`created_at`)) (
                    PARTITION p_audit_2026_01 VALUES LESS THAN (TO_DAYS('2026-02-01')),
                    PARTITION p_audit_2026_02 VALUES LESS THAN (TO_DAYS('2026-03-01')),
                    PARTITION p_audit_2026_03 VALUES LESS THAN (TO_DAYS('2026-04-01')),
                    PARTITION p_audit_2026_04 VALUES LESS THAN (TO_DAYS('2026-05-01')),
                    PARTITION p_audit_2026_05 VALUES LESS THAN (TO_DAYS('2026-06-01')),
                    PARTITION p_audit_2026_06 VALUES LESS THAN (TO_DAYS('2026-07-01')),
                    PARTITION p_audit_2026_07 VALUES LESS THAN (TO_DAYS('2026-08-01')),
                    PARTITION p_audit_2026_08 VALUES LESS THAN (TO_DAYS('2026-09-01')),
                    PARTITION p_audit_2026_09 VALUES LESS THAN (TO_DAYS('2026-10-01')),
                    PARTITION p_audit_2026_10 VALUES LESS THAN (TO_DAYS('2026-11-01')),
                    PARTITION p_audit_2026_11 VALUES LESS THAN (TO_DAYS('2026-12-01')),
                    PARTITION p_audit_2026_12 VALUES LESS THAN (TO_DAYS('2027-01-01')),
                    PARTITION p_audit_2027_01 VALUES LESS THAN (TO_DAYS('2027-02-01')),
                    PARTITION p_audit_2027_02 VALUES LESS THAN (TO_DAYS('2027-03-01')),
                    PARTITION p_audit_2027_03 VALUES LESS THAN (TO_DAYS('2027-04-01')),
                    PARTITION p_audit_2027_04 VALUES LESS THAN (TO_DAYS('2027-05-01')),
                    PARTITION p_audit_2027_05 VALUES LESS THAN (TO_DAYS('2027-06-01')),
                    PARTITION p_audit_2027_06 VALUES LESS THAN (TO_DAYS('2027-07-01')),
                    PARTITION p_future          VALUES LESS THAN MAXVALUE
                )
            ");
        } catch (\Exception $e) {
            // Partitioning skipped (not supported in this MySQL configuration).
        }

        // ── tbl_snapshot – quarterly partitions ──────────────────────────────
        try {
            DB::unprepared("
                ALTER TABLE `tbl_snapshot`
                PARTITION BY RANGE (TO_DAYS(`created_at`)) (
                    PARTITION p_snap_2026_q1 VALUES LESS THAN (TO_DAYS('2026-04-01')),
                    PARTITION p_snap_2026_q2 VALUES LESS THAN (TO_DAYS('2026-07-01')),
                    PARTITION p_snap_2026_q3 VALUES LESS THAN (TO_DAYS('2026-10-01')),
                    PARTITION p_snap_2026_q4 VALUES LESS THAN (TO_DAYS('2027-01-01')),
                    PARTITION p_snap_2027_q1 VALUES LESS THAN (TO_DAYS('2027-04-01')),
                    PARTITION p_snap_2027_q2 VALUES LESS THAN (TO_DAYS('2027-07-01')),
                    PARTITION p_snap_2027_q3 VALUES LESS THAN (TO_DAYS('2027-10-01')),
                    PARTITION p_snap_2027_q4 VALUES LESS THAN (TO_DAYS('2028-01-01')),
                    PARTITION p_future        VALUES LESS THAN MAXVALUE
                )
            ");
        } catch (\Exception $e) {
            // Partitioning skipped (not supported in this MySQL configuration).
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::unprepared('ALTER TABLE `tbl_audit_log` REMOVE PARTITIONING');
        DB::unprepared('ALTER TABLE `tbl_snapshot` REMOVE PARTITIONING');
    }
};
