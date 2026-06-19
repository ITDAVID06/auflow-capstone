<?php

namespace App\Actions\ErrorReports;

use App\Modules\ErrorReports\Models\ErrorReport;
use Illuminate\Support\Facades\DB;

class UpdateErrorReportStatusAction
{
    public function execute(ErrorReport $report, string $status): void
    {
        DB::transaction(function () use ($report, $status): void {
            $report->status = $status;
            $report->save();
        });
    }
}
