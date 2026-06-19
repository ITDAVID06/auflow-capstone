<?php

namespace App\Console\Commands;

use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\VerificationSnapshot\Services\SnapshotSecurityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillSnapshotHashes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snapshot:backfill-hashes {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill action_hash for existing snapshots that don\'t have one';

    protected $securityService;

    public function __construct(SnapshotSecurityService $securityService)
    {
        parent::__construct();
        $this->securityService = $securityService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        // Get snapshots without action_hash
        $snapshots = Snapshot::whereNull('action_hash')->get();

        if ($snapshots->isEmpty()) {
            $this->info('✅ No snapshots need backfilling. All snapshots already have action_hash.');

            return Command::SUCCESS;
        }

        $this->info("Found {$snapshots->count()} snapshot(s) without action_hash");

        $bar = $this->output->createProgressBar($snapshots->count());
        $bar->start();

        $success = 0;
        $failed = 0;
        $errors = [];

        // Temporarily disable the immutability trigger for backfill
        if (! $dryRun) {
            DB::statement('DROP TRIGGER IF EXISTS trg_tbl_snapshot_no_update');

            // Remove ON UPDATE CURRENT_TIMESTAMP from approved_at to preserve original timestamps
            DB::statement('ALTER TABLE tbl_snapshot MODIFY COLUMN approved_at timestamp NOT NULL DEFAULT current_timestamp()');

            $this->info('🔓 Temporarily disabled immutability protections for backfill');
        }

        foreach ($snapshots as $snapshot) {
            try {
                // Parse payload
                $payload = is_string($snapshot->payload_json)
                    ? json_decode($snapshot->payload_json, true)
                    : $snapshot->payload_json;

                // Get actor_id - check multiple sources
                // 1. Direct column (old structure)
                $actorId = $snapshot->approved_by ?? $snapshot->rejected_by ?? null;

                // 2. From payload if not found in columns (new structure)
                if (! $actorId) {
                    $actorId = $payload['approved_by'] ?? $payload['rejected_by'] ?? null;
                }

                if (! $actorId) {
                    throw new \Exception("No actor_id found for snapshot {$snapshot->public_id}");
                }

                // Get timestamp - use approved_at or created_at
                $timestamp = $snapshot->approved_at
                    ? strtotime($snapshot->approved_at)
                    : $snapshot->created_at->timestamp;

                // Generate hash
                $hash = $this->securityService->generateActionHash($actorId, $timestamp, $payload);

                if (! $dryRun) {
                    // Bypass trigger by using raw update
                    DB::table('tbl_snapshot')
                        ->where('id', $snapshot->id)
                        ->update(['action_hash' => $hash]);
                }

                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'public_id' => $snapshot->public_id,
                    'error' => $e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Recreate the trigger and restore approved_at behavior after backfill
        if (! $dryRun) {
            // Restore ON UPDATE CURRENT_TIMESTAMP on approved_at
            DB::statement('ALTER TABLE tbl_snapshot MODIFY COLUMN approved_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()');

            // Recreate immutability trigger (only blocks updates when locked = 1)
            DB::statement("
                CREATE TRIGGER trg_tbl_snapshot_no_update
                BEFORE UPDATE ON tbl_snapshot
                FOR EACH ROW
                BEGIN
                    IF OLD.locked = 1 THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Snapshots are immutable';
                    END IF;
                END
            ");
            $this->info('🔒 Re-enabled immutability protections');
        }

        // Summary
        if ($dryRun) {
            $this->info("✅ Dry run completed. Would have processed {$snapshots->count()} snapshot(s).");
        } else {
            $this->info('✅ Backfill completed!');
        }

        $this->table(
            ['Status', 'Count'],
            [
                ['Success', $success],
                ['Failed', $failed],
                ['Total', $snapshots->count()],
            ]
        );

        if (! empty($errors)) {
            $this->error("\n❌ Errors encountered:");
            $this->table(
                ['Snapshot ID', 'Error'],
                $errors
            );
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
