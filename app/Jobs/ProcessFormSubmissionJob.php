<?php

namespace App\Jobs;

use App\Actions\FormBuilder\FindSubmissionByIdempotencyKeyAction;
use App\Actions\FormBuilder\RecordFailedSubmissionAction;
use App\Actions\FormBuilder\WriteCanonicalSubmissionAction;
use App\Exceptions\SlotUnavailableException;
use App\Exceptions\SubmissionLimitExceededException;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\Notifications\Services\NotificationService as InAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessFormSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schemaSnapshot
     * @param  array<int, array<string, mixed>>  $attachmentPayloads
     * @param  array<int, array<string, mixed>>  $slotPayloads
     * @param  array<int, array<string, mixed>>  $workflowProgressPayloads
     */
    public function __construct(
        public readonly int $formId,
        public readonly int $accountId,
        public readonly array $payload,
        public readonly array $schemaSnapshot,
        public readonly string $idempotencyKey,
        public readonly ?int $currentStepId,
        public readonly ?int $currentActorId,
        public readonly string $submittedAt,
        public readonly array $attachmentPayloads,
        public readonly array $slotPayloads,
        public readonly array $workflowProgressPayloads,
        public readonly ?int $workflowId,
        public readonly ?string $workflowType,
        public readonly ?string $pendingReference = null,
        public readonly ?int $workflowVersionId = null,
    ) {}

    public function handle(
        WriteCanonicalSubmissionAction $writeCanonicalSubmissionAction,
        FindSubmissionByIdempotencyKeyAction $findSubmissionByIdempotencyKeyAction,
    ): void {
        try {
            $existingSubmission = $findSubmissionByIdempotencyKeyAction->execute($this->idempotencyKey);

            if ($existingSubmission) {
                $this->dispatchNotifications((int) $existingSubmission->id);

                return;
            }

            [$payload, $attachmentPayloads] = $this->promoteTemporaryFiles();
            $submission = $writeCanonicalSubmissionAction->execute(
                form: Form::query()->findOrFail($this->formId),
                accountId: $this->accountId,
                payload: $payload,
                schemaSnapshot: $this->schemaSnapshot,
                idempotencyKey: $this->idempotencyKey,
                currentStepId: $this->currentStepId,
                currentActorId: $this->currentActorId,
                submittedAt: $this->submittedAt,
                attachmentPayloads: $attachmentPayloads,
                slotPayloads: $this->slotPayloads,
                workflowProgressPayloads: $this->workflowProgressPayloads,
                workflowVersionId: $this->workflowVersionId,
            );

            $this->dispatchNotifications((int) $submission->id);

            $this->cleanupTemporaryFiles();
        } catch (SlotUnavailableException $exception) {
            Log::warning('[Submission Queue] Slot unavailable during submission processing', [
                'form_id' => $this->formId,
                'account_id' => $this->accountId,
                'idempotency_key' => $this->idempotencyKey,
                'pending_reference' => $this->pendingReference,
                'error' => $exception->getMessage(),
            ]);

            $this->recordFailedSubmission('The facility slot you selected is no longer available. Please submit again with a different time.');
            $this->notifySubmissionFailure('The facility slot you selected is no longer available. Please submit again with a different time.');
            $this->cleanupTemporaryFiles();
        } catch (SubmissionLimitExceededException $exception) {
            Log::warning('[Submission Queue] Submission limit reached', [
                'form_id' => $this->formId,
                'account_id' => $this->accountId,
                'idempotency_key' => $this->idempotencyKey,
                'pending_reference' => $this->pendingReference,
            ]);

            $this->recordFailedSubmission('This form has reached its submission limit and is no longer accepting submissions.');
            $this->notifySubmissionFailure('This form has reached its submission limit and is no longer accepting submissions.');
            $this->cleanupTemporaryFiles();
        } catch (Throwable $exception) {
            Log::error('[Submission Queue] Submission processing failed', [
                'form_id' => $this->formId,
                'account_id' => $this->accountId,
                'idempotency_key' => $this->idempotencyKey,
                'pending_reference' => $this->pendingReference,
                'attempt' => $this->attempts(),
                'error' => $exception->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries || app()->runningUnitTests()) {
                throw $exception;
            }

            $this->release(30);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[Submission Queue] Submission processing failed permanently', [
            'form_id' => $this->formId,
            'account_id' => $this->accountId,
            'idempotency_key' => $this->idempotencyKey,
            'pending_reference' => $this->pendingReference,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        $this->recordFailedSubmission('Your submission could not be processed. Please try again or contact support.');
        $this->notifySubmissionFailure('Your submission could not be processed. Please try again or contact support.');
        $this->cleanupTemporaryFiles();
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function promoteTemporaryFiles(): array
    {
        $payload = $this->replaceTempPathsInPayload($this->payload);

        $attachmentPayloads = array_map(function (array $attachmentPayload): array {
            $attachmentPayload['file_path'] = $this->promoteTempPath(
                path: $attachmentPayload['file_path'] ?? null,
                targetDirectory: 'submissions_attachments'
            );

            return $attachmentPayload;
        }, $this->attachmentPayloads);

        if (isset($payload['attachments']) && is_array($payload['attachments'])) {
            $payload['attachments'] = array_map(function (array $attachmentPayload): array {
                return [
                    'original_name' => (string) ($attachmentPayload['original_name'] ?? ''),
                    'file_path' => (string) ($attachmentPayload['file_path'] ?? ''),
                    'mime_type' => (string) ($attachmentPayload['mime_type'] ?? ''),
                ];
            }, $attachmentPayloads);
        }

        return [$payload, $attachmentPayloads];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function replaceTempPathsInPayload(array $payload): array
    {
        array_walk_recursive($payload, function (&$value): void {
            if (! is_string($value)) {
                return;
            }

            if (! str_starts_with($value, 'submission-temp/submissions_uploads/')) {
                return;
            }

            $value = (string) $this->promoteTempPath($value, 'submissions_uploads');
        });

        return $payload;
    }

    private function promoteTempPath(?string $path, string $targetDirectory): ?string
    {
        if (! is_string($path) || $path === '') {
            return $path;
        }

        if (! str_starts_with($path, 'submission-temp/')) {
            return $path;
        }

        // All submission files (uploads and attachments) are stored on the default private disk.
        // Storing on the public disk would expose sensitive documents without authentication.
        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($path)) {
            return $path;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $filename = Str::uuid()->toString();
        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        $finalPath = trim($targetDirectory, '/').'/'.$filename;
        $disk->move($path, $finalPath);

        return $finalPath;
    }

    private function dispatchNotifications(int $canonicalSubmissionId): void
    {
        if (! $this->workflowId) {
            return;
        }

        SendSubmissionNotificationsJob::dispatch(
            canonicalSubmissionId: $canonicalSubmissionId,
            eventType: 'submission_created',
            workflowId: $this->workflowId,
            workflowType: $this->workflowType,
        );
    }

    private function recordFailedSubmission(string $failureReason): void
    {
        app(RecordFailedSubmissionAction::class)->execute(
            formId: $this->formId,
            accountId: $this->accountId,
            idempotencyKey: $this->idempotencyKey,
            payload: $this->payload,
            schemaSnapshot: $this->schemaSnapshot,
            submittedAt: $this->submittedAt,
            failureReason: $failureReason,
            pendingReference: $this->pendingReference,
        );
    }

    private function notifySubmissionFailure(string $message): void
    {
        app(InAppNotificationService::class)->send(
            recipientIds: $this->accountId,
            type: 'submission_processing_failed',
            data: [
                'title' => 'Submission Processing Failed',
                'message' => $message,
                'action_url' => null,
                'action_text' => null,
                'related_type' => 'submission',
                'related_id' => null,
                'icon' => 'x-circle',
                'priority' => 'high',
                'triggered_by' => null,
            ]
        );
    }

    private function cleanupTemporaryFiles(): void
    {
        $disk = Storage::disk(config('filesystems.default'));

        $paths = [];
        $payload = $this->payload;
        array_walk_recursive($payload, function ($value) use (&$paths): void {
            if (is_string($value) && str_starts_with($value, 'submission-temp/')) {
                $paths[] = $value;
            }
        });

        foreach ($this->attachmentPayloads as $attachmentPayload) {
            $path = $attachmentPayload['file_path'] ?? null;
            if (is_string($path) && str_starts_with($path, 'submission-temp/')) {
                $paths[] = $path;
            }
        }

        foreach (array_unique($paths) as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
