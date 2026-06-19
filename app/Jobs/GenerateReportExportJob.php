<?php

namespace App\Jobs;

use App\Modules\Reports\Services\CsvExportWriter;
use App\Modules\Reports\Services\ReportExportOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly string $exportId,
        public readonly array $filters,
        public readonly int $requestedBy,
    ) {}

    public function handle(CsvExportWriter $csvExportWriter, ReportExportOrchestrator $orchestrator): void
    {
        $orchestrator->markProcessing($this->exportId);

        try {
            $csvData = $csvExportWriter->buildCsvExportData($this->filters);
            $filename = sprintf('%s-%s', $this->exportId, $csvData['filename']);
            $filePath = 'exports/async/'.$filename;

            Storage::disk('local')->put($filePath, $csvData['content']);

            $orchestrator->markCompleted($this->exportId, $filePath, $filename);
        } catch (Throwable $exception) {
            $orchestrator->markFailed($this->exportId, $exception->getMessage());

            throw $exception;
        }
    }
}
