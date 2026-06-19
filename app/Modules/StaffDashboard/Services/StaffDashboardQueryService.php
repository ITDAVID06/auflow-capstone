<?php

namespace App\Modules\StaffDashboard\Services;

use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StaffDashboardQueryService
{
    public function __construct(protected StaffStepReadinessService $readinessService) {}

    public function getMetricsForStaff(int $staffId): array
    {
        return Cache::remember(
            "auflow:dashboard:metrics:staff:{$staffId}",
            now()->addMinutes(5),
            function () use ($staffId) {
                $row = WorkflowStepProgress::whereHas('step', function ($q) use ($staffId) {
                    $q->where('assigned_account_id', $staffId)
                        ->orWhereHas('approvers', fn ($aq) => $aq->where('account_id', $staffId));
                })
                    ->selectRaw(
                        "COUNT(*) as total,
                         SUM(CASE WHEN status IN ('Pending', 'Waiting') THEN 1 ELSE 0 END) as pending,
                         SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
                         SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected"
                    )
                    ->first();

                return [
                    'total' => (int) ($row->total ?? 0),
                    'pending' => (int) ($row->pending ?? 0),
                    'approved' => (int) ($row->approved ?? 0),
                    'rejected' => (int) ($row->rejected ?? 0),
                ];
            }
        );
    }

    public function getPendingRequestsForStaff(int $staffId, ?string $search = null): array
    {
        $progresses = WorkflowStepProgress::with([
            'step.workflow.form',
            'canonicalSubmission.parentRevision',
            'canonicalSubmission.submitter.profile',
        ])
            ->whereHas('step', function ($q) use ($staffId) {
                $q->where('assigned_account_id', $staffId)
                    ->orWhereHas('approvers', fn ($aq) => $aq->where('account_id', $staffId));
            })
            ->whereIn('status', ['Pending', 'Waiting'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->whereHas('step.workflow.form', function ($formQuery) use ($search) {
                        $formQuery->where('form_code', 'like', "%{$search}%")
                            ->orWhere('form_name', 'like', "%{$search}%");
                    })
                        ->orWhereHas('step.workflow', function ($workflowQuery) use ($search) {
                            $workflowQuery->where('workflow_name', 'like', "%{$search}%");
                        });
                });
            })
            ->get();

        $stats = $this->buildProgressStats($progresses);

        $requests = [];
        foreach ($progresses as $progress) {
            $step = $progress->step;
            $workflow = $step->workflow ?? null;
            $form = $workflow?->form;

            if (! $form || ! $this->readinessService->isStepReady($step, $progress->submission_id)) {
                continue;
            }

            $canonicalSubmission = $progress->canonicalSubmission;
            if (! $canonicalSubmission) {
                continue;
            }

            $totalSteps = (int) ($stats['total_steps_by_workflow'][(int) $workflow->id] ?? 0);

            $requests[] = [
                'progress_id' => $progress->id,
                'submission_id' => (int) $canonicalSubmission->id,
                'form_code' => $form->form_code ?? '-',
                'form_name' => $form->form_name,
                'status' => $progress->status,
                'progress' => $this->calculateProgressPercent(
                    submissionId: (int) $progress->submission_id,
                    workflowId: (int) $workflow->id,
                    totalStepsByWorkflow: $stats['total_steps_by_workflow'],
                    completedByWorkflowSubmission: $stats['done_by_key'],
                ),
                'submitted_at' => optional($canonicalSubmission->submitted_at ?? $canonicalSubmission->created_at)->toDateTimeString() ?? now()->toDateTimeString(),
                'submitter' => $this->resolveSubmitterName($canonicalSubmission),
                'revision_of' => $canonicalSubmission->parentRevision?->id,
                'root_submission_id' => (int) ($canonicalSubmission->root_submission_id ?? $canonicalSubmission->id),
            ];
        }

        return collect($requests)
            ->groupBy(fn ($row) => $row['root_submission_id'])
            ->map(fn ($group) => $group->sortByDesc('submission_id')->first())
            ->values()
            ->sortByDesc(function ($row) {
                $timestamp = $row['submitted_at'] ?? null;
                if ($timestamp instanceof \DateTimeInterface) {
                    return $timestamp->getTimestamp();
                }

                return is_string($timestamp) ? strtotime($timestamp) ?: 0 : 0;
            })
            ->map(function (array $row): array {
                unset($row['root_submission_id']);

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return array{assigned_count:int, pending_pool_count:int, has_unassigned_pending:bool}
     */
    public function getPendingContextForStaff(int $staffId): array
    {
        $assignedCount = count($this->getPendingRequestsForStaff($staffId));

        $pendingPoolCount = WorkflowStepProgress::query()
            ->whereIn('status', ['Pending', 'Waiting'])
            ->count();

        return [
            'assigned_count' => $assignedCount,
            'pending_pool_count' => $pendingPoolCount,
            'has_unassigned_pending' => $assignedCount === 0 && $pendingPoolCount > 0,
        ];
    }

    public function getAllRequestsForStaff(
        int $staffId,
        ?string $status = null,
        ?string $q = null,
        int $perPage = 15
    ): array {
        $latestIds = WorkflowStepProgress::query()
            ->select(DB::raw('MAX(tbl_workflow_step_progress.id) as id'))
            ->join('tbl_workflow_step as ws', 'ws.id', '=', 'tbl_workflow_step_progress.step_id')
            ->leftJoin('tbl_workflow_step_approvers as wsa', 'wsa.step_id', '=', 'ws.id')
            ->where(function ($query) use ($staffId) {
                $query->where('ws.assigned_account_id', $staffId)
                    ->orWhere('wsa.account_id', $staffId);
            })
            ->when($status, fn ($query) => $query->where('tbl_workflow_step_progress.status', $status))
            ->groupBy('tbl_workflow_step_progress.form_id', 'tbl_workflow_step_progress.submission_id')
            ->pluck('id')
            ->all();

        if (empty($latestIds)) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
            ];
        }

        $progresses = WorkflowStepProgress::with(['step.workflow.form'])
            ->whereIn('id', $latestIds)
            ->orderByDesc('id')
            ->get();

        $stats = $this->buildProgressStats($progresses);

        $rows = [];
        $progresses->loadMissing([
            'canonicalSubmission.parentRevision',
            'canonicalSubmission.submitter.profile',
        ]);

        foreach ($progresses as $progress) {
            $workflow = $progress->step?->workflow;
            $form = $workflow?->form;
            if (! $form || ! $workflow) {
                continue;
            }

            $canonicalSubmission = $progress->canonicalSubmission;
            if (! $canonicalSubmission) {
                continue;
            }

            $rows[] = [
                'progress_id' => $progress->id,
                'submission_id' => (int) $canonicalSubmission->id,
                'form_code' => $form->form_code ?? '-',
                'form_name' => $form->form_name,
                'status' => $progress->status,
                'progress' => $this->calculateProgressPercent(
                    submissionId: (int) $progress->submission_id,
                    workflowId: (int) $workflow->id,
                    totalStepsByWorkflow: $stats['total_steps_by_workflow'],
                    completedByWorkflowSubmission: $stats['done_by_key'],
                ),
                'submitted_at' => optional($canonicalSubmission->submitted_at ?? $canonicalSubmission->created_at)->toDateTimeString() ?? now()->toDateTimeString(),
                'submitter' => $this->resolveSubmitterName($canonicalSubmission),
                'revision_of' => $canonicalSubmission->parentRevision?->id,
                'root_submission_id' => (int) ($canonicalSubmission->root_submission_id ?? $canonicalSubmission->id),
            ];
        }

        if ($q) {
            $qLower = mb_strtolower($q);
            $rows = array_values(array_filter($rows, function ($row) use ($qLower) {
                $haystack = mb_strtolower(($row['form_code'].' '.$row['form_name'].' '.$row['submitter']));

                return str_contains($haystack, $qLower);
            }));
        }

        $collapsed = collect($rows)
            ->groupBy(fn ($row) => $row['root_submission_id'])
            ->map(function (Collection $group) {
                return $group->sortByDesc(function ($row) {
                    return [$row['submitted_at'], $row['progress_id']];
                })->first();
            })
            ->values()
            ->map(function (array $row): array {
                unset($row['root_submission_id']);

                return $row;
            })
            ->sortByDesc(fn ($row) => $row['submitted_at'])
            ->values();

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $total = $collapsed->count();
        $items = $collapsed->slice(($currentPage - 1) * $perPage, $perPage)->values()->all();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @param  Collection<int, WorkflowStepProgress>  $progresses
     * @return array{total_steps_by_workflow: array<int, int>, done_by_key: array<string, int>}
     */
    private function buildProgressStats(Collection $progresses): array
    {
        $workflowIds = $progresses
            ->pluck('workflow_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $submissionIds = $progresses
            ->pluck('submission_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($workflowIds === [] || $submissionIds === []) {
            return [
                'total_steps_by_workflow' => [],
                'done_by_key' => [],
            ];
        }

        $totalStepsByWorkflow = WorkflowStep::query()
            ->whereIn('workflow_id', $workflowIds)
            ->selectRaw('workflow_id, COUNT(*) as total')
            ->groupBy('workflow_id')
            ->pluck('total', 'workflow_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        $doneByKey = WorkflowStepProgress::query()
            ->whereIn('submission_id', $submissionIds)
            ->whereIn('workflow_id', $workflowIds)
            ->whereIn('status', ['Approved', 'Rejected', 'Skipped'])
            ->selectRaw('workflow_id, submission_id, COUNT(*) as total')
            ->groupBy('workflow_id', 'submission_id')
            ->get()
            ->mapWithKeys(fn (WorkflowStepProgress $progress) => [
                $this->progressKey((int) $progress->workflow_id, (int) $progress->submission_id) => (int) ($progress->total ?? 0),
            ])
            ->all();

        return [
            'total_steps_by_workflow' => $totalStepsByWorkflow,
            'done_by_key' => $doneByKey,
        ];
    }

    /**
     * @param  array<int, int>  $totalStepsByWorkflow
     * @param  array<string, int>  $completedByWorkflowSubmission
     */
    private function calculateProgressPercent(
        int $submissionId,
        int $workflowId,
        array $totalStepsByWorkflow,
        array $completedByWorkflowSubmission,
    ): int {
        $total = (int) ($totalStepsByWorkflow[$workflowId] ?? 0);

        if ($total <= 0) {
            return 0;
        }

        $done = (int) ($completedByWorkflowSubmission[$this->progressKey($workflowId, $submissionId)] ?? 0);

        return (int) round(($done / $total) * 100);
    }

    private function progressKey(int $workflowId, int $submissionId): string
    {
        return $workflowId.':'.$submissionId;
    }

    private function resolveSubmitterName(FormSubmission $submission): string
    {
        $submitter = $submission->submitter;
        $name = trim((string) ($submitter?->profile?->first_name ?? '').' '.(string) ($submitter?->profile?->last_name ?? ''));

        return $name !== '' ? $name : ($submitter?->username ?? 'Unknown');
    }
}
