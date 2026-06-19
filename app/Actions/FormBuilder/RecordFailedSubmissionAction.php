<?php

namespace App\Actions\FormBuilder;

use App\Modules\FormBuilder\Models\FormSubmission;

class RecordFailedSubmissionAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schemaSnapshot
     */
    public function execute(
        int $formId,
        int $accountId,
        string $idempotencyKey,
        array $payload,
        array $schemaSnapshot,
        string $submittedAt,
        string $failureReason,
        ?string $pendingReference = null,
    ): FormSubmission {
        $existing = FormSubmission::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if (strtolower((string) $existing->submission_status) !== 'failed') {
                $existing->forceFill([
                    'submission_status' => 'failed',
                    'current_workflow_status' => 'failed',
                ])->save();
            }

            return $existing;
        }

        return FormSubmission::query()->create([
            'form_id' => $formId,
            'account_id' => $accountId,
            'idempotency_key' => $idempotencyKey,
            'submission_status' => 'failed',
            'current_workflow_status' => 'failed',
            'payload_json' => [
                ...$payload,
                '_processing_error' => $failureReason,
                '_pending_reference' => $pendingReference,
            ],
            'schema_snapshot_json' => $schemaSnapshot,
            'submitted_at' => $submittedAt,
            'is_latest_revision' => true,
        ]);
    }
}
