<?php

namespace App\Console\Commands;

use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeWorkflowData extends Command
{
    protected $signature = 'auflow:normalize-workflows';

    protected $description = 'Normalize workflow status + backfill version columns';

    public function handle(): int
    {
        DB::table('tbl_workflow')->whereRaw('LOWER(status)="draft"')->update(['status' => 'Draft']);
        DB::table('tbl_workflow')->whereRaw('LOWER(status)="active"')->update(['status' => 'Active']);
        DB::table('tbl_workflow')->whereRaw('LOWER(status) in ("archive","archived")')->update(['status' => 'Archived']);

        DB::table('tbl_workflow')->whereNull('version')->update(['version' => 1]);
        WorkflowStepProgress::query()->whereNull('workflow_version')->update(['workflow_version' => 1]);

        $this->info('Workflow data normalized.');

        return self::SUCCESS;
    }
}
