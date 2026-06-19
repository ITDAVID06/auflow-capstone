<?php

namespace App\Modules\Reports\Services;

use App\Jobs\GenerateReportExportJob;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ReportExportOrchestrator
{
    public function asyncExportThreshold(): int
    {
        return max(1, (int) config('reports.async_export_threshold', 2000));
    }

    public function shouldQueueExport(int $estimatedRows): bool
    {
        return $estimatedRows > $this->asyncExportThreshold();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function queueExport(array $filters, int $requestedBy, int $estimatedRows): array
    {
        $exportId = (string) Str::uuid();

        $payload = [
            'export_id' => $exportId,
            'status' => 'queued',
            'requested_by' => $requestedBy,
            'estimated_rows' => $estimatedRows,
            'filters' => $filters,
            'filename' => null,
            'file_path' => null,
            'error' => null,
            'created_at' => now()->toIso8601String(),
        ];

        Cache::put($this->cacheKey($exportId), $payload, now()->addSeconds($this->cacheTtlSeconds()));

        GenerateReportExportJob::dispatch($exportId, $filters, $requestedBy);

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getExport(string $exportId): ?array
    {
        $payload = Cache::get($this->cacheKey($exportId));

        return is_array($payload) ? $payload : null;
    }

    public function markProcessing(string $exportId): void
    {
        $this->updateExport($exportId, static function (array $payload): array {
            $payload['status'] = 'processing';
            $payload['error'] = null;

            return $payload;
        });
    }

    public function markCompleted(string $exportId, string $filePath, string $filename): void
    {
        $this->updateExport($exportId, static function (array $payload) use ($filePath, $filename): array {
            $payload['status'] = 'completed';
            $payload['file_path'] = $filePath;
            $payload['filename'] = $filename;
            $payload['error'] = null;
            $payload['completed_at'] = now()->toIso8601String();

            return $payload;
        });
    }

    public function markFailed(string $exportId, string $error): void
    {
        $this->updateExport($exportId, static function (array $payload) use ($error): array {
            $payload['status'] = 'failed';
            $payload['error'] = $error;
            $payload['completed_at'] = now()->toIso8601String();

            return $payload;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function canAccessExport(array $payload, ?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        $requestedBy = (int) ($payload['requested_by'] ?? 0);
        $accountId = (int) ($user->account_id ?? 0);

        if ($requestedBy > 0 && $requestedBy === $accountId) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission('submissions.override');
    }

    public function cacheKey(string $exportId): string
    {
        return 'reports.exports.'.$exportId;
    }

    private function cacheTtlSeconds(): int
    {
        return max(300, (int) config('reports.async_export_cache_ttl_seconds', 7200));
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $updater
     */
    private function updateExport(string $exportId, callable $updater): void
    {
        $payload = $this->getExport($exportId);

        if (! is_array($payload)) {
            return;
        }

        $updated = $updater($payload);
        Cache::put($this->cacheKey($exportId), $updated, now()->addSeconds($this->cacheTtlSeconds()));
    }
}
