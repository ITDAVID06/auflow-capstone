<?php

namespace App\Modules\WorkflowBuilder\Observers;

use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Services\AuditLogger;

class WorkflowStepProgressObserver
{
    public function __construct(
        private AuditLogger $audit,
        private NotificationService $notificationService
    ) {}

    public function created(WorkflowStepProgress $m): void
    {
        [$stepName, $workflowId] = $this->stepDetails($m);
        $this->audit->userAction('workflow_step_progress_created', $m, 'Success', "Created progress for step {$stepName}", [
            'progress_id' => $m->getKey(),
            'step_id' => $m->step_id,
            'step_name' => $stepName,
            'workflow_id' => $workflowId,
        ]);

        if ($m->status === 'Pending') {
            // Notify submitter on first pending step (successful submission)
            $this->notifySubmitterOnSuccess($m);
        }
    }

    public function updated(WorkflowStepProgress $m): void
    {
        [$stepName, $workflowId] = $this->stepDetails($m);
        $this->audit->userAction('workflow_step_progress_updated', $m, 'Success', "Updated progress for step {$stepName}", [
            'progress_id' => $m->getKey(),
            'step_id' => $m->step_id,
            'step_name' => $stepName,
            'workflow_id' => $workflowId,
        ]);

        // Send in-app notifications when step is approved or rejected
        $this->sendWorkflowNotifications($m);
    }

    public function deleted(WorkflowStepProgress $m): void
    {
        [$stepName, $workflowId] = $this->stepDetails($m);
        $this->audit->userAction('workflow_step_progress_deleted', $m, 'Warning', "Deleted progress for step {$stepName}", [
            'progress_id' => $m->getKey(),
            'step_id' => $m->step_id,
            'step_name' => $stepName,
            'workflow_id' => $workflowId,
        ]);
    }

    private function stepDetails(WorkflowStepProgress $m): array
    {
        $step = WorkflowStep::find($m->step_id);

        return [
            $step?->step_name ?? $step?->name ?? "Step #{$m->step_id}",
            $step?->workflow_id,
        ];
    }

    /**
     * Send in-app notifications for workflow step actions
     */
    private function sendWorkflowNotifications(WorkflowStepProgress $progress): void
    {
        // Only send notifications when action is taken (approved/rejected, including overrides)
        $isApproved = in_array($progress->action_taken, ['Approved', 'Override-Approve']);
        $isRejected = in_array($progress->action_taken, ['Rejected', 'Override-Reject']);

        if (! $isApproved && ! $isRejected) {
            return;
        }

        try {
            $progress->loadMissing(['step', 'form', 'actor.profile', 'workflow']);

            // Get the submitter (from the dynamic submission table)
            $submitter = $this->getSubmitter($progress);

            if (! $submitter) {
                \Log::warning('[Notification] Could not find submitter for notification', [
                    'form_id' => $progress->form_id,
                    'submission_id' => $progress->submission_id,
                ]);

                return;
            }

            $actorName = $progress->actor?->profile?->full_name ?? $progress->actor?->username ?? 'System';
            $stepName = $progress->step?->step_name ?? 'Unknown Step';
            $formName = $progress->form?->form_name ?? 'Unknown Form';

            // 1. Notify the submitter about the action taken
            if ($isApproved) {
                // Check if this is the final step (workflow completed)
                $isWorkflowCompleted = $this->isWorkflowCompleted($progress);

                if ($isWorkflowCompleted) {
                    // Workflow is fully completed - send completion notification
                    $this->notificationService->send(
                        [$submitter],
                        'workflow_completed',
                        [
                            'title' => 'Request Fully Approved',
                            'message' => "Your {$formName} request has been fully approved and completed!",
                            'action_url' => route('student-dashboard.submission.view', ['formId' => $progress->form_id, 'submissionId' => $progress->submission_id]),
                            'action_text' => 'View Request',
                            'related_type' => 'workflow_step_progress',
                            'related_id' => $progress->id,
                            'priority' => 'high',
                            'triggered_by' => $progress->actor_id,
                        ]
                    );
                } else {
                    // Still more steps - send step approval notification
                    $this->notificationService->send(
                        [$submitter],
                        'workflow_approved',
                        [
                            'title' => 'Request Approved',
                            'message' => "{$actorName} approved your {$formName} request at step: {$stepName}",
                            'action_url' => route('student-dashboard.submission.view', ['formId' => $progress->form_id, 'submissionId' => $progress->submission_id]),
                            'action_text' => 'View Request',
                            'related_type' => 'workflow_step_progress',
                            'related_id' => $progress->id,
                            'priority' => 'normal',
                            'triggered_by' => $progress->actor_id,
                        ]
                    );
                }

            } else {
                // Rejected - workflow terminates
                $this->notificationService->send(
                    [$submitter],
                    'workflow_rejected',
                    [
                        'title' => 'Request Rejected',
                        'message' => "{$actorName} rejected your {$formName} request at step: {$stepName}. The workflow has been terminated.",
                        'action_url' => route('student-dashboard.submission.view', ['formId' => $progress->form_id, 'submissionId' => $progress->submission_id]),
                        'action_text' => 'View Request',
                        'related_type' => 'workflow_step_progress',
                        'related_id' => $progress->id,
                        'priority' => 'high',
                        'triggered_by' => $progress->actor_id,
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Log::error('[Notification] Failed to send workflow notifications', [
                'progress_id' => $progress->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if the workflow is fully completed (all steps approved/skipped)
     */
    private function isWorkflowCompleted(WorkflowStepProgress $progress): bool
    {
        try {
            // Get total steps that require approval
            $totalSteps = WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                ->where('submission_id', $progress->submission_id)
                ->count();

            // Get approved/skipped steps
            $completedSteps = WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                ->where('submission_id', $progress->submission_id)
                ->whereIn('status', ['Approved', 'Skipped'])
                ->count();

            return $totalSteps > 0 && $totalSteps === $completedSteps;
        } catch (\Throwable $e) {
            \Log::error('[Notification] Error checking workflow completion', [
                'progress_id' => $progress->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get submitter account_id from the dynamic submission table
     */
    private function getSubmitter(WorkflowStepProgress $progress): ?int
    {
        try {
            $submission = $this->resolveCanonicalSubmission($progress);

            if (! $submission || ! $submission->account_id) {
                \Log::warning('[Notification] Canonical submission not found for notification', [
                    'form_id' => $progress->form_id,
                    'submission_id' => $progress->submission_id,
                ]);

                return null;
            }

            return (int) $submission->account_id;
        } catch (\Throwable $e) {
            \Log::error('[Notification] Error getting submitter', [
                'form_id' => $progress->form_id,
                'submission_id' => $progress->submission_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveCanonicalSubmission(WorkflowStepProgress $progress): ?FormSubmission
    {
        return FormSubmission::query()->find($progress->submission_id);
    }

    /**
     * Notify submitter when their request is successfully submitted
     */
    private function notifySubmitterOnSuccess(WorkflowStepProgress $progress): void
    {
        try {
            // Only notify on the very first step of the workflow
            $progress->loadMissing(['step', 'form', 'workflow']);

            $workflow = $progress->workflow;
            if (! $workflow) {
                return;
            }

            // Check if this is the first step group
            $firstGroup = WorkflowStep::where('workflow_id', $progress->workflow_id)
                ->where(function ($q) {
                    $q->whereHas('assignedUser')
                        ->orWhereHas('approvers');
                })
                ->min('step_group');

            // Only send notification once - on the first step
            if ($progress->step->step_group !== $firstGroup) {
                return;
            }

            $alreadyNotified = WorkflowStepProgress::query()
                ->where('workflow_id', $progress->workflow_id)
                ->where('submission_id', $progress->submission_id)
                ->where('id', '<', $progress->id)
                ->where('status', 'Pending')
                ->whereHas('step', fn ($q) => $q->where('step_group', $firstGroup))
                ->exists();

            if ($alreadyNotified) {
                return;
            }

            // Get the submitter
            $submitter = $this->getSubmitter($progress);
            if (! $submitter) {
                \Log::warning('[Notification] Could not find submitter for success notification', [
                    'form_id' => $progress->form_id,
                    'submission_id' => $progress->submission_id,
                ]);

                return;
            }

            $formName = $progress->form?->form_name ?? 'Unknown Form';

            $this->notificationService->send(
                [$submitter],
                'submission_created',
                [
                    'title' => 'Request Submitted Successfully',
                    'message' => "Your {$formName} request has been submitted and is now under review.",
                    'action_url' => route('student-dashboard.submission.view', [
                        'formId' => $progress->form_id,
                        'submissionId' => $progress->submission_id,
                    ]),
                    'action_text' => 'View Request',
                    'related_type' => 'submission',
                    'related_id' => $progress->submission_id,
                    'priority' => 'normal',
                    'triggered_by' => $submitter,
                ]
            );

        } catch (\Throwable $e) {
            \Log::error('[Notification] Failed to send submission success notification', [
                'progress_id' => $progress->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
