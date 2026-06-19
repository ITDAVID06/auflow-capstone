<?php

namespace App\Services;

use App\Mail\SubmissionPendingMail;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Services\SubmissionReadRepository;
use App\Modules\Notifications\Models\Notification;
use App\Modules\WorkflowBuilder\Actions\SendSubmissionCompletionEmail;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(
        private readonly SubmissionReadRepository $submissionReadRepository
    ) {}

    private const APPROVER_NOTIFICATION_DEDUPE_SECONDS = 120;

    private const COMPLETION_NOTIFICATION_DEDUPE_HOURS = 6;

    /**
     * Notify workflow approver(s) - supports multi-approver OR condition.
     */
    public function notifyApprover(Form $form, WorkflowStep $step, int $submissionId, ?int $progressId = null): void
    {
        $recipients = $this->resolveStepRecipients($step);

        if ($recipients->isEmpty()) {
            \Log::warning('[Notify] Skipped: no resolvable recipients for step', [
                'step_id' => $step->id,
                'submission_id' => $submissionId,
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            $email = (string) ($recipient['email'] ?? '');
            if ($email === '') {
                continue;
            }

            Mail::to($email)->sendNow(
                new SubmissionPendingMail($form, $step, $submissionId, $progressId)
            );
        }

        $accountIds = $recipients
            ->pluck('account_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (! empty($accountIds)) {
            $accountIds = $this->filterAccountsWithoutRecentApproverNotification($accountIds, $step->id);
        }

        if (! empty($accountIds)) {
            $this->sendPendingApprovalInAppNotifications($accountIds, $form, $step, $submissionId, $progressId);
        }
    }

    private function resolveStepRecipients(WorkflowStep $step): Collection
    {
        $step->loadMissing(['approvers.user.profile', 'assignedUser.profile']);

        $recipients = collect();
        if ($step->approvers->isNotEmpty()) {
            foreach ($step->approvers as $approver) {
                $recipients->push([
                    'account_id' => $approver->account_id,
                    'email' => $approver->user?->email ?? $approver->user?->profile?->email,
                ]);
            }
        } else {
            $recipients->push([
                'account_id' => $step->assigned_account_id,
                'email' => $step->assignedUser?->email ?? $step->assignedUser?->profile?->email,
            ]);
        }

        return $recipients
            ->filter(fn ($recipient) => ! empty($recipient['account_id']) || ! empty($recipient['email']))
            ->unique(fn ($recipient) => ($recipient['account_id'] ?? '').'|'.($recipient['email'] ?? ''))
            ->values();
    }

    /**
     * Prevent duplicate in-app approver notifications caused by retries or repeated unlock calls.
     */
    private function filterAccountsWithoutRecentApproverNotification(array $accountIds, int $stepId): array
    {
        $windowStart = now()->subSeconds(self::APPROVER_NOTIFICATION_DEDUPE_SECONDS);

        $alreadyNotified = Notification::query()
            ->whereIn('account_id', $accountIds)
            ->where('type', 'workflow_pending_approval')
            ->where('related_type', 'workflow_step')
            ->where('related_id', $stepId)
            ->where('created_at', '>=', $windowStart)
            ->pluck('account_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if (empty($alreadyNotified)) {
            return $accountIds;
        }

        return array_values(array_filter(
            $accountIds,
            fn ($accountId) => ! in_array((int) $accountId, $alreadyNotified, true)
        ));
    }

    private function sendPendingApprovalInAppNotifications(array $accountIds, Form $form, WorkflowStep $step, int $submissionId, ?int $progressId = null): void
    {
        $now = now();
        $triggeredBy = auth()->user()?->account_id;
        $actionUrl = $progressId
            ? route('staff-dashboard.submission.view', ['id' => $progressId])
            : route('staff-dashboard.index');

        $rows = collect($accountIds)
            ->map(fn ($accountId) => (int) $accountId)
            ->unique()
            ->values()
            ->map(function (int $accountId) use ($form, $step, $submissionId, $triggeredBy, $now, $actionUrl): array {
                return [
                    'account_id' => $accountId,
                    'type' => 'workflow_pending_approval',
                    'title' => 'New Approval Request',
                    'message' => "A {$form->form_name} request is awaiting your approval at step: {$step->step_name}",
                    'action_url' => $actionUrl,
                    'action_text' => 'Review Request',
                    'related_type' => 'workflow_step',
                    'related_id' => $step->id,
                    'icon' => 'bell',
                    'priority' => 'high',
                    'triggered_by' => $triggeredBy,
                    'is_read' => false,
                    'idempotency_key' => $this->buildPendingApprovalIdempotencyKey($submissionId, (int) $step->id, $accountId),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();

        if (! empty($rows)) {
            DB::table('tbl_notification')->insertOrIgnore($rows);
        }
    }

    private function buildPendingApprovalIdempotencyKey(int $submissionId, int $stepId, int $accountId): string
    {
        return "workflow_pending_approval:{$submissionId}:{$stepId}:{$accountId}";
    }

    /**
     * Sequential on submit: notify the first group that actually has assignees.
     */
    public function notifyFirstSequentialApprovers(Workflow $workflow, int $submissionId, Form $form): void
    {
        // Find the first step group that has at least one Pending step with an assignee.
        // Filtering by 'Pending' is safe here because we also check whether any earlier
        // group was acted on by a human (Approved/Rejected). If a human already acted on
        // an earlier group, the approval flow already notified the pending group — we skip
        // to avoid duplicate notifications. Auto-skipped groups (status=Skipped) do not
        // block notification of the next pending group.
        $firstPendingGroup = WorkflowStepProgress::query()
            ->where('tbl_workflow_step_progress.workflow_id', $workflow->id)
            ->where('tbl_workflow_step_progress.submission_id', $submissionId)
            ->where('tbl_workflow_step_progress.status', 'Pending')
            ->whereHas('step', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNotNull('assigned_account_id')
                        ->orWhereHas('approvers');
                });
            })
            ->join('tbl_workflow_step', 'tbl_workflow_step.id', '=', 'tbl_workflow_step_progress.step_id')
            ->min('tbl_workflow_step.step_group');

        if ($firstPendingGroup === null) {
            \Log::warning('[Notify] No pending steps with assignees found for sequential notification', [
                'workflow_id' => $workflow->id,
                'submission_id' => $submissionId,
            ]);

            return;
        }

        // If any earlier group was acted on by a human (Approved/Rejected), the approval
        // flow already triggered notifications for the pending group — skip to avoid duplicates.
        $humanActedInEarlierGroup = WorkflowStepProgress::query()
            ->where('tbl_workflow_step_progress.workflow_id', $workflow->id)
            ->where('tbl_workflow_step_progress.submission_id', $submissionId)
            ->whereIn('tbl_workflow_step_progress.status', ['Approved', 'Rejected'])
            ->join('tbl_workflow_step', 'tbl_workflow_step.id', '=', 'tbl_workflow_step_progress.step_id')
            ->where('tbl_workflow_step.step_group', '<', $firstPendingGroup)
            ->exists();

        if ($humanActedInEarlierGroup) {
            \Log::info('[Notify] Earlier group was already acted on — submission notification suppressed to avoid duplicate', [
                'workflow_id' => $workflow->id,
                'submission_id' => $submissionId,
                'first_pending_group' => $firstPendingGroup,
            ]);

            return;
        }

        $firstSteps = WorkflowStepProgress::query()
            ->with(['step.approvers.user', 'step.assignedUser'])
            ->where('tbl_workflow_step_progress.workflow_id', $workflow->id)
            ->where('tbl_workflow_step_progress.submission_id', $submissionId)
            ->where('tbl_workflow_step_progress.status', 'Pending')
            ->whereHas('step', function ($query) use ($firstPendingGroup) {
                $query->where('step_group', $firstPendingGroup)
                    ->where(function ($subQuery) {
                        $subQuery->whereNotNull('assigned_account_id')
                            ->orWhereHas('approvers');
                    });
            })
            ->get();

        foreach ($firstSteps as $progress) {
            if (! $progress->step) {
                continue;
            }

            $this->notifyApprover($form, $progress->step, $submissionId, (int) $progress->id);
        }
    }

    /**
     * Parallel on submit: notify everyone with an assignee.
     */
    public function notifyAllParallelApprovers(Workflow $workflow, int $submissionId, Form $form): void
    {
        $pendingProgresses = WorkflowStepProgress::query()
            ->with(['step.approvers.user', 'step.assignedUser'])
            ->where('tbl_workflow_step_progress.workflow_id', $workflow->id)
            ->where('tbl_workflow_step_progress.submission_id', $submissionId)
            ->where('tbl_workflow_step_progress.status', 'Pending')
            ->whereHas('step', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNotNull('assigned_account_id')
                        ->orWhereHas('approvers');
                });
            })
            ->get();

        foreach ($pendingProgresses as $progress) {
            if ($progress->step) {
                $this->notifyApprover($form, $progress->step, $submissionId, (int) $progress->id);
            }
        }
    }

    /**
     * Sequential after approve: notify next group that has assignees.
     */
    public function notifyNextSequentialApprovers(WorkflowStepProgress $progress): void
    {
        $progress->loadMissing('step', 'form');

        $currentGroup = $progress->step->step_group;
        $this->notifyNextSequentialApproversByGroup(
            workflow: $progress->step->workflow,
            submissionId: (int) $progress->submission_id,
            currentGroup: (int) $currentGroup,
            form: $progress->form
        );
    }

    /**
     * Sequential after approve: notify the next pending group with assignees.
     */
    public function notifyNextSequentialApproversByGroup(Workflow $workflow, int $submissionId, int $currentGroup, Form $form): void
    {
        $nextGroupWithAssignee = WorkflowStepProgress::query()
            ->where('tbl_workflow_step_progress.workflow_id', $workflow->id)
            ->where('tbl_workflow_step_progress.submission_id', $submissionId)
            ->where('tbl_workflow_step_progress.status', 'Pending')
            ->whereHas('step', function ($query) use ($currentGroup) {
                $query->where('step_group', '>', $currentGroup)
                    ->where(function ($subQuery) {
                        $subQuery->whereNotNull('assigned_account_id')
                            ->orWhereHas('approvers');
                    });
            })
            ->join('tbl_workflow_step', 'tbl_workflow_step.id', '=', 'tbl_workflow_step_progress.step_id')
            ->min('tbl_workflow_step.step_group');

        if ($nextGroupWithAssignee === null) {
            \Log::info('[Notify] No next sequential group with assignees', [
                'workflow_id' => $workflow->id,
                'submission_id' => $submissionId,
                'current_group' => $currentGroup,
            ]);

            return; // no more approvers
        }

        $nextSteps = WorkflowStepProgress::query()
            ->with(['step.approvers.user', 'step.assignedUser'])
            ->where('tbl_workflow_step_progress.workflow_id', $workflow->id)
            ->where('tbl_workflow_step_progress.submission_id', $submissionId)
            ->where('tbl_workflow_step_progress.status', 'Pending')
            ->whereHas('step', function ($query) use ($nextGroupWithAssignee) {
                $query->where('step_group', $nextGroupWithAssignee)
                    ->where(function ($subQuery) {
                        $subQuery->whereNotNull('assigned_account_id')
                            ->orWhereHas('approvers');
                    });
            })
            ->get();

        foreach ($nextSteps as $nextProgress) {
            if (! $nextProgress->step) {
                continue;
            }

            $this->notifyApprover($form, $nextProgress->step, $submissionId, (int) $nextProgress->id);
        }
    }

    /**
     * Notify submitter once workflow is completed (approved/rejected).
     *
     * @param  'approved'|'rejected'  $outcome
     */
    public function notifySubmissionCompletion(Workflow $workflow, int $submissionId, string $outcome): void
    {
        $outcome = strtolower($outcome);
        if (! in_array($outcome, ['approved', 'rejected'], true)) {
            return;
        }

        $form = $workflow->form()->first();
        if (! $form) {
            return;
        }

        $submission = $this->resolveCanonicalSubmission($form->id, $submissionId);
        if (! $submission || ! $submission->account_id) {
            return;
        }

        if (! $this->isWorkflowCompletedForOutcome($workflow->id, $submissionId, $outcome)) {
            return;
        }

        $cacheKey = "submission:{$submissionId}:completed:{$outcome}";
        if (! cache()->add($cacheKey, now()->toIso8601String(), now()->addHours(self::COMPLETION_NOTIFICATION_DEDUPE_HOURS))) {
            return;
        }

        $submitter = $submission->submitter;

        if (! $submitter || empty($submitter->email)) {
            return;
        }

        $submitterName = trim((string) ($submitter->profile?->first_name ?? '').' '.(string) ($submitter->profile?->last_name ?? ''));
        if ($submitterName === '') {
            $submitterName = (string) ($submitter->username ?: 'Submitter');
        }

        $viewUrl = route('student-dashboard.submission.view', [
            'formId' => $form->id,
            'submissionId' => $submissionId,
        ]);

        SendSubmissionCompletionEmail::run(
            form: $form,
            submissionId: $submissionId,
            outcome: $outcome,
            submitterEmail: $submitter->email,
            submitterName: $submitterName,
            viewUrl: $viewUrl
        );

        try {
            $notificationService = app(\App\Modules\Notifications\Services\NotificationService::class);
            $notificationService->send(
                recipientIds: (int) $submission->account_id,
                type: 'submission_'.$outcome,
                data: [
                    'title' => $outcome === 'approved' ? 'Request Approved' : 'Request Rejected',
                    'message' => $outcome === 'approved'
                        ? "Your {$form->form_name} request has been approved."
                        : "Your {$form->form_name} request has been rejected.",
                    'action_url' => $viewUrl,
                    'action_text' => 'View Request →',
                    'related_type' => 'submission',
                    'related_id' => $submissionId,
                    'icon' => $outcome === 'approved' ? 'check-circle' : 'x-circle',
                    'priority' => 'high',
                    'triggered_by' => null,
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('[Notification] Failed to send completion notification', [
                'submission_id' => $submissionId,
                'outcome' => $outcome,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveCanonicalSubmission(int $formId, int $submissionId): ?FormSubmission
    {
        return FormSubmission::query()->with(['submitter.profile'])->find($submissionId);
    }

    private function isWorkflowCompletedForOutcome(
        int $workflowId,
        int $submissionId,
        string $outcome
    ): bool {
        $statuses = $this->submissionReadRepository->workflowStatusesForSubmission(
            workflowId: $workflowId,
            submissionId: $submissionId,
        );

        if ($statuses->isEmpty()) {
            return false;
        }

        $hasActiveSteps = $statuses->contains(fn ($status) => in_array($status, ['Pending', 'Waiting'], true));
        if ($hasActiveSteps) {
            return false;
        }

        if ($outcome === 'approved') {
            return $statuses->every(fn ($status) => in_array($status, ['Approved', 'Skipped'], true));
        }

        return $statuses->contains(fn ($status) => in_array($status, ['Rejected', 'Auto-Rejected'], true));
    }
}
