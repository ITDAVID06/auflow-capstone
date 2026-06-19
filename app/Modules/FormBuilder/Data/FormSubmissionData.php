<?php

namespace App\Modules\FormBuilder\Data;

use App\Modules\FormBuilder\Models\FormSubmission;
use Carbon\CarbonInterface;

class FormSubmissionData
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schemaSnapshot
     */
    public function __construct(
        public int $id,
        public int $formId,
        public int $accountId,
        public string $submissionStatus,
        public string $currentWorkflowStatus,
        public array $payload,
        public array $schemaSnapshot,
        public CarbonInterface $submittedAt,
        public ?int $revisionOf,
        public ?int $rootSubmissionId,
        public bool $isLatestRevision,
    ) {}

    public static function fromModel(FormSubmission $submission): self
    {
        return new self(
            id: (int) $submission->id,
            formId: (int) $submission->form_id,
            accountId: (int) $submission->account_id,
            submissionStatus: (string) $submission->submission_status,
            currentWorkflowStatus: (string) $submission->current_workflow_status,
            payload: (array) ($submission->payload_json ?? []),
            schemaSnapshot: (array) ($submission->schema_snapshot_json ?? []),
            submittedAt: $submission->submitted_at,
            revisionOf: $submission->revision_of ? (int) $submission->revision_of : null,
            rootSubmissionId: $submission->root_submission_id ? (int) $submission->root_submission_id : null,
            isLatestRevision: (bool) $submission->is_latest_revision,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->formId,
            'account_id' => $this->accountId,
            'submission_status' => $this->submissionStatus,
            'current_workflow_status' => $this->currentWorkflowStatus,
            'payload_json' => $this->payload,
            'schema_snapshot_json' => $this->schemaSnapshot,
            'submitted_at' => $this->submittedAt->toDateTimeString(),
            'revision_of' => $this->revisionOf,
            'root_submission_id' => $this->rootSubmissionId,
            'is_latest_revision' => $this->isLatestRevision,
        ];
    }
}
