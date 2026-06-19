<?php

namespace App\Modules\Reports\Services;

use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReportSummaryService
{
    /**
     * Compute aggregate counts and average completion for the current filter set.
     * Results are cached for 60 seconds keyed by form_id and the serialized
     * filter state, giving a significant reduction in aggregate query load
     * on paginated report views with the same filters.
     *
     * @param  Builder<FormSubmission>  $filteredQuery
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildSummary(Builder $filteredQuery, array $filters = []): array
    {
        $formId = (int) ($filters['form_id'] ?? 0);

        // Build a deterministic cache key from the subset of filters that affect
        // aggregates (exclude pagination: per_page / page).
        $summaryFilters = array_diff_key($filters, array_flip(['per_page', 'page']));
        ksort($summaryFilters);
        $cacheKey = sprintf(
            'reports_summary_%d_%s',
            $formId,
            md5(json_encode($summaryFilters))
        );

        /** @var array<string, mixed> $cached */
        $cached = Cache::remember($cacheKey, 60, function () use ($filteredQuery): array {
            $totalSubmissions = (clone $filteredQuery)->count();

            $statusCountsQuery = DB::query()->fromSub(
                (clone $filteredQuery)->select('submission_status'),
                'filtered_submissions'
            );

            $statusCountsRaw = $statusCountsQuery
                ->selectRaw('LOWER(submission_status) as status_key, COUNT(*) as total')
                ->groupByRaw('LOWER(submission_status)')
                ->pluck('total', 'status_key');

            $statusCounts = [
                'approved' => (int) ($statusCountsRaw['approved'] ?? 0),
                'rejected' => (int) ($statusCountsRaw['rejected'] ?? 0),
                'pending' => (int) ($statusCountsRaw['pending'] ?? 0),
                'completed' => (int) ($statusCountsRaw['completed'] ?? 0),
            ];

            $averageCompletionSeconds = $this->resolveAverageCompletionSeconds($filteredQuery);

            return [
                'total_submissions' => $totalSubmissions,
                'status_counts' => $statusCounts,
                'average_completion_seconds' => $averageCompletionSeconds,
                'average_completion_human' => $this->formatDurationHuman($averageCompletionSeconds),
            ];
        });

        return $cached;
    }

    /**
     * @param  Builder<FormSubmission>  $filteredQuery
     */
    private function resolveAverageCompletionSeconds(Builder $filteredQuery): ?float
    {
        $submissionIdQuery = (clone $filteredQuery)->select('id');

        $strictCompletion = WorkflowStepProgress::query()
            ->from('tbl_workflow_step_progress as wsp')
            ->selectRaw('wsp.submission_id, MAX(wsp.acted_at) as last_acted_at')
            ->whereNotNull('wsp.acted_at')
            ->whereIn('wsp.submission_id', $submissionIdQuery)
            ->where(function ($query): void {
                $query
                    ->whereRaw("LOWER(COALESCE(wsp.status, '')) = ?", ['completed'])
                    ->orWhereRaw("LOWER(COALESCE(wsp.action_taken, '')) = ?", ['completed']);
            })
            ->groupBy('wsp.submission_id');

        $strictAverage = $this->averageCompletionSecondsFromSubQuery($strictCompletion);

        if ($strictAverage !== null) {
            return (float) $strictAverage;
        }

        $fallbackCompletion = WorkflowStepProgress::query()
            ->from('tbl_workflow_step_progress as wsp')
            ->selectRaw('wsp.submission_id, MAX(wsp.acted_at) as last_acted_at')
            ->whereNotNull('wsp.acted_at')
            ->whereIn('wsp.submission_id', (clone $filteredQuery)->select('id'))
            ->whereRaw("LOWER(COALESCE(wsp.status, '')) IN (?, ?, ?)", ['approved', 'rejected', 'skipped'])
            ->groupBy('wsp.submission_id');

        $fallbackAverage = $this->averageCompletionSecondsFromSubQuery($fallbackCompletion);

        return $fallbackAverage === null ? null : (float) $fallbackAverage;
    }

    private function averageCompletionSecondsFromSubQuery(Builder $completionSubQuery): ?float
    {
        $query = DB::query()
            ->fromSub($completionSubQuery, 'completion')
            ->join('tbl_form_submission as fs', 'fs.id', '=', 'completion.submission_id')
            ->whereNotNull('fs.submitted_at');

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $value = $query
                ->selectRaw("AVG((strftime('%s', completion.last_acted_at) - strftime('%s', fs.submitted_at))) as avg_seconds")
                ->value('avg_seconds');

            return $value === null ? null : (float) $value;
        }

        $value = $query
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, fs.submitted_at, completion.last_acted_at)) as avg_seconds')
            ->value('avg_seconds');

        return $value === null ? null : (float) $value;
    }

    private function formatDurationHuman(?float $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $seconds = (int) round($seconds);
        if ($seconds <= 0) {
            return '0h';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        if ($days > 0) {
            return sprintf('%dd %dh', $days, $hours);
        }

        $minutes = intdiv($seconds % 3600, 60);
        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        return sprintf('%dm', max($minutes, 1));
    }
}
