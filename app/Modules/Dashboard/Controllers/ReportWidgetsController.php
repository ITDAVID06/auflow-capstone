<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\FormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ReportWidgetsController extends Controller
{
    /**
     * GET /dashboard/report-widgets/submission-trend
     *
     * Returns the daily submission counts for the last 30 days, cached for 60 s.
     * Used by the admin-dashboard embedded widget.
     */
    public function submissionTrend(): JsonResponse
    {
        $data = Cache::remember('dashboard.report_widgets.trend', 60, function (): array {
            $rows = FormSubmission::query()
                ->selectRaw('DATE(submitted_at) as day, COUNT(*) as cnt')
                ->where('submitted_at', '>=', now()->subDays(29)->startOfDay())
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            return $rows->map(fn ($r) => [
                'date' => (string) $r->day,
                'count' => (int) $r->cnt,
            ])->values()->all();
        });

        return response()->json($data);
    }

    /**
     * GET /dashboard/report-widgets/status-breakdown
     *
     * Returns per-status submission counts for the current calendar month,
     * cached for 60 s. Used by the admin-dashboard embedded widget.
     */
    public function statusBreakdown(): JsonResponse
    {
        $data = Cache::remember('dashboard.report_widgets.status_breakdown', 60, function (): array {
            $rows = FormSubmission::query()
                ->selectRaw("COALESCE(LOWER(submission_status), 'unknown') as status, COUNT(*) as cnt")
                ->whereYear('submitted_at', now()->year)
                ->whereMonth('submitted_at', now()->month)
                ->groupBy('status')
                ->orderByDesc('cnt')
                ->get();

            return $rows->map(fn ($r) => [
                'status' => (string) $r->status,
                'count' => (int) $r->cnt,
            ])->values()->all();
        });

        return response()->json($data);
    }
}
