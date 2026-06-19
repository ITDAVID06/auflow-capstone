<?php

namespace App\Actions\FormBuilder;

use App\Exceptions\SlotUnavailableException;
use App\Exceptions\SubmissionLimitExceededException;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\Slot;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WriteCanonicalSubmissionAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schemaSnapshot
     * @param  array<int, array<string, mixed>>  $attachmentPayloads
     * @param  array<int, array<string, mixed>>  $slotPayloads
     * @param  array<int, array<string, mixed>>  $workflowProgressPayloads
     */
    public function execute(
        Form $form,
        int $accountId,
        array $payload,
        array $schemaSnapshot,
        ?string $idempotencyKey = null,
        ?int $revisionOf = null,
        ?int $rootSubmissionId = null,
        string $submissionStatus = 'Pending',
        string $currentWorkflowStatus = 'Pending',
        ?int $currentStepId = null,
        ?int $currentActorId = null,
        CarbonInterface|string|null $submittedAt = null,
        array $attachmentPayloads = [],
        array $slotPayloads = [],
        array $workflowProgressPayloads = [],
        ?int $workflowVersionId = null,
    ): FormSubmission {
        return DB::transaction(function () use (
            $form,
            $accountId,
            $payload,
            $schemaSnapshot,
            $idempotencyKey,
            $revisionOf,
            $rootSubmissionId,
            $submissionStatus,
            $currentWorkflowStatus,
            $currentStepId,
            $currentActorId,
            $submittedAt,
            $attachmentPayloads,
            $slotPayloads,
            $workflowProgressPayloads,
            $workflowVersionId,
        ) {
            if (is_string($idempotencyKey) && $idempotencyKey !== '') {
                $existingSubmission = FormSubmission::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingSubmission) {
                    return $existingSubmission->fresh([
                        'attachments',
                        'slots',
                        'workflowProgressEntries',
                    ]);
                }
            }

            $parentSubmission = $revisionOf ? FormSubmission::query()->findOrFail($revisionOf) : null;
            $resolvedRootSubmissionId = $rootSubmissionId
                ?? $parentSubmission?->root_submission_id
                ?? $parentSubmission?->id;

            // Atomic submission-limit enforcement: count with row lock so concurrent
            // requests cannot both slip through a pre-check race condition.
            $limit = (int) ($form->submission_limit ?? 0);
            if ($limit > 0) {
                $currentCount = FormSubmission::query()
                    ->where('form_id', $form->id)
                    ->lockForUpdate()
                    ->count();

                if ($currentCount >= $limit) {
                    throw new SubmissionLimitExceededException(
                        'This form has reached its submission limit.'
                    );
                }
            }

            if ($parentSubmission) {
                $parentSubmission->forceFill(['is_latest_revision' => false])->save();
            }

            $submission = FormSubmission::query()->create([
                'form_id' => $form->id,
                'account_id' => $accountId,
                'workflow_version_id' => $workflowVersionId,
                'idempotency_key' => $idempotencyKey,
                'submission_status' => $submissionStatus,
                'current_workflow_status' => $currentWorkflowStatus,
                'current_step_id' => $currentStepId,
                'current_actor_id' => $currentActorId,
                'payload_json' => $payload,
                'schema_snapshot_json' => $schemaSnapshot,
                'submitted_at' => $this->normalizeSubmittedAt($submittedAt),
                'revision_of' => $revisionOf,
                'root_submission_id' => $resolvedRootSubmissionId,
                'is_latest_revision' => true,
            ]);

            if (! $submission->root_submission_id) {
                $submission->forceFill(['root_submission_id' => $submission->id])->save();
            }

            $this->persistAttachments($submission, $attachmentPayloads);
            $this->persistSlots($submission, $slotPayloads);
            $this->persistWorkflowProgressEntries($submission, $workflowProgressPayloads);

            return $submission->fresh([
                'attachments',
                'slots',
                'workflowProgressEntries',
            ]);
        });
    }

    private function normalizeSubmittedAt(CarbonInterface|string|null $submittedAt): CarbonInterface
    {
        if ($submittedAt instanceof CarbonInterface) {
            return $submittedAt;
        }

        if (is_string($submittedAt)) {
            return Carbon::parse($submittedAt);
        }

        return now();
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachmentPayloads
     */
    private function persistAttachments(FormSubmission $submission, array $attachmentPayloads): void
    {
        foreach ($attachmentPayloads as $attachmentPayload) {
            // Strip legacy columns that may have been removed from the table.
            $validColumns = ['file_path', 'original_name', 'mime_type', 'uploaded_by'];
            $attributes = array_intersect_key($attachmentPayload, array_flip($validColumns));
            $attributes['submission_id'] = $submission->id;

            if (Schema::hasColumn('tbl_submission_attachment', 'field_name')) {
                $attributes['field_name'] = $attachmentPayload['field_name'] ?? 'attachment';
            }

            if (Schema::hasColumn('tbl_submission_attachment', 'stored_path')) {
                $attributes['stored_path'] = $attachmentPayload['stored_path'] ?? ($attachmentPayload['file_path'] ?? null);
            }

            $attachment = new SubmissionAttachment;
            $attachment->forceFill($attributes)->save();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $slotPayloads
     */
    private function persistSlots(FormSubmission $submission, array $slotPayloads): void
    {
        foreach ($slotPayloads as $slotPayload) {
            $this->assertSlotStillAvailable($slotPayload);

            Slot::query()->create([
                'form_id' => $submission->form_id,
                'account_id' => $submission->account_id,
                'status' => 'Pending',
                ...$slotPayload,
                'submission_id' => $submission->id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $slotPayload
     */
    private function assertSlotStillAvailable(array $slotPayload): void
    {
        $facilityId = $slotPayload['facility_id'] ?? null;
        $date = $slotPayload['date'] ?? null;
        $startTime = $slotPayload['start_time'] ?? null;
        $endTime = $slotPayload['end_time'] ?? null;

        if (! $facilityId || ! $date || ! $startTime || ! $endTime) {
            return;
        }

        $conflictExists = Slot::query()
            ->where('facility_id', $facilityId)
            ->where('date', $date)
            ->where('status', '!=', 'Rejected')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->lockForUpdate()
            ->exists();

        if ($conflictExists) {
            throw new SlotUnavailableException('The facility slot you selected is no longer available.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $workflowProgressPayloads
     */
    private function persistWorkflowProgressEntries(FormSubmission $submission, array $workflowProgressPayloads): void
    {
        foreach ($workflowProgressPayloads as $progressPayload) {
            WorkflowStepProgress::query()->create([
                'form_id' => $submission->form_id,
                ...$progressPayload,
                'submission_id' => $submission->id,
            ]);
        }
    }
}
