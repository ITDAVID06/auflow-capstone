<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Manages MySQL RANGE partitions for append-only tables.
 *
 * Runs monthly via the scheduler. Each run:
 *   1. Creates missing future partitions (up to 6 months ahead for audit_log,
 *      2 quarters ahead for snapshot).
 *   2. Optionally drops/archives partitions older than a configurable threshold.
 *
 * No-op on SQLite (used in tests).
 */
class ManagePartitions extends Command
{
    protected $signature = 'partitions:manage
                            {--drop-before= : Drop audit_log partitions with data older than this date (Y-m-d). Irreversible.}
                            {--dry-run : Print planned actions without executing them.}';

    protected $description = 'Create future MySQL partitions for tbl_audit_log and tbl_snapshot, and optionally drop old ones.';

    public function handle(): int
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->info('SQLite detected – partitioning is a no-op.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->manageAuditLogPartitions($dryRun);
        $this->manageSnapshotPartitions($dryRun);

        $this->info($dryRun ? 'Dry-run complete – no changes applied.' : 'Partition management complete.');

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // tbl_audit_log – monthly partitions
    // ──────────────────────────────────────────────────────────────────────────

    private function manageAuditLogPartitions(bool $dryRun): void
    {
        $existing = $this->existingPartitions('tbl_audit_log');
        $lookahead = 6; // months ahead to ensure exist

        for ($i = 0; $i <= $lookahead; $i++) {
            $month = Carbon::now()->addMonths($i)->startOfMonth();
            $nextMonth = $month->copy()->addMonth();
            $partitionName = 'p_audit_'.$month->format('Y_m');

            if (in_array($partitionName, $existing, true)) {
                continue;
            }

            // Reorganise p_future to add the new month partition before it
            $upperBound = $nextMonth->format('Y-m-d');
            $sql = "ALTER TABLE `tbl_audit_log`
                    REORGANIZE PARTITION `p_future` INTO (
                        PARTITION `{$partitionName}` VALUES LESS THAN (TO_DAYS('{$upperBound}')),
                        PARTITION `p_future`           VALUES LESS THAN MAXVALUE
                    )";

            $this->line("  [audit_log] Add partition {$partitionName} (< {$upperBound})");

            if (! $dryRun) {
                DB::unprepared($sql);
            }
        }

        // Optional: drop old partitions
        $dropBefore = $this->option('drop-before');
        if ($dropBefore) {
            $cutoff = Carbon::createFromFormat('Y-m-d', $dropBefore);
            foreach ($existing as $name) {
                if (! preg_match('/^p_audit_(\d{4})_(\d{2})$/', $name, $m)) {
                    continue;
                }
                $partitionMonth = Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->endOfMonth();
                if ($partitionMonth->lt($cutoff)) {
                    $this->warn("  [audit_log] DROP partition {$name} (data before {$cutoff->format('Y-m-d')})");
                    if (! $dryRun) {
                        DB::unprepared("ALTER TABLE `tbl_audit_log` DROP PARTITION `{$name}`");
                    }
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // tbl_snapshot – quarterly partitions
    // ──────────────────────────────────────────────────────────────────────────

    private function manageSnapshotPartitions(bool $dryRun): void
    {
        $existing = $this->existingPartitions('tbl_snapshot');
        $lookahead = 2; // quarters ahead to ensure exist

        for ($i = 0; $i <= $lookahead; $i++) {
            [$year, $quarter] = $this->currentQuarterOffset($i);
            $partitionName = "p_snap_{$year}_q{$quarter}";

            if (in_array($partitionName, $existing, true)) {
                continue;
            }

            $nextQuarterStart = $this->quarterStart($year, $quarter)->addMonths(3);
            $upperBound = $nextQuarterStart->format('Y-m-d');

            $sql = "ALTER TABLE `tbl_snapshot`
                    REORGANIZE PARTITION `p_future` INTO (
                        PARTITION `{$partitionName}` VALUES LESS THAN (TO_DAYS('{$upperBound}')),
                        PARTITION `p_future`          VALUES LESS THAN MAXVALUE
                    )";

            $this->line("  [snapshot] Add partition {$partitionName} (< {$upperBound})");

            if (! $dryRun) {
                DB::unprepared($sql);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    private function existingPartitions(string $table): array
    {
        $rows = DB::select(
            'SELECT PARTITION_NAME
             FROM information_schema.PARTITIONS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND PARTITION_NAME IS NOT NULL',
            [$table]
        );

        return array_column($rows, 'PARTITION_NAME');
    }

    /**
     * Returns [year, quarter] offset by $quartersAhead from today.
     *
     * @return array{int, int}
     */
    private function currentQuarterOffset(int $quartersAhead): array
    {
        $date = Carbon::now()->addMonths($quartersAhead * 3);
        $quarter = (int) ceil($date->month / 3);

        return [$date->year, $quarter];
    }

    private function quarterStart(int $year, int $quarter): Carbon
    {
        $startMonth = ($quarter - 1) * 3 + 1;

        return Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
    }
}
