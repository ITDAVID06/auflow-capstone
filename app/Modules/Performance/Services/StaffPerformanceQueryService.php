<?php

declare(strict_types=1);

namespace App\Modules\Performance\Services;

use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StaffPerformanceQueryService
{
    /**
     * Get the main performance report metrics grouped by staff member.
     */
    public function getPerformanceReport(array $filters): array
    {
        $baseQuery = $this->buildBaseQuery($filters)
            ->whereNotNull('wsp.duration_seconds')
            ->whereNotNull('wsp.actor_id')
            ->whereIn('wsp.status', ['Approved', 'Rejected', 'Skipped']);

        $baseSql = $baseQuery->select(
            'wsp.actor_id',
            DB::raw('COALESCE(NULLIF(CONCAT(TRIM(up.first_name), " ", TRIM(up.last_name)), " "), u.username, u.email) as staff_name'),
            'up.department',
            'wsp.duration_seconds'
        )->toSql();

        $query = DB::table(DB::raw("
            (
                SELECT actor_id, staff_name, department, duration_seconds,
                       ROW_NUMBER() OVER (PARTITION BY actor_id ORDER BY duration_seconds) as rn,
                       COUNT(*) OVER (PARTITION BY actor_id) as total_count
                FROM ($baseSql) as raw_data
            ) as ranked
        "))
            ->mergeBindings($baseQuery->getQuery())
            ->select(
                'actor_id',
                DB::raw('MAX(staff_name) as staff_name'),
                DB::raw('MAX(department) as department'),
                DB::raw('MAX(total_count) as total_approvals'),
                DB::raw('ROUND(AVG(duration_seconds), 0) as avg_response_time_seconds'),
                DB::raw('MAX(duration_seconds) as longest_duration_seconds'),
                DB::raw('ROUND(AVG(CASE WHEN rn IN (FLOOR((total_count + 1) / 2), CEIL((total_count + 1) / 2)) THEN duration_seconds END), 0) as median_response_time_seconds')
            )
            ->groupBy('actor_id')
            ->orderByDesc('total_approvals');

        return $query->get()->map(function ($row) {
            return [
                'actor_id' => $row->actor_id,
                'staff_name' => $row->staff_name,
                'department' => $row->department ?? 'N/A',
                'total_approvals' => (int) $row->total_approvals,
                'avg_response_time_seconds' => (int) $row->avg_response_time_seconds,
                'median_response_time_seconds' => (int) $row->median_response_time_seconds,
                'longest_duration_seconds' => (int) $row->longest_duration_seconds,
            ];
        })->toArray();
    }

    /**
     * Get the staff members with the oldest currently pending tasks.
     */
    public function getCurrentlyPending(array $filters): array
    {
        $query = WorkflowStepProgress::query()->from('tbl_workflow_step_progress as wsp')
            ->join('tbl_workflow_step as ws', 'ws.id', '=', 'wsp.step_id')
            ->join('tbl_user as u', 'u.account_id', '=', 'ws.assigned_account_id')
            ->leftJoin('tbl_userprofile as up', 'up.account_id', '=', 'u.account_id')
            ->whereIn('wsp.status', ['Pending', 'In Review'])
            ->whereNotNull('wsp.started_at');

        if (! empty($filters['form_id'])) {
            $query->where('wsp.form_id', $filters['form_id']);
        }

        return $query->select(
            'ws.assigned_account_id as actor_id',
            DB::raw('COALESCE(NULLIF(CONCAT(TRIM(up.first_name), " ", TRIM(up.last_name)), " "), u.username, u.email) as staff_name'),
            'up.department',
            DB::raw('COUNT(*) as pending_count'),
            DB::raw('MAX(TIMESTAMPDIFF(SECOND, wsp.started_at, NOW())) as oldest_pending_seconds')
        )
            ->groupBy('ws.assigned_account_id', 'u.username', 'u.email', 'up.first_name', 'up.last_name', 'up.department')
            ->orderByDesc('oldest_pending_seconds')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'actor_id' => $row->actor_id,
                    'staff_name' => $row->staff_name,
                    'department' => $row->department ?? 'N/A',
                    'pending_count' => (int) $row->pending_count,
                    'oldest_pending_seconds' => (int) $row->oldest_pending_seconds,
                ];
            })->toArray();
    }

    private function buildBaseQuery(array $filters): Builder
    {
        $query = WorkflowStepProgress::query()->from('tbl_workflow_step_progress as wsp')
            ->join('tbl_user as u', 'u.account_id', '=', 'wsp.actor_id')
            ->leftJoin('tbl_userprofile as up', 'up.account_id', '=', 'u.account_id');

        if (! empty($filters['date_from'])) {
            $query->whereDate('wsp.acted_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('wsp.acted_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['form_id'])) {
            $query->where('wsp.form_id', $filters['form_id']);
        }

        return $query;
    }
}
