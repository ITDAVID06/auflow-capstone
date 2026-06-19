<?php

namespace App\Console\Commands;

use App\Modules\UserManagement\Models\User;
use App\Services\DemoSeeding\DemoSeederOrchestrator;
use App\Services\DemoSeeding\DemoSeedProfile;
use Database\Seeders\AdminAccountSeeder;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedDemoCommand extends Command
{
    protected $signature = 'seed:demo
        {--profile=medium : quick|medium|full}
        {--with-edge : Include edge-state scenarios}
        {--deterministic-only : Skip randomized expansion data}
        {--count-submissions= : Override profile submission target}
        {--fresh : Run migrate:fresh before seeding}';

    protected $description = 'Seed AUFlow demo data with deterministic and optional expanded datasets';

    public function __construct(protected DemoSeederOrchestrator $orchestrator)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $profile = strtolower((string) $this->option('profile'));
        $withEdge = (bool) $this->option('with-edge');
        $deterministicOnly = (bool) $this->option('deterministic-only');
        $override = $this->option('count-submissions');

        if (! in_array($profile, ['quick', 'medium', 'full'], true)) {
            $this->error('Invalid profile. Allowed values: quick, medium, full.');

            return self::FAILURE;
        }

        if ((bool) $this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $this->call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => AdminAccountSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoAccountSeeder::class, '--force' => true]);

        // Keep demo runs deterministic by resetting historical audit rows.
        // TRUNCATE is used intentionally to bypass the append-only trigger (demo reset only).
        DB::statement('TRUNCATE TABLE tbl_audit_log');

        $adminAccountId = (int) User::query()
            ->where('email', 'admin@auf.edu.ph')
            ->value('account_id');

        if ($adminAccountId <= 0) {
            $this->error('Admin account not found after baseline seeding.');

            return self::FAILURE;
        }

        $profileConfig = DemoSeedProfile::fromOptions(
            name: $profile,
            submissionOverride: is_numeric($override) ? (int) $override : null,
            deterministicOnly: $deterministicOnly,
        );

        $summary = $this->orchestrator->run(
            adminAccountId: $adminAccountId,
            profile: $profileConfig,
            withEdge: $withEdge,
        );

        $this->info('Demo seeding completed.');
        $this->line('');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Profile', $profileConfig->name],
                ['With Edge Scenarios', $withEdge ? 'yes' : 'no'],
                ['Deterministic Only', $deterministicOnly ? 'yes' : 'no'],
                ['Forms', (string) ($summary['forms'] ?? 0)],
                ['Workflows', (string) ($summary['workflows'] ?? 0)],
                ['Submissions', (string) ($summary['submissions'] ?? 0)],
                ['Workflow Progress Entries', (string) ($summary['progress_entries'] ?? 0)],
                ['Snapshots', (string) ($summary['snapshots'] ?? 0)],
                ['Notifications', (string) ($summary['notifications'] ?? 0)],
                ['Audit Logs', (string) ($summary['audit_logs'] ?? 0)],
            ]
        );

        if (! empty($summary['scenario_counts'])) {
            $this->line('');
            $this->info('Scenario Distribution');
            foreach ($summary['scenario_counts'] as $scenario => $count) {
                $this->line("- {$scenario}: {$count}");
            }
        }

        $this->line('');
        $this->info('Demo Accounts');
        $this->line('- admin@auf.test / password');
        $this->line('- staff1@auf.test / password');
        $this->line('- student1@auf.test / password');

        return self::SUCCESS;
    }
}
