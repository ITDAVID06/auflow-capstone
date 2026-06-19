<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportsCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_deletes_files_older_than_ttl(): void
    {
        Storage::fake('local');

        $ttl = 3600;
        config(['reports.async_export_cache_ttl_seconds' => $ttl]);

        $oldTime    = now()->subSeconds($ttl + 60)->timestamp;
        $recentTime = now()->subSeconds($ttl - 60)->timestamp;

        Storage::disk('local')->put('exports/async/old_export.csv', 'data');
        Storage::disk('local')->put('exports/async/recent_export.csv', 'data');

        // Manually set mtime on the fake filesystem
        $root    = Storage::disk('local')->path('');
        touch($root . 'exports/async/old_export.csv', $oldTime);
        touch($root . 'exports/async/recent_export.csv', $recentTime);

        $this->artisan('reports:cleanup-exports')
            ->expectsOutputToContain('Deleted 1 expired async export file(s).')
            ->assertExitCode(0);

        Storage::disk('local')->assertMissing('exports/async/old_export.csv');
        Storage::disk('local')->assertExists('exports/async/recent_export.csv');
    }

    public function test_cleanup_leaves_recently_created_files(): void
    {
        Storage::fake('local');

        $ttl = 3600;
        config(['reports.async_export_cache_ttl_seconds' => $ttl]);

        $recentTime = now()->subSeconds($ttl - 60)->timestamp;

        Storage::disk('local')->put('exports/async/recent_export.csv', 'data');
        $root = Storage::disk('local')->path('');
        touch($root . 'exports/async/recent_export.csv', $recentTime);

        $this->artisan('reports:cleanup-exports')
            ->expectsOutputToContain('Deleted 0 expired async export file(s).')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists('exports/async/recent_export.csv');
    }

    public function test_cleanup_is_a_no_op_when_directory_is_empty(): void
    {
        Storage::fake('local');

        config(['reports.async_export_cache_ttl_seconds' => 3600]);

        $this->artisan('reports:cleanup-exports')
            ->expectsOutputToContain('Deleted 0 expired async export file(s).')
            ->assertExitCode(0);
    }
}
