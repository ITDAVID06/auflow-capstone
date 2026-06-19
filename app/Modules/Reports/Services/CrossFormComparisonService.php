<?php

namespace App\Modules\Reports\Services;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CrossFormComparisonService
{
    public const SUPPORTED_METRICS = [
        'submission_count',
        'avg_completion_time_seconds',
        'approval_rate',
    ];

    public function __construct(
        private readonly SubmissionQueryService $submissionQueryService,
    ) {}

    /**
     * Compare multiple forms on a single metric.
     *
     * @param  array<int>  $formIds
     * @param  string  $metric  One of SUPPORTED_METRICS
     * @param  array{date_from?: string|null, date_to?: string|null}|null  $dateRange
     * @return array<int, array{form_id: int, form_name: string, value: float|null}>
     */
    public function compare(
        array $formIds,
        string $metric = 'submission_count',
        ?array $dateRange = null,
    ): array {
        $forms = Form::whereIn('id', $formIds)
            ->where('status', 'Active')
            ->orderBy('form_name')
            ->get(['id', 'form_name']);

        $results = [];

        foreach ($forms as $form) {
            $filters = ['form_id' => $form->id];

            if (! empty($dateRange['date_from'])) {
                $filters['date_from'] = $dateRange['date_from'];
            }
            if (! empty($dateRange['date_to'])) {
                $filters['date_to'] = $dateRange['date_to'];
            }

            // formFieldTypes not required here — no builder filter clauses
            $baseQuery = $this->submissionQueryService->buildFilteredSubmissionQuery(
                $form->id,
                $filters,
                [],
            );

            $value = match ($metric) {
                'submission_count' => (float) (clone $baseQuery)->count(),
                'avg_completion_time_seconds' => $this->resolveAvgCompletionSeconds(clone $baseQuery),
                'approval_rate' => $this->resolveApprovalRate(clone $baseQuery),
                default => null,
            };

            $results[] = [
                'form_id' => $form->id,
                'form_name' => $form->form_name,
                'value' => $value,
            ];
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private metric helpers
    // -------------------------------------------------------------------------

    /**
     * @param  Builder<\App\Modules\FormBuilder\Models\FormSubmission>  $baseQuery
     */
    private function resolveAvgCompletionSeconds(Builder $baseQuery): ?float
    {
        $submissionIdSubQuery = (clone $baseQuery)->select('id');

        $completionSubQuery = WorkflowStepProgress::query()
            ->from('tbl_workflow_step_progress as wsp')
            ->selectRaw('wsp.submission_id, MAX(wsp.acted_at) as last_acted_at')
            ->whereNotNull('wsp.acted_at')
            ->whereIn('wsp.submission_id', $submissionIdSubQuery)
            ->where(function ($q): void {
                $q->whereRaw("LOWER(COALESCE(wsp.status, '')) IN (?, ?, ?, ?)", ['completed', 'approved', 'rejected', 'skipped'])
                    ->orWhereRaw("LOWER(COALESCE(wsp.action_taken, '')) = ?", ['completed']);
            })
            ->groupBy('wsp.submission_id');

        $joinQuery = DB::query()
            ->fromSub($completionSubQuery, 'completion')
            ->join('tbl_form_submission as fs', 'fs.id', '=', 'completion.submission_id')
            ->whereNotNull('fs.submitted_at');

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $value = $joinQuery
                ->selectRaw("AVG((strftime('%s', completion.last_acted_at) - strftime('%s', fs.submitted_at))) as avg_seconds")
                ->value('avg_seconds');
        } else {
            $value = $joinQuery
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, fs.submitted_at, completion.last_acted_at)) as avg_seconds')
                ->value('avg_seconds');
        }

        return $value === null ? null : round((float) $value, 1);
    }

    /**
     * @param  Builder<\App\Modules\FormBuilder\Models\FormSubmission>  $baseQuery
     */
    private function resolveApprovalRate(Builder $baseQuery): ?float
    {
        $total = (clone $baseQuery)->count();

        if ($total === 0) {
            return null;
        }

        $approved = (clone $baseQuery)
            ->whereRaw("LOWER(COALESCE(submission_status, '')) = ?", ['approved'])
            ->count();

        return round($approved / $total * 100, 1);
    }
}
