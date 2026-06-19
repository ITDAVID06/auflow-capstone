<?php

namespace App\Actions\ErrorReports;

use App\Modules\ErrorReports\Models\ErrorReport;
use Illuminate\Support\Facades\DB;

class StoreErrorReportAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): ErrorReport
    {
        return DB::transaction(function () use ($data): ErrorReport {
            return ErrorReport::create($data);
        });
    }
}
