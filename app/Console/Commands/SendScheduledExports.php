<?php

namespace App\Console\Commands;

use App\Mail\ScheduledExportMail;
use App\Modules\Reports\Services\ScheduledExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendScheduledExports extends Command
{
    protected $signature = 'reports:send-scheduled-exports';

    protected $description = 'Send due scheduled report exports via email';

    public function __construct(
        private readonly ScheduledExportService $exportService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $exports = $this->exportService->findDue();

        if ($exports->isEmpty()) {
            $this->info('No scheduled exports are due.');

            return self::SUCCESS;
        }

        $this->info("Processing {$exports->count()} due scheduled export(s).");

        $sent = 0;
        $failed = 0;

        foreach ($exports as $export) {
            try {
                $attachment = $this->exportService->buildExportAttachment($export);

                Mail::to($export->recipient_email)->send(
                    new ScheduledExportMail($export, $attachment['filename'], $attachment['content'])
                );

                $export->update(['last_sent_at' => now()]);

                $sent++;
                $this->info("  ✓ #{$export->id} → {$export->recipient_email}");
            } catch (Throwable $e) {
                $failed++;
                $this->error("  ✗ #{$export->id}: {$e->getMessage()}");
                Log::error('reports:send-scheduled-exports failed', [
                    'scheduled_export_id' => $export->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Done. Sent: {$sent}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}
