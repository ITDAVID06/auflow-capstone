<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSeedIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_demo_can_run_twice_without_creating_duplicate_rows(): void
    {
        $this->artisan('seed:demo --profile=quick --with-edge --deterministic-only')
            ->assertExitCode(0);

        $first = $this->tableCounts();

        $this->artisan('seed:demo --profile=quick --with-edge --deterministic-only')
            ->assertExitCode(0);

        $second = $this->tableCounts();

        $this->assertSame($first, $second);

        $submissionCount = DB::table('tbl_form_submission')->whereNotNull('idempotency_key')->count();
        $distinctSubmissionKeys = DB::table('tbl_form_submission')->whereNotNull('idempotency_key')->distinct('idempotency_key')->count();

        $this->assertSame($submissionCount, $distinctSubmissionKeys);

        $notificationCount = DB::table('tbl_notification')->whereNotNull('idempotency_key')->count();
        $distinctNotificationKeys = DB::table('tbl_notification')->whereNotNull('idempotency_key')->distinct('idempotency_key')->count();

        $this->assertSame($notificationCount, $distinctNotificationKeys);
    }

    public function test_seed_demo_medium_profile_can_be_rerun_idempotently(): void
    {
        $this->artisan('seed:demo --profile=medium --with-edge --count-submissions=260')
            ->assertExitCode(0);

        $first = $this->tableCounts();

        $this->artisan('seed:demo --profile=medium --with-edge --count-submissions=260')
            ->assertExitCode(0);

        $second = $this->tableCounts();

        $this->assertSame($first, $second);
        $this->assertSame(260, (int) DB::table('tbl_form_submission')->count());
    }

    public function test_seed_demo_rerun_handles_existing_legacy_rows_without_idempotency_key(): void
    {
        $this->artisan('seed:demo --profile=quick --with-edge --deterministic-only')
            ->assertExitCode(0);

        DB::table('tbl_form_submission')
            ->orderBy('id')
            ->limit(3)
            ->update(['idempotency_key' => null]);

        $this->artisan('seed:demo --profile=quick --with-edge --deterministic-only')
            ->assertExitCode(0);

        $duplicateIdempotencyRows = DB::table('tbl_form_submission')
            ->select('idempotency_key', DB::raw('COUNT(*) as total'))
            ->whereNotNull('idempotency_key')
            ->groupBy('idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $duplicateIdempotencyRows);
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        return [
            'forms' => DB::table('tbl_form')->count(),
            'workflows' => DB::table('tbl_workflow')->count(),
            'submissions' => DB::table('tbl_form_submission')->count(),
            'progress' => DB::table('tbl_workflow_step_progress')->count(),
            'snapshots' => DB::table('tbl_snapshot')->count(),
            'notifications' => DB::table('tbl_notification')->count(),
            'audit' => DB::table('tbl_audit_log')->count(),
        ];
    }
}
