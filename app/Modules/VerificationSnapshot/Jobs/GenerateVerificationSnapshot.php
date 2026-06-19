<?php

namespace App\Modules\VerificationSnapshot\Jobs;

use App\Modules\VerificationSnapshot\Services\SnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateVerificationSnapshot implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Keep uniqueness lock for 5 minutes.
     */
    public int $uniqueFor = 300;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param  int  $progressId  The workflow step progress ID
     */
    public function __construct(
        public readonly int $progressId
    ) {}

    public function uniqueId(): string
    {
        return 'snapshot-progress-'.$this->progressId;
    }

    /**
     * Execute the job.
     */
    public function handle(SnapshotService $snapshotService): void
    {
        try {
            Log::info('[Snapshot Queue] Generating snapshot', [
                'progress_id' => $this->progressId,
                'attempt' => $this->attempts(),
            ]);

            $snapshot = $snapshotService->createFromProgress($this->progressId);

            Log::info('[Snapshot Queue] Snapshot generated successfully', [
                'progress_id' => $this->progressId,
                'snapshot_id' => $snapshot->id,
                'public_id' => $snapshot->public_id,
            ]);

        } catch (\Throwable $e) {
            Log::error('[Snapshot Queue] Failed to generate snapshot', [
                'progress_id' => $this->progressId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to mark job as failed and trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[Snapshot Queue] Snapshot generation failed permanently', [
            'progress_id' => $this->progressId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Optional: Send notification to admins about failed snapshot generation
        // You could dispatch a notification here if critical
    }
}
