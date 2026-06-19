<?php

namespace App\Modules\AdminSubmissions\Services;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\Slot;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\VerificationSnapshot\Services\SnapshotService;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Services\NotificationService;
use Carbon\CarbonInterval;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminSubmissionsQueryService
{
    public function __construct(
        protected SubmissionFieldValueNormalizer $fieldValueNormalizer,
        protected SnapshotService $snapshot,
        protected ?NotificationService $notifier = null // optional, we guard calls
    ) {}

    /** ---------- List: system-wide READY pending, ordered ---------- */
    public function getAllPendingSystemWide(?string $q = null): array
    {
        return $this->getSystemSubmissions('Pending', $q);
    }

    /** ---------- Detail view for admins (no assignee gate) ---------- */
    public function getSubmissionDetailsForAdmin(int $progressId): array
    {
        $progress = WorkflowStepProgress::with('step.workflow.form')->findOrFail($progressId);
        $form = $progress->step->workflow->form()->with('fields')->first();
        $submission = $this->resolveCanonicalSubmissionFromProgress(
            $progress,
            ['attachments', 'slots.facility', 'submitter.profile', 'parentRevision']
        );

        if (! $submission) {
            abort(404, 'Submission not found.');
        }

        return $this->buildAdminSubmissionPayload($submission, $form, $progressId, $progress->workflow_id);
    }

    /** ---------------- Submission Details for Admin Viewer ---------------- */
    public function getAdminSubmissionDetails(int $formId, int $submissionId): ?array
    {
        $form = Form::with('fields')->find($formId);
        if (! $form) {
            return null;
        }

        $submission = $this->findCanonicalSubmission($formId, $submissionId, [
            'attachments',
            'slots.facility',
            'submitter.profile',
            'parentRevision',
        ]);
        if (! $submission) {
            return null;
        }

        return $this->buildAdminSubmissionPayload(
            $submission,
            $form,
            $this->resolveCurrentProgressId($submission, $form, $submissionId)
        );
    }

    /** ---------- Override acting (approve/reject) ---------- */
    public function adminOverride(
        int $progressId,
        int $adminId,
        string $status,
        ?string $comment = null,
        bool $forceAssignment = true,
        bool $forceReadiness = false
    ): void {
        $progress = DB::transaction(function () use ($progressId, $status, $adminId, $comment, $forceAssignment, $forceReadiness) {
            /** @var WorkflowStepProgress $progress */
            $progress = WorkflowStepProgress::with(['step.workflow', 'version'])
                ->lockForUpdate()
                ->findOrFail($progressId);

            if ($progress->status !== 'Pending') {
                throw new \RuntimeException('Already processed');
            }

            if (! $forceAssignment && (int) $progress->step->assigned_account_id !== $adminId) {
                throw new \RuntimeException('Unauthorized: not the assigned approver');
            }

            if (! $forceReadiness && $status === 'Approved' && ! $this->isStepReady($progress->step, $progress->submission_id)) {
                throw new \RuntimeException('Previous steps not yet approved');
            }

            $progress->update([
                'status' => $status,
                'action_taken' => $status === 'Approved' ? 'Override-Approve' : 'Override-Reject',
                'acted_at' => now(),
                'completed_at' => now(),
                'actor_id' => $adminId,
                'comments' => trim(($progress->comments ? $progress->comments."\n" : '').('[OVERRIDE] '.($comment ?? ''))),
                'duration_seconds' => $progress->started_at ? now()->diffInSeconds($progress->started_at) : null,
            ]);

            // Rejection cascades: parallel peers + downstream
            if ($status === 'Rejected') {
                $step = $progress->step;
                $stepsSnapshot = $progress->version?->steps_snapshot ?? [];

                if (! empty($stepsSnapshot)) {
                    $rejectedStepArr = collect($stepsSnapshot)->firstWhere('id', $step->id);
                    $rejectedGroup = (int) ($rejectedStepArr['step_group'] ?? 0);

                    if ($rejectedGroup > 0) {
                        $peerIds = collect($stepsSnapshot)
                            ->filter(fn ($s) => (int) ($s['step_group'] ?? 0) === $rejectedGroup && (int) $s['id'] !== (int) $step->id)
                            ->pluck('id');

                        if ($peerIds->isNotEmpty()) {
                            WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                                ->where('submission_id', $progress->submission_id)
                                ->whereIn('step_id', $peerIds)
                                ->where('status', 'Pending')
                                ->update([
                                    'status' => 'Rejected',
                                    'action_taken' => 'Auto-Rejected (Peer)',
                                    'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, started_at, NOW())'),
                                    'acted_at' => now(),
                                    'updated_at' => now(),
                                ]);
                        }
                    }

                    $downstream = $this->getDownstreamStepIds($step, $stepsSnapshot);
                    if (! empty($downstream)) {
                        WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                            ->where('submission_id', $progress->submission_id)
                            ->whereIn('step_id', $downstream)
                            ->whereIn('status', ['Pending', 'Waiting'])
                            ->update([
                                'status' => 'Rejected',
                                'action_taken' => 'Auto-Rejected',
                                'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, started_at, NOW())'),
                                'acted_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }
                } else {
                    // Legacy fallback: live step table
                    $workflow = $step->workflow;

                    if (! empty($step->step_group)) {
                        WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                            ->where('submission_id', $progress->submission_id)
                            ->where('step_id', '!=', $step->id)
                            ->whereHas('step', fn ($q) => $q->where('step_group', (int) $step->step_group))
                            ->where('status', 'Pending')
                            ->update([
                                'status' => 'Rejected',
                                'action_taken' => 'Auto-Rejected (Peer)',
                                'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, started_at, NOW())'),
                                'acted_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }

                    $downstream = $this->getDownstreamStepIds($step, []);
                    if (! empty($downstream)) {
                        WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                            ->where('submission_id', $progress->submission_id)
                            ->whereIn('step_id', $downstream)
                            ->where('status', 'Pending')
                            ->update([
                                'status' => 'Rejected',
                                'action_taken' => 'Auto-Rejected',
                                'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, started_at, NOW())'),
                                'acted_at' => now(),
                                'updated_at' => now(),
                            ]);
                    } else {
                        WorkflowStepProgress::query()->from('tbl_workflow_step_progress as wsp')
                            ->join('tbl_workflow_step as ws', 'ws.id', '=', 'wsp.step_id')
                            ->where('wsp.workflow_id', $progress->workflow_id)
                            ->where('wsp.submission_id', $progress->submission_id)
                            ->where('wsp.status', 'Pending')
                            ->where('ws.step_order', '>', (int) $step->step_order)
                            ->update([
                                'wsp.status' => 'Rejected',
                                'wsp.action_taken' => 'Auto-Rejected',
                                'wsp.duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, wsp.started_at, NOW())'),
                                'wsp.acted_at' => now(),
                                'wsp.updated_at' => now(),
                            ]);
                    }
                }
            }

            return $progress->fresh(['step.workflow', 'form', 'actor.profile']);
        });

        // On approve: advance sequential (notifications handled within advanceWorkflowIfNeeded)
        if ($progress->status === 'Approved') {
            try {
                $workflow = $progress->step->workflow;
                $this->advanceWorkflowIfNeeded($workflow, (int) $progress->submission_id, (int) $progress->step->step_group);

                // Check if workflow is complete and send notification
                $allSteps = WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                    ->where('submission_id', $progress->submission_id)->count();

                $doneSteps = WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                    ->where('submission_id', $progress->submission_id)
                    ->whereIn('status', ['Approved', 'Skipped'])->count();

                if ($allSteps > 0 && $doneSteps === $allSteps) {
                    if ($this->notifier) {
                        $this->notifier->notifySubmissionCompletion($progress->step->workflow, (int) $progress->submission_id, 'approved');
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('[AdminOverride] advance/notify failed', [
                    'progress_id' => $progress->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // On reject: send rejection notification immediately
        if ($progress->status === 'Rejected') {
            try {
                if ($this->notifier) {
                    $this->notifier->notifySubmissionCompletion($progress->step->workflow, (int) $progress->submission_id, 'rejected');
                }
            } catch (\Throwable $e) {
                \Log::warning('[AdminOverride] rejection notification failed', [
                    'progress_id' => $progress->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Snapshot best-effort
        try {
            // Generate snapshot after response (non-blocking, worker-independent)
            \App\Modules\VerificationSnapshot\Jobs\GenerateVerificationSnapshot::dispatchAfterResponse($progress->id);
            \Log::info('[Snapshot] Scheduled snapshot generation', ['progress_id' => $progress->id]);
        } catch (\Throwable $e) {
            \Log::warning('[Snapshot] Failed (override)', ['progress_id' => $progress->id, 'error' => $e->getMessage()]);
        }
    }

    /** ---------- System metrics ---------- */
    public function getSystemMetrics(): array
    {
        $base = WorkflowStepProgress::query();

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'Pending')->count(),
            'approved' => (clone $base)->where('status', 'Approved')->count(),
            'rejected' => (clone $base)->where('status', 'Rejected')->count(),
        ];
    }

    /** ---------- Helpers (same readiness rules as staff) ---------- */
    private function progressPercent(int $submissionId, int $workflowId): int
    {
        $total = WorkflowStep::where('workflow_id', $workflowId)->count();
        if ($total === 0) {
            return 0;
        }

        $done = WorkflowStepProgress::where('submission_id', $submissionId)
            ->where('workflow_id', $workflowId)
            ->whereIn('status', ['Approved', 'Rejected'])
            ->count();

        return (int) round(($done / $total) * 100);
    }

    /**
     * Batch load workflow step previews for multiple submissions
     * Eliminates N+1 query by loading all workflow steps in one query per workflow
     *
     * @param  array  $progressData  Array of ['progress' => WorkflowStepProgress, 'workflow_id' => int, 'submission_id' => int]
     * @return array Keyed by submission_id => ['steps' => [...], 'count' => int, 'completed' => int]
     */
    private function batchLoadWorkflowPreviews(array $progressData): array
    {
        // Group submission IDs by workflow_id
        $submissionsByWorkflow = [];
        foreach ($progressData as $item) {
            $workflowId = $item['workflow_id'];
            $submissionId = $item['submission_id'];
            $submissionsByWorkflow[$workflowId] ??= [];
            $submissionsByWorkflow[$workflowId][] = $submissionId;
        }

        // Batch load all workflow steps for all submissions, grouped by workflow
        $allWorkflowSteps = [];
        foreach ($submissionsByWorkflow as $workflowId => $submissionIds) {
            $steps = WorkflowStepProgress::with(['step', 'actor.profile'])
                ->whereIn('tbl_workflow_step_progress.submission_id', $submissionIds)
                ->where('tbl_workflow_step_progress.workflow_id', $workflowId)
                ->join('tbl_workflow_step as ws', 'ws.id', '=', 'tbl_workflow_step_progress.step_id')
                ->orderBy('ws.step_group')
                ->orderBy('ws.step_order')
                ->select('tbl_workflow_step_progress.*')
                ->get()
                ->groupBy('submission_id');

            foreach ($steps as $submissionId => $stepList) {
                $mapped = $stepList->map(fn ($sp) => [
                    'id' => $sp->id,
                    'name' => $sp->step?->step_name ?? '—',
                    'status' => $sp->status,
                    'actor' => $sp->actor?->profile?->first_name.' '.$sp->actor?->profile?->last_name,
                    'acted_at' => optional($sp->acted_at)?->toDateTimeString(),
                    'step_group' => $sp->step?->step_group,
                    'step_order' => $sp->step?->step_order,
                ]);

                $allWorkflowSteps[$submissionId] = [
                    'steps' => $mapped,
                    'count' => $mapped->count(),
                    'completed' => $mapped->whereIn('status', ['Approved', 'Rejected', 'Skipped'])->count(),
                ];
            }
        }

        return $allWorkflowSteps;
    }

    private function isStepReady(WorkflowStep $step, int $submissionId): bool
    {
        $workflow = $step->workflow;

        // Parallel groups: prior groups must be completed
        if (! empty($step->step_group) && (int) $step->step_group > 0) {
            $priorGroupSteps = WorkflowStep::where('workflow_id', $workflow->id)
                ->where('step_group', '<', $step->step_group)
                ->pluck('id');

            if ($priorGroupSteps->isEmpty()) {
                return true;
            }

            $statuses = WorkflowStepProgress::whereIn('step_id', $priorGroupSteps)
                ->where('submission_id', $submissionId)
                ->pluck('status');

            return $statuses->every(fn ($s) => in_array($s, ['Approved', 'Skipped'], true));
        }

        // Sequential fallback
        return $this->isSequentiallyReady($step, $submissionId);
    }

    private function isSequentiallyReady(WorkflowStep $step, int $submissionId): bool
    {
        $priorSteps = WorkflowStep::where('workflow_id', $step->workflow_id)
            ->where('step_order', '<', $step->step_order)
            ->pluck('id');

        if ($priorSteps->isEmpty()) {
            return true;
        }

        $statuses = WorkflowStepProgress::whereIn('step_id', $priorSteps)
            ->where('submission_id', $submissionId)
            ->pluck('status');

        return $statuses->every(fn ($s) => in_array($s, ['Approved', 'Skipped'], true));
    }

    /** Advance sequential workflows: unlock next group (Waiting → Pending) */
    protected function advanceWorkflowIfNeeded(Workflow $workflow, int $submissionId, int $currentGroup): void
    {
        $group = (int) $currentGroup;

        while (true) {
            $statuses = WorkflowStepProgress::query()
                ->where('workflow_id', $workflow->id)
                ->where('submission_id', $submissionId)
                ->whereHas('step', fn ($q) => $q->where('step_group', $group))
                ->pluck('status');

            if ($statuses->isEmpty()) {
                break;
            }

            $hasActive = $statuses->contains(fn ($status) => in_array($status, ['Pending', 'Waiting'], true));
            if ($hasActive) {
                break;
            }

            $nextGroup = $group + 1;
            $nextGroupStatuses = WorkflowStepProgress::query()
                ->where('workflow_id', $workflow->id)
                ->where('submission_id', $submissionId)
                ->whereHas('step', fn ($q) => $q->where('step_group', $nextGroup))
                ->pluck('status');

            if ($nextGroupStatuses->isEmpty()) {
                break;
            }

            $unlockedCount = WorkflowStepProgress::query()
                ->where('workflow_id', $workflow->id)
                ->where('submission_id', $submissionId)
                ->where('status', 'Waiting')
                ->whereHas('step', fn ($q) => $q->where('step_group', $nextGroup))
                ->update([
                    'status' => 'Pending',
                    'started_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($unlockedCount > 0 && $this->notifier) {
                try {
                    $form = $workflow->form()->first();
                    if ($form) {
                        $this->notifier->notifyNextSequentialApproversByGroup(
                            workflow: $workflow,
                            submissionId: (int) $submissionId,
                            currentGroup: $group,
                            form: $form
                        );
                    }
                } catch (\Throwable $e) {
                    \Log::warning('[Notify] Failed to notify next approvers in AdminSubmissionsService', [
                        'workflow_id' => $workflow->id,
                        'submissionId' => $submissionId,
                        'step_group' => $nextGroup,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $group = $nextGroup;
        }
    }

    /**
     * Return IDs of all steps downstream of $fromStep using the frozen snapshot.
     *
     * If step_group > 0: downstream = steps in groups AFTER fromStep's group.
     * Otherwise (sequential): downstream = steps with higher step_order.
     * Returns [] when no snapshot supplied (caller handles legacy path).
     */
    private function getDownstreamStepIds(WorkflowStep $fromStep, array $stepsSnapshot): array
    {
        if (empty($stepsSnapshot)) {
            return [];
        }

        $snapshot = collect($stepsSnapshot);
        $fromArr = $snapshot->firstWhere('id', $fromStep->id);

        if ($fromArr === null) {
            return [];
        }

        $fromGroup = (int) ($fromArr['step_group'] ?? 0);
        $fromOrder = (int) ($fromArr['step_order'] ?? 0);

        if ($fromGroup > 0) {
            return $snapshot
                ->filter(fn ($s) => (int) ($s['step_group'] ?? 0) > $fromGroup)
                ->pluck('id')
                ->values()
                ->all();
        }

        // Sequential: downstream = higher step_order
        return $snapshot
            ->filter(fn ($s) => (int) ($s['step_order'] ?? 0) > $fromOrder)
            ->pluck('id')
            ->values()
            ->all();
    }

    public function getSystemSubmissionsPaginated(?string $statusFilter = null, ?string $q = null, int $perPage = 12, ?string $sort = null, ?string $direction = null): array
    {
        $statusFilter = $statusFilter ? ucfirst(strtolower($statusFilter)) : null;
        if ($statusFilter === 'All') {
            $statusFilter = null;
        }

        $perPage = max(1, min($perPage, 100));
        $dir = ($direction === 'desc') ? 'desc' : 'asc';

        // Whitelisted sort column map — prevents SQL injection
        $sortableColumns = [
            'form' => 'f.form_name',
            'status' => 'wsp.status',
            'submitted' => 'fs.submitted_at',
            'submitter' => null, // handled separately via CONCAT
        ];

        $appliedSort = ($sort !== null && array_key_exists($sort, $sortableColumns)) ? $sort : null;

        $latestProgressSubquery = WorkflowStepProgress::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('form_id', 'submission_id', 'workflow_id');

        $query = WorkflowStepProgress::query()->from('tbl_workflow_step_progress as wsp')
            ->joinSub($latestProgressSubquery, 'latest', fn ($join) => $join->on('latest.id', '=', 'wsp.id'))
            ->join('tbl_workflow_step as ws', 'ws.id', '=', 'wsp.step_id')
            ->join('tbl_form as f', 'f.id', '=', 'wsp.form_id')
            ->select([
                'wsp.id',
                'wsp.form_id',
                'wsp.submission_id',
                'wsp.workflow_id',
                'wsp.status',
                'wsp.started_at',
                'wsp.acted_at',
                'wsp.updated_at',
                'wsp.created_at',
                'ws.step_group',
                'ws.step_order',
                'ws.step_name',
                'f.form_name',
            ]);

        // Add joins required for sort columns
        if ($appliedSort === 'submitted' || $appliedSort === 'submitter') {
            $query->leftJoin('tbl_form_submission as fs', 'fs.id', '=', 'wsp.submission_id');
        }
        if ($appliedSort === 'submitter') {
            $query->leftJoin('tbl_userprofile as up', 'up.account_id', '=', 'fs.account_id');
        }

        if ($statusFilter) {
            $query->where('wsp.status', $statusFilter);
        }

        if ($q) {
            $qLike = '%'.mb_strtolower($q).'%';
            $query->where(function ($where) use ($qLike): void {
                $where->whereRaw('LOWER(f.form_name) LIKE ?', [$qLike])
                    ->orWhereRaw('LOWER(wsp.status) LIKE ?', [$qLike]);
            });
        }

        if ($appliedSort !== null) {
            if ($appliedSort === 'submitter') {
                $query->orderByRaw("CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,'')) {$dir}");
            } else {
                $query->orderBy($sortableColumns[$appliedSort], $dir);
            }
            $query->orderByDesc('wsp.id');
        } elseif (strcasecmp((string) $statusFilter, 'Pending') === 0) {
            $query->orderBy('ws.step_group')
                ->orderBy('ws.step_order')
                ->orderByDesc('wsp.id');
        } else {
            $query->orderByRaw('COALESCE(wsp.acted_at, wsp.updated_at, wsp.created_at) DESC')
                ->orderByDesc('wsp.id');
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        $baseRows = collect($paginator->items());

        if ($baseRows->isEmpty()) {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ];
        }

        $progresses = WorkflowStepProgress::with([
            'step.workflow.form',
            'canonicalSubmission.submitter.profile',
        ])
            ->whereIn('id', $baseRows->pluck('id'))
            ->get()
            ->keyBy('id');

        $previewMetadata = $baseRows->map(fn ($row): array => [
            'workflow_id' => (int) $row->workflow_id,
            'submission_id' => (int) $row->submission_id,
        ])->all();

        $workflowPreviews = $this->batchLoadWorkflowPreviews($previewMetadata);

        $rows = [];
        foreach ($baseRows as $item) {
            $progress = $progresses->get((int) $item->id);
            $form = $progress?->step?->workflow?->form;
            if (! $progress || ! $form) {
                continue;
            }

            $submission = $this->resolveCanonicalSubmissionFromProgress($progress, ['submitter.profile']);
            if (! $submission) {
                continue;
            }

            if ($item->status === 'Pending') {
                $reference = $item->started_at ?? $item->created_at;
                $elapsedSeconds = $reference ? now()->diffInSeconds($reference) : null;
            } elseif (in_array($item->status, ['Approved', 'Rejected'], true)) {
                if ($progress->duration_seconds !== null) {
                    $elapsedSeconds = $progress->duration_seconds;
                } elseif ($item->acted_at) {
                    $reference = $item->started_at ?? $item->created_at;
                    $elapsedSeconds = $reference
                        ? \Carbon\Carbon::parse($item->acted_at)->diffInSeconds(\Carbon\Carbon::parse($reference))
                        : null;
                } else {
                    $elapsedSeconds = null;
                }
            } else {
                $elapsedSeconds = null;
            }

            $workflowPreview = $workflowPreviews[(int) $item->submission_id] ?? [
                'steps' => collect(),
                'count' => 0,
                'completed' => 0,
            ];

            $rows[] = [
                'id' => (int) $item->id,
                'form_id' => $form->id,
                'form_name' => $form->form_name,
                'status' => $item->status,
                'progress' => $this->progressPercent((int) $item->submission_id, (int) $item->workflow_id),
                'submission_id' => (int) $submission->id,
                'submitted_at' => optional($submission->submitted_at ?? $submission->created_at)->toDateTimeString(),
                'submitter' => $this->resolveSubmitterName($submission),
                'step_group' => (int) ($item->step_group ?? 0),
                'step_order' => (int) ($item->step_order ?? 0),
                'started_at' => optional($item->started_at)->toDateTimeString(),
                'elapsed_seconds' => $elapsedSeconds,
                'elapsed_human' => $elapsedSeconds ? CarbonInterval::seconds($elapsedSeconds)->cascade()->forHumans() : null,
                'workflow_preview' => [
                    'steps' => $workflowPreview['steps'],
                    'current' => $item->step_name ?? '—',
                    'count' => $workflowPreview['count'],
                    'completed' => $workflowPreview['completed'],
                ],
            ];
        }

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Fetch system-wide submissions.
     * - If $statusFilter === 'Pending', only include steps that are READY (respect step_group, step_order, canvas).
     * - If $statusFilter is null or other statuses, include all that match (no readiness gating).
     * - Ordering:
     *   - Pending: by step_group asc, step_order asc
     *   - Others: by updated_at/acted_at desc fallback to id desc
     */
    public function getSystemSubmissions(?string $statusFilter = null, ?string $q = null, ?int $limit = null): array
    {
        $statusFilter = $statusFilter ? ucfirst(strtolower($statusFilter)) : null;
        if ($statusFilter === 'All') {
            $statusFilter = null;
        }

        // ========= DASHBOARD (limit mode) =========
        if ($limit) {
            $latestIds = WorkflowStepProgress::query()
                ->select(DB::raw('MAX(id) as id'))
                ->groupBy('form_id', 'submission_id')
                ->orderByDesc('id')
                ->limit($limit)
                ->pluck('id')
                ->all();

            if (empty($latestIds)) {
                return [];
            }

            // Eager load relationships
            $progresses = WorkflowStepProgress::with(['step.workflow.form', 'canonicalSubmission.submitter.profile'])
                ->whereIn('id', $latestIds)
                ->orderByDesc('created_at')
                ->get();

            // Group by form_id to batch load submission data
            $progressesByForm = [];
            $progressMetadata = []; // For batch loading workflow previews
            foreach ($progresses as $progress) {
                $step = $progress->step;
                $workflow = $step?->workflow;
                $form = $workflow?->form;
                if (! $form) {
                    continue;
                }

                $formId = $form->id;
                $progressesByForm[$formId] ??= ['form' => $form, 'workflow' => $workflow, 'items' => []];
                $progressesByForm[$formId]['items'][] = $progress;

                // Track for workflow preview batch loading
                $progressMetadata[] = [
                    'progress' => $progress,
                    'workflow_id' => $workflow->id,
                    'submission_id' => $progress->submission_id,
                ];
            }

            // Batch load workflow previews (eliminates N+1)
            $workflowPreviews = $this->batchLoadWorkflowPreviews($progressMetadata);

            $rows = [];
            foreach ($progressesByForm as $data) {
                $form = $data['form'];
                $workflow = $data['workflow'];
                $items = $data['items'];

                foreach ($items as $progress) {
                    $step = $progress->step;
                    $submission = $this->resolveCanonicalSubmissionFromProgress($progress, ['submitter.profile']);
                    if (! $submission) {
                        continue;
                    }

                    $workflowPreview = $workflowPreviews[$progress->submission_id] ?? [
                        'steps' => collect(),
                        'count' => 0,
                        'completed' => 0,
                    ];

                    $rows[] = [
                        'id' => $progress->id,
                        'form_id' => $form->id,
                        'form_name' => $form->form_name,
                        'status' => $progress->status,
                        'progress' => $this->progressPercent($progress->submission_id, $workflow->id),
                        'submission_id' => (int) $submission->id,
                        'submitted_at' => optional($submission->submitted_at ?? $submission->created_at)->toDateTimeString(),
                        'submitter' => $this->resolveSubmitterName($submission),
                        'workflow_preview' => [
                            'steps' => $workflowPreview['steps'],
                            'current' => $step?->step_name ?? '—',
                            'count' => $workflowPreview['count'],
                            'completed' => $workflowPreview['completed'],
                        ],
                    ];
                }
            }

            if ($q) {
                $qLower = mb_strtolower($q);
                $rows = array_values(array_filter($rows, function ($r) use ($qLower) {
                    $hay = mb_strtolower(($r['form_name'] ?? '').' '.($r['status'] ?? '').' '.($r['submitter'] ?? ''));

                    return str_contains($hay, $qLower);
                }));
            }

            usort($rows, fn ($a, $b) => strcmp(($b['submitted_at'] ?? ''), ($a['submitted_at'] ?? '')));

            return $rows;
        }

        // ========= FULL MODE (All Submissions page) =========
        // Eager load relationships
        $query = WorkflowStepProgress::with(['step.workflow.form', 'canonicalSubmission.submitter.profile']);
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $progresses = $query->get();

        // Group by form_id to batch load submission data
        $progressesByForm = [];
        $progressMetadata = []; // For batch loading workflow previews
        foreach ($progresses as $progress) {
            $step = $progress->step;
            $workflow = $step?->workflow;
            $form = $workflow?->form;
            if (! $form) {
                continue;
            }

            if (strcasecmp((string) $statusFilter, 'Pending') === 0) {
                if (! $this->isStepReady($step, $progress->submission_id)) {
                    continue;
                }
            }

            $formId = $form->id;
            $progressesByForm[$formId] ??= ['form' => $form, 'workflow' => $workflow, 'items' => []];
            $progressesByForm[$formId]['items'][] = ['progress' => $progress, 'step' => $step];

            // Track for workflow preview batch loading
            $progressMetadata[] = [
                'progress' => $progress,
                'workflow_id' => $workflow->id,
                'submission_id' => $progress->submission_id,
            ];
        }

        // Batch load workflow previews (eliminates N+1)
        $workflowPreviews = $this->batchLoadWorkflowPreviews($progressMetadata);

        $rows = [];
        foreach ($progressesByForm as $data) {
            $form = $data['form'];
            $workflow = $data['workflow'];
            $items = $data['items'];

            foreach ($items as $item) {
                $progress = $item['progress'];
                $step = $item['step'];
                $submission = $this->resolveCanonicalSubmissionFromProgress($progress, ['submitter.profile']);

                if (! $submission) {
                    continue;
                }

                // Calculate elapsed time
                if ($progress->status === 'Pending') {
                    $reference = $progress->started_at ?? $progress->created_at;
                    $elapsedSeconds = $reference ? now()->diffInSeconds($reference) : null;
                } elseif (in_array($progress->status, ['Approved', 'Rejected'])) {
                    if ($progress->duration_seconds !== null) {
                        $elapsedSeconds = $progress->duration_seconds;
                    } elseif ($progress->acted_at) {
                        $reference = $progress->started_at ?? $progress->created_at;
                        $elapsedSeconds = $reference
                            ? $progress->acted_at->diffInSeconds($reference)
                            : null;
                    } else {
                        $elapsedSeconds = null;
                    }
                } else {
                    $elapsedSeconds = null;
                }

                $workflowPreview = $workflowPreviews[$progress->submission_id] ?? [
                    'steps' => collect(),
                    'count' => 0,
                    'completed' => 0,
                ];

                $rows[] = [
                    'id' => $progress->id,
                    'form_id' => $form->id,
                    'form_name' => $form->form_name,
                    'status' => $progress->status,
                    'progress' => $this->progressPercent($progress->submission_id, $workflow->id),
                    'submission_id' => (int) $submission->id,
                    'submitted_at' => optional($submission->submitted_at ?? $submission->created_at)->toDateTimeString(),
                    'submitter' => $this->resolveSubmitterName($submission),
                    'step_group' => (int) ($step->step_group ?? 0),
                    'step_order' => (int) ($step->step_order ?? 0),
                    'started_at' => optional($progress->started_at)?->toDateTimeString(),
                    'elapsed_seconds' => $elapsedSeconds,
                    'elapsed_human' => $elapsedSeconds ? CarbonInterval::seconds($elapsedSeconds)->cascade()->forHumans() : null,
                    'acted_at' => optional($progress->acted_at)->toDateTimeString(),
                    'updated_at' => optional($progress->updated_at)->toDateTimeString(),
                    'workflow_preview' => [
                        'steps' => $workflowPreview['steps'],
                        'current' => $step?->step_name ?? '—',
                        'count' => $workflowPreview['count'],
                        'completed' => $workflowPreview['completed'],
                    ],
                ];
            }
        }

        if ($q) {
            $qLower = mb_strtolower($q);
            $rows = array_values(array_filter($rows, function ($r) use ($qLower) {
                $hay = mb_strtolower(($r['form_name'] ?? '').' '.($r['status'] ?? '').' '.($r['submitter'] ?? ''));

                return str_contains($hay, $qLower);
            }));
        }

        if (strcasecmp((string) $statusFilter, 'Pending') === 0) {
            usort($rows, fn ($a, $b) => [$a['step_group'], $a['step_order']] <=> [$b['step_group'], $b['step_order']]);
        } else {
            usort($rows, function ($a, $b) {
                $ka = $a['acted_at'] ?? $a['updated_at'] ?? null;
                $kb = $b['acted_at'] ?? $b['updated_at'] ?? null;
                if ($ka === $kb) {
                    return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
                }

                return strcmp($kb ?? '', $ka ?? ''); // desc
            });
        }

        return array_map(function ($r) {
            unset($r['acted_at'], $r['updated_at']);

            return $r;
        }, $rows);
    }

    public function getSystemSubmissionsForUser(int $accountId, ?string $q = null, ?string $statusFilter = null): array
    {
        $query = WorkflowStepProgress::with(['step.workflow.form', 'canonicalSubmission.submitter.profile'])
            ->whereIn('status', ['Pending', 'Approved', 'Rejected'])
            ->where('actor_id', $accountId);

        $progresses = $query->get();
        $rows = [];

        foreach ($progresses as $progress) {
            $step = $progress->step;
            $workflow = $step?->workflow;
            $form = $workflow?->form;
            if (! $form) {
                continue;
            }

            $submission = $this->resolveCanonicalSubmissionFromProgress($progress, ['submitter.profile']);
            if (! $submission) {
                continue;
            }

            // Build workflow preview (only this submission’s steps)
            $allSteps = WorkflowStepProgress::with(['step', 'actor.profile'])
                ->where('submission_id', $submission->id)
                ->join('tbl_workflow_step as ws', 'ws.id', '=', 'tbl_workflow_step_progress.step_id')
                ->where('tbl_workflow_step_progress.workflow_id', $progress->workflow_id)
                ->orderBy('ws.step_group')
                ->orderBy('ws.step_order')
                ->select('tbl_workflow_step_progress.*')
                ->get()
                ->map(function ($sp) {
                    return [
                        'id' => $sp->id,
                        'name' => $sp->step?->step_name ?? '—',
                        'status' => $sp->status,
                        'actor' => $sp->actor?->profile?->first_name.' '.$sp->actor?->profile?->last_name,
                        'acted_at' => optional($sp->acted_at)?->toDateTimeString(),
                        'step_group' => $sp->step?->step_group,
                        'step_order' => $sp->step?->step_order,
                    ];
                });

            $rows[] = [
                'id' => $progress->id,
                'form_id' => $form->id,
                'form_name' => $form->form_name,
                'status' => $progress->status,
                'submission_id' => (int) $submission->id,
                'submitted_at' => optional($submission->submitted_at ?? $submission->created_at)->toDateTimeString(),
                'submitter' => $this->resolveSubmitterName($submission),
                'workflow_preview' => [
                    'steps' => $allSteps,
                    'current' => $step?->step_name ?? '—',
                    'count' => $allSteps->count(),
                    'completed' => $allSteps->whereIn('status', ['Approved', 'Rejected'])->count(),
                ],
            ];
        }

        if ($statusFilter && strtolower($statusFilter) !== 'all') {
            $statusFilterLower = strtolower($statusFilter);
            $rows = array_values(array_filter($rows, function ($row) use ($statusFilterLower) {
                return strtolower((string) ($row['status'] ?? '')) === $statusFilterLower;
            }));
        }

        // Search filter
        if ($q) {
            $qLower = mb_strtolower($q);
            $rows = array_values(array_filter($rows, function ($r) use ($qLower) {
                $hay = mb_strtolower(($r['form_name'] ?? '').' '.($r['status'] ?? '').' '.($r['submitter'] ?? ''));

                return str_contains($hay, $qLower);
            }));
        }

        // Sort by latest updated
        usort($rows, fn ($a, $b) => strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? ''));

        return $rows;
    }

    public function getSystemSubmissionsForUserPaginated(
        int $accountId,
        ?string $statusFilter = null,
        ?string $q = null,
        int $perPage = 12
    ): array {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, (int) request()->integer('page', 1));

        $rows = $this->getSystemSubmissionsForUser($accountId, $q, $statusFilter);
        $total = count($rows);
        $items = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function getUserMetrics(int $accountId): array
    {
        return Cache::remember(
            "auflow:dashboard:metrics:user:{$accountId}",
            now()->addMinutes(5),
            function () use ($accountId) {
                $row = WorkflowStepProgress::where('actor_id', $accountId)
                    ->selectRaw(
                        "COUNT(*) as total,
                         SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
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

    private function resolveCanonicalSubmissionFromProgress(WorkflowStepProgress $progress, array $relations = []): ?FormSubmission
    {
        return FormSubmission::query()->with($relations)->find($progress->submission_id);
    }

    private function findCanonicalSubmission(int $formId, int $submissionId, array $relations = []): ?FormSubmission
    {
        return FormSubmission::query()->with($relations)->find($submissionId);
    }

    private function buildAdminSubmissionPayload(
        FormSubmission $submission,
        Form $form,
        ?int $progressId,
        ?int $workflowId = null
    ): array {
        $workflow = $this->buildWorkflowSteps($submission, $workflowId);
        $totalDurationSeconds = collect($workflow)->sum(fn (array $step): int => (int) ($step['duration'] ?? 0));
        $totalDurationHuman = $totalDurationSeconds > 0
            ? CarbonInterval::seconds($totalDurationSeconds)->cascade()->forHumans()
            : null;

        return [
            'id' => (int) $submission->id,
            'progress_id' => $progressId,
            'submission_id' => (int) $submission->id,
            'form_id' => $form->id,
            'form_code' => $form->form_code ?? '-',
            'form_name' => $form->form_name,
            'created_at' => optional($submission->submitted_at ?? $submission->created_at)->toDateTimeString(),
            'updated_at' => optional($submission->updated_at ?? $submission->submitted_at ?? $submission->created_at)->toDateTimeString(),
            'form_fields' => collect($this->schemaFieldsForSubmission($submission, $form))->map(fn (array $field): array => [
                'id' => $field['id'] ?? null,
                'field_name' => $field['field_name'],
                'label' => $field['label'] ?? $field['field_name'],
                'data_type' => $field['data_type'] ?? 'text',
                'is_required' => (bool) ($field['is_required'] ?? false),
                'options' => $field['options'] ?? [],
                'options_meta' => $field['options_meta'] ?? [],
                'field_order' => (int) ($field['field_order'] ?? 0),
                'help_text' => $field['help_text'] ?? null,
                'use_slots' => (bool) ($field['use_slots'] ?? false),
                'require_facility' => (bool) ($field['require_facility'] ?? false),
                'date_mode' => $field['date_mode'] ?? 'single',
                'field_options' => $field['field_options'] ?? [],
            ])->values(),
            'fields' => $this->buildNormalizedSubmissionFields($submission, $form),
            'attachments' => $submission->attachments,
            'slots' => $this->extractSlots($submission),
            'date_ranges' => $this->extractDateRanges($submission),
            'workflow' => $workflow,
            'workflow_duration' => [
                'total_seconds' => $totalDurationSeconds,
                'total_human' => $totalDurationHuman,
            ],
            'snapshot' => $this->buildSnapshotData($submission),
            'submitter' => $this->resolveSubmitterName($submission),
            'can_review' => true,
            'is_latest' => (bool) $submission->is_latest_revision,
            'history' => $this->buildSubmissionHistory($submission, $workflowId),
        ];
    }

    private function resolveCurrentProgressId(FormSubmission $submission, Form $form, int $submissionId): ?int
    {
        $currentProgressId = WorkflowStepProgress::query()
            ->where('tbl_workflow_step_progress.form_id', $form->id)
            ->where('tbl_workflow_step_progress.submission_id', $submission->id)
            ->whereIn('tbl_workflow_step_progress.status', ['Pending', 'Waiting', 'In Review'])
            ->join('tbl_workflow_step as ws', 'ws.id', '=', 'tbl_workflow_step_progress.step_id')
            ->orderBy('ws.step_group')
            ->orderBy('ws.step_order')
            ->orderBy('tbl_workflow_step_progress.id')
            ->value('tbl_workflow_step_progress.id');

        if ($currentProgressId) {
            return (int) $currentProgressId;
        }

        $latestProgressId = WorkflowStepProgress::query()
            ->where('form_id', $form->id)
            ->where('submission_id', $submission->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('id');

        return $latestProgressId ? (int) $latestProgressId : null;
    }

    private function resolveSubmitterName(FormSubmission $submission): string
    {
        $submitter = $submission->submitter;
        $name = trim((string) ($submitter?->profile?->first_name ?? '').' '.(string) ($submitter?->profile?->last_name ?? ''));

        return $name !== '' ? $name : ($submitter?->username ?? 'Unknown');
    }

    private function buildWorkflowSteps(FormSubmission $submission, ?int $workflowId = null): array
    {
        $query = WorkflowStepProgress::query()
            ->with(['step.assignedUser.profile', 'actor.profile', 'commentAttachments.uploader.profile'])
            ->join('tbl_workflow_step as ws', 'ws.id', '=', 'tbl_workflow_step_progress.step_id')
            ->where('tbl_workflow_step_progress.submission_id', $submission->id);

        if ($workflowId) {
            $query->where('tbl_workflow_step_progress.workflow_id', $workflowId);
        }

        return $query
            ->orderBy('ws.step_group')
            ->orderBy('ws.step_order')
            ->select('tbl_workflow_step_progress.*')
            ->get()
            ->map(function (WorkflowStepProgress $progress): array {
                $seconds = $progress->duration_seconds;
                if ($seconds === null && $progress->acted_at) {
                    $start = $progress->started_at ?? $progress->created_at;
                    if ($start) {
                        $seconds = $progress->acted_at->diffInSeconds($start);
                    }
                }

                $attachments = $progress->commentAttachments->map(function ($attachment): array {
                    $uploaderName = trim((string) ($attachment->uploader?->profile?->first_name ?? '').' '.(string) ($attachment->uploader?->profile?->last_name ?? ''));
                    if ($uploaderName === '') {
                        $uploaderName = $attachment->uploader?->username ?? 'Unknown';
                    }

                    return [
                        'id' => $attachment->id,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'size_bytes' => $attachment->size_bytes,
                        'uploaded_by_id' => $attachment->uploaded_by,
                        'uploaded_by_name' => $uploaderName,
                        'uploaded_at' => optional($attachment->created_at)->toDateTimeString(),
                        'download_url' => route('staff-dashboard.progress-attachments.download', ['id' => $attachment->id]),
                        'preview_url' => route('staff-dashboard.progress-attachments.preview', ['id' => $attachment->id]),
                    ];
                })->values()->all();

                return [
                    'id' => $progress->id,
                    'step' => $progress->step->step_name ?? 'Step '.$progress->step_id,
                    'status' => $progress->status,
                    'actor' => $progress->actor?->profile?->full_name
                        ?? $progress->step?->assignedUser?->profile?->full_name
                        ?? '—',
                    'acted_at' => optional($progress->acted_at)->toDateTimeString(),
                    'comments' => $progress->comments,
                    'duration' => $seconds !== null ? (int) $seconds : null,
                    'duration_human' => $seconds ? CarbonInterval::seconds($seconds)->cascade()->forHumans() : null,
                    'attachments' => $attachments,
                ];
            })
            ->values()
            ->all();
    }

    private function buildNormalizedSubmissionFields(FormSubmission $submission, Form $form): array
    {
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];

        return collect($this->schemaFieldsForSubmission($submission, $form))
            ->map(function (array $field) use ($payload): array {
                $dataType = strtolower((string) ($field['data_type'] ?? 'text'));
                $value = $payload[$field['field_name']] ?? null;

                return [
                    'field_name' => $field['field_name'],
                    'label' => $field['label'] ?? $field['field_name'],
                    'value' => $this->fieldValueNormalizer->normalizeChoiceValue($dataType, $value),
                    'type' => $dataType,
                    'field_options' => $field['field_options'] ?? [],
                ];
            })
            ->values()
            ->all();
    }

    private function schemaFieldsForSubmission(FormSubmission $submission, Form $form): array
    {
        $schemaSnapshot = is_array($submission->schema_snapshot_json) ? $submission->schema_snapshot_json : [];
        $fields = $schemaSnapshot['fields'] ?? null;
        if (is_array($fields) && $fields !== []) {
            return array_values($fields);
        }

        $formFields = $form->toSchemaArray()['fields'] ?? [];

        if ($formFields instanceof \Illuminate\Support\Collection) {
            return $formFields->values()->all();
        }

        return is_array($formFields) ? $formFields : [];
    }

    private function extractSlots(FormSubmission $submission): array
    {
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];
        if (isset($payload['slots']) && is_array($payload['slots'])) {
            return array_values($payload['slots']);
        }

        return $submission->slots
            ->map(fn (Slot $slot): array => [
                'date' => optional($slot->date)->toDateString(),
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'facility_id' => $slot->facility_id,
                'facility' => $slot->facility?->name,
            ])
            ->values()
            ->all();
    }

    private function extractDateRanges(FormSubmission $submission): array
    {
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];
        $dateRanges = $payload['date_ranges'] ?? [];
        if (! is_array($dateRanges)) {
            return [];
        }

        return array_values(array_map(function (array $range): array {
            $start = $range['start'] ?? $range['from'] ?? $range['start_date'] ?? null;
            $end = $range['end'] ?? $range['to'] ?? $range['end_date'] ?? null;

            return [
                'start' => $start,
                'end' => $end,
                'start_date' => $range['start_date'] ?? $start,
                'end_date' => $range['end_date'] ?? $end,
            ];
        }, $dateRanges));
    }

    private function buildSnapshotData(FormSubmission $submission): array
    {
        $snapshot = Snapshot::query()
            ->where('submission_id', $submission->id)
            ->orderByDesc('id')
            ->first();

        if (! $snapshot) {
            return ['exists' => false];
        }

        return [
            'exists' => true,
            'public_id' => $snapshot->public_id,
            'short_code' => substr($snapshot->public_id, -6),
            'status' => $snapshot->status,
            'approved_at' => optional($snapshot->approved_at)->toDateTimeString(),
            'url' => route('snapshots.show', $snapshot->public_id),
            'approved_by' => $snapshot->approved_by,
            'comment' => $snapshot->comment,
        ];
    }

    private function buildSubmissionHistory(FormSubmission $submission, ?int $workflowId = null): array
    {
        $rootSubmissionId = $submission->root_submission_id ?: $submission->id;
        $chain = FormSubmission::query()
            ->where(function ($query) use ($rootSubmissionId) {
                $query->where('root_submission_id', $rootSubmissionId)
                    ->orWhere('id', $rootSubmissionId);
            })
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $progressQuery = WorkflowStepProgress::query()
            ->whereIn('submission_id', $chain->pluck('id'));
        if ($workflowId) {
            $progressQuery->where('workflow_id', $workflowId);
        }

        $latestStatuses = $progressQuery
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('submission_id')
            ->map(fn ($group) => $group->first()?->status ?? 'Pending');

        $progressIdsQuery = WorkflowStepProgress::query()
            ->whereIn('submission_id', $chain->pluck('id'));
        if ($workflowId) {
            $progressIdsQuery->where('workflow_id', $workflowId);
        }

        $progressIds = $progressIdsQuery
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('submission_id')
            ->map(fn ($group) => $group->first()?->id);

        return $chain->values()->map(function (FormSubmission $row, int $index) use ($latestStatuses, $progressIds): array {
            return [
                'id' => (int) $row->id,
                'progress_id' => $progressIds->get($row->id),
                'version' => $index + 1,
                'created_at' => optional($row->submitted_at ?? $row->created_at)->toDateTimeString(),
                'updated_at' => optional($row->updated_at ?? $row->submitted_at ?? $row->created_at)->toDateTimeString(),
                'status' => $latestStatuses->get($row->id) ?? 'Pending',
                'is_latest' => (bool) $row->is_latest_revision,
            ];
        })->all();
    }

    /* ============================================================
       Robust helpers to normalize JSON-encoded choice field values
       ============================================================ */

    /**
     * Decode strings that "look like" JSON, including HTML-escaped or backslash-escaped payloads.
     * Returns array|object|string|null (null only when input is null).
     */
    private function decodeJsonish(mixed $val): mixed
    {
        if ($val === null) {
            return null;
        }
        if (is_array($val) || is_object($val)) {
            return $val;
        }

        if (! is_string($val)) {
            return $val;
        }

        $s = trim($val);
        if ($s === '') {
            return $s;
        }

        // 1) html entity unescape (covers &quot;, &#34;, etc.)
        $variants = [];
        $u0 = html_entity_decode($s, ENT_QUOTES | ENT_HTML5);
        $variants[] = $s;
        $variants[] = $u0;

        // 2) strip outer quotes if present
        $stripOuter = function (string $x): string {
            if ((str_starts_with($x, '"') && str_ends_with($x, '"')) ||
                (str_starts_with($x, "'") && str_ends_with($x, "'"))) {
                return substr($x, 1, -1);
            }

            return $x;
        };
        $variants[] = $stripOuter($s);
        $variants[] = $stripOuter($u0);

        // 3) de-escape backslashes/newlines/etc.
        $deSlash = function (string $x): string {
            $x = str_replace(['\\n', '\\r', '\\t'], '', $x);
            $x = str_replace(['\\"', "\\'"], ['"', "'"], $x);

            return $x;
        };
        $variants[] = $deSlash($s);
        $variants[] = $deSlash($u0);
        $variants[] = $deSlash($stripOuter($s));
        $variants[] = $deSlash($stripOuter($u0));

        foreach ($variants as $cand) {
            $cand = trim($cand);
            if ($cand === '') {
                continue;
            }
            $decoded = json_decode($cand, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Also normalize array-of-stringified-objects
                if (is_array($decoded)) {
                    return array_map(function ($v) {
                        if (is_string($v)) {
                            $try = json_decode($v, true);

                            return json_last_error() === JSON_ERROR_NONE ? $try : $v;
                        }

                        return $v;
                    }, $decoded);
                }

                return $decoded;
            }
        }

        // Could not decode to JSON; return cleaned last candidate
        return $deSlash($stripOuter($u0));
    }

    /**
     * Normalize choice-like fields to arrays/objects when stored as JSON.
     * Keeps non-choice fields unchanged.
     */
    private function normalizeChoiceValue(string $dataType, mixed $raw): mixed
    {
        $choiceTypes = ['checkbox', 'radio', 'select', 'multiselect'];
        if (! in_array(strtolower($dataType), $choiceTypes, true)) {
            return $raw;
        }

        $decoded = $this->decodeJsonish($raw);

        // For checkbox/multiselect: ensure array
        if (in_array(strtolower($dataType), ['checkbox', 'multiselect'], true)) {
            if (is_array($decoded)) {
                return $decoded;
            }
            if ($decoded === null || $decoded === '') {
                return [];
            }

            return [$decoded];
        }

        // For radio/select:
        // - If it's a list (e.g., [{"value":...}]) return the first item.
        // - If it's an associative array/object (e.g., ["value"=>"x","text"=>"y"]) return it as-is.
        if (is_array($decoded)) {
            if (array_is_list($decoded)) {
                return $decoded[0] ?? null;
            }

            return $decoded; // associative/object-like
        }

        return $decoded;
    }
}
