<?php

namespace App\Jobs;

use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSubmissionNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 120, 300, 600, 1200];

    public int $timeout = 30;

    public function __construct(
        public readonly int $canonicalSubmissionId,
        public readonly string $eventType,
        public readonly ?int $workflowId = null,
        public readonly ?string $workflowType = null,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        if ($this->eventType !== 'submission_created' || ! $this->workflowId) {
            return;
        }

        $submission = FormSubmission::query()
            ->with('form')
            ->find($this->canonicalSubmissionId);

        if (! $submission || ! $submission->form) {
            Log::warning('[Submission Notifications Queue] Missing submission or form', [
                'canonical_submission_id' => $this->canonicalSubmissionId,
                'workflow_id' => $this->workflowId,
            ]);

            return;
        }

        $workflow = Workflow::query()->find($this->workflowId);
        if (! $workflow) {
            Log::warning('[Submission Notifications Queue] Missing workflow', [
                'canonical_submission_id' => $this->canonicalSubmissionId,
                'workflow_id' => $this->workflowId,
            ]);

            return;
        }

        $submissionId = $this->canonicalSubmissionId;

        if (strcasecmp((string) $this->workflowType, 'Parallel') === 0) {
            $notificationService->notifyAllParallelApprovers($workflow, $submissionId, $submission->form);

            return;
        }

        $notificationService->notifyFirstSequentialApprovers($workflow, $submissionId, $submission->form);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[Submission Notifications Queue] Notification dispatch failed permanently', [
            'canonical_submission_id' => $this->canonicalSubmissionId,
            'workflow_id' => $this->workflowId,
            'event_type' => $this->eventType,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}
