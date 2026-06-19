<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupAsyncExports extends Command
{
    protected $signature = 'reports:cleanup-exports';

    protected $description = 'Delete async export files under storage/app/exports/async/ older than the configured TTL';

    public function handle(): int
    {
        $ttl    = max(1, (int) config('reports.async_export_cache_ttl_seconds', 3600));
        $cutoff = now()->subSeconds($ttl)->timestamp;

        $files   = Storage::disk('local')->files('exports/async');
        $deleted = 0;

        foreach ($files as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);

            if ($lastModified < $cutoff) {
                Storage::disk('local')->delete($file);
                $deleted++;
            }
        }

        $this->line("Deleted {$deleted} expired async export file(s).");

        return self::SUCCESS;
    }
}
