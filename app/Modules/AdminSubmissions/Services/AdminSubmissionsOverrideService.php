<?php

namespace App\Modules\AdminSubmissions\Services;

use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class AdminSubmissionsOverrideService
{
    public function __construct(protected NotificationService $notifier) {}

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
            $progress = WorkflowStepProgress::with(['version', 'step.workflow'])
                ->lockForUpdate()
                ->findOrFail($progressId);

            // Lock the parent submission to prevent race conditions during concurrent admin override attempts
            \App\Modules\FormBuilder\Models\FormSubmission::where('id', $progress->submission_id)->lockForUpdate()->firstOrFail();

            if (! in_array($progress->status, ['Pending', 'Waiting'], true)) {
                throw new \RuntimeException('Already processed');
            }

            if (! $forceAssignment && (int) $progress->step->assigned_account_id !== $adminId) {
                throw new \RuntimeException('Unauthorized: not the assigned approver');
            }

            if (! $forceReadiness && $status === 'Approved' && ! $this->isStepReady($progress->step, $progress->submission_id, $progress->version)) {
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

            if ($status === 'Rejected') {
                $step = $progress->step;
                $stepsSnapshot = $progress->version?->steps_snapshot ?? [];

                // Cancel peer steps in the same parallel group (snapshot-first)
                if (! empty($stepsSnapshot)) {
                    $rejectedStepArr = collect($stepsSnapshot)->firstWhere('id', $step->id);
                    $rejectedGroup = (int) ($rejectedStepArr['step_group'] ?? 0);

                    if ($rejectedGroup > 0) {
                        // Cancel Pending peers in same group (excluding self)
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

                    // Cascade-reject all downstream steps from snapshot
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

            return $progress->fresh(['version', 'step.workflow', 'workflow', 'form', 'actor.profile']);
        });

        if ($progress->status === 'Approved') {
            try {
                $workflow = $progress->step->workflow;
                $version = $progress->version;
                $stepsSnapshot = $version?->steps_snapshot ?? [];
                // Resolve step_group from snapshot; fall back to live step for pre-version submissions
                $currentGroup = ! empty($stepsSnapshot)
                    ? (int) (collect($stepsSnapshot)->firstWhere('id', $progress->step_id)['step_group'] ?? 0)
                    : (int) ($progress->step?->step_group ?? 0);
                $this->advanceWorkflowIfNeeded($workflow, (int) $progress->submission_id, $currentGroup, $version);

                $allSteps = WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                    ->where('submission_id', $progress->submission_id)->count();

                $doneSteps = WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                    ->where('submission_id', $progress->submission_id)
                    ->whereIn('status', ['Approved', 'Skipped'])->count();

                if ($allSteps > 0 && $doneSteps === $allSteps) {
                    $this->notifier->notifySubmissionCompletion($progress->workflow, (int) $progress->submission_id, 'approved');
                }
            } catch (\Throwable $e) {
                \Log::warning('[AdminOverride] advance/notify failed', [
                    'progress_id' => $progress->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($progress->status === 'Rejected') {
            try {
                $this->notifier->notifySubmissionCompletion($progress->workflow, (int) $progress->submission_id, 'rejected');
            } catch (\Throwable $e) {
                \Log::warning('[AdminOverride] rejection notification failed', [
                    'progress_id' => $progress->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            \App\Modules\VerificationSnapshot\Jobs\GenerateVerificationSnapshot::dispatchAfterResponse($progress->id);
            \Log::info('[Snapshot] Scheduled snapshot generation', ['progress_id' => $progress->id]);
        } catch (\Throwable $e) {
            \Log::warning('[Snapshot] Failed (override)', ['progress_id' => $progress->id, 'error' => $e->getMessage()]);
        }
    }

    private function isStepReady(WorkflowStep $step, int $submissionId, ?\App\Modules\WorkflowBuilder\Models\WorkflowVersion $version = null): bool
    {
        $stepsSnapshot = $version ? (is_string($version->steps_snapshot) ? json_decode($version->steps_snapshot, true) : $version->steps_snapshot) : [];
        if (! empty($stepsSnapshot)) {
            $snapshotSteps = collect($stepsSnapshot);
            $currentStep = $snapshotSteps->firstWhere('id', $step->id);
            if (! $currentStep) {
                return false;
            }

            $currentGroup = $currentStep['step_group'] ?? 0;
            if ($currentGroup > 0) {
                $priorStepIds = $snapshotSteps->filter(fn ($s) => ($s['step_group'] ?? 0) < $currentGroup)->pluck('id');
                if ($priorStepIds->isEmpty()) {
                    return true;
                }

                $statuses = WorkflowStepProgress::whereIn('step_id', $priorStepIds)
                    ->where('submission_id', $submissionId)
                    ->pluck('status');

                return $statuses->every(fn ($status) => in_array($status, ['Approved', 'Skipped'], true));
            }

            // Fallback to sequential
            $currentOrder = $currentStep['step_order'] ?? 0;
            $priorStepIds = $snapshotSteps->filter(fn ($s) => ($s['step_order'] ?? 0) < $currentOrder)->pluck('id');
            if ($priorStepIds->isEmpty()) {
                return true;
            }

            $statuses = WorkflowStepProgress::whereIn('step_id', $priorStepIds)
                ->where('submission_id', $submissionId)
                ->pluck('status');

            return $statuses->every(fn ($status) => in_array($status, ['Approved', 'Skipped'], true));
        }

        // FALLBACK to live table logic for pre-version (legacy) submissions
        $workflow = $step->workflow;

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

            return $statuses->every(fn ($stepStatus) => in_array($stepStatus, ['Approved', 'Skipped'], true));
        }

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

        return $statuses->every(fn ($stepStatus) => in_array($stepStatus, ['Approved', 'Skipped'], true));
    }

    private function advanceWorkflowIfNeeded(Workflow $workflow, int $submissionId, int $currentGroup, ?\App\Modules\WorkflowBuilder\Models\WorkflowVersion $version = null): void
    {
        $group = (int) $currentGroup;

        $stepsSnapshot = $version ? (is_string($version->steps_snapshot) ? json_decode($version->steps_snapshot, true) : $version->steps_snapshot) : [];

        while (true) {
            $query = WorkflowStepProgress::query()
                ->where('workflow_id', $workflow->id)
                ->where('submission_id', $submissionId);

            if (! empty($stepsSnapshot)) {
                $groupStepIds = collect($stepsSnapshot)->filter(fn ($s) => (int) ($s['step_group'] ?? 0) === $group)->pluck('id');
                if ($groupStepIds->isEmpty()) {
                    break;
                }
                $statuses = $query->whereIn('step_id', $groupStepIds)->pluck('status');
            } else {
                $statuses = $query->whereHas('step', fn ($q) => $q->where('step_group', $group))->pluck('status');
            }

            if ($statuses->isEmpty()) {
                break;
            }

            $hasActive = $statuses->contains(fn ($status) => in_array($status, ['Pending', 'Waiting'], true));
            if ($hasActive) {
                break;
            }

            $nextGroup = $group + 1;

            if (! empty($stepsSnapshot)) {
                $nextGroupStepIds = collect($stepsSnapshot)->filter(fn ($s) => (int) ($s['step_group'] ?? 0) === $nextGroup)->pluck('id');
                if ($nextGroupStepIds->isEmpty()) {
                    break;
                }
                $nextGroupStatuses = WorkflowStepProgress::query()
                    ->where('workflow_id', $workflow->id)
                    ->where('submission_id', $submissionId)
                    ->whereIn('step_id', $nextGroupStepIds)
                    ->pluck('status');

                if ($nextGroupStatuses->isEmpty()) {
                    break;
                }

                $unlockedCount = WorkflowStepProgress::query()
                    ->where('workflow_id', $workflow->id)
                    ->where('submission_id', $submissionId)
                    ->where('status', 'Waiting')
                    ->whereIn('step_id', $nextGroupStepIds)
                    ->update([
                        'status' => 'Pending',
                        'started_at' => now(),
                        'updated_at' => now(),
                    ]);
            } else {
                // Legacy fallback: query live step table
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
            }

            if ($unlockedCount > 0) {
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
                    \Log::warning('[Notify] Failed to notify next approvers in AdminSubmissionsOverrideService', [
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
     * Return the IDs of all steps downstream of $fromStep using the frozen snapshot.
     *
     * Downstream is defined purely by step_group / step_order:
     *   - If step_group > 0: all snapshot steps in groups AFTER the rejected step's group.
     *   - Otherwise (sequential): all snapshot steps with a higher step_order.
     *
     * Falls back to an empty array when no snapshot is available (caller handles legacy path).
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
}
