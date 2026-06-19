<?php

namespace App\Modules\VerificationSnapshot\Services;

use App\Modules\FormBuilder\Models\Facility;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Services\SnapshotStorageService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class SnapshotService
{
    protected SnapshotSecurityService $securityService;

    protected SnapshotStorageService $storageService;

    public function __construct(SnapshotSecurityService $securityService, SnapshotStorageService $storageService)
    {
        $this->securityService = $securityService;
        $this->storageService = $storageService;
    }

    /**
     * Create a snapshot from a progress id.
     * - Pull submission row from runtime table
     * - Map to labels (FormField order)
     * - Normalize files to public URLs
     * - Store all metadata in payload_json
     * - If $renderedHtml is provided, upload it to object storage and record the path.
     */
    public function createFromProgress(int $progressId, ?string $renderedHtml = null): Snapshot
    {
        $progress = WorkflowStepProgress::with([
            'step.workflow.form',
            'actor.profile',
            'canonicalSubmission.attachments',
            'canonicalSubmission.slots.facility',
        ])->findOrFail($progressId);
        $form = $progress->step->workflow->form()->with('fields')->first();

        $submission = $this->resolveCanonicalSubmission($progress, [
            'attachments',
            'slots.facility',
        ]);

        if (! $submission) {
            throw new \RuntimeException("Submission not found for progress {$progress->id}");
        }

        $payloadValues = is_array($submission->payload_json) ? $submission->payload_json : [];

        // Build ordered field list with labels/types
        $fields = [];
        foreach ($form->fields->sortBy('field_order') as $f) {
            $fieldName = (string) $f->field_name;
            $label = (string) $f->label;
            $type = strtolower((string) $f->data_type);

            $raw = $payloadValues[$fieldName] ?? null;

            if ($this->looksLikeSlotsField($type, $label, $fieldName) && empty($raw)) {
                $raw = $this->canonicalSlotsPayload($submission);
            }

            // Normalize/cast
            $val = $this->normalizeValue($raw, $type, $label, $fieldName);
            $isFile = ($type === 'file');

            $fields[] = [
                'name' => $fieldName,
                'label' => $label ?: $fieldName,
                'type' => $type ?: 'text',
                'value' => $val,
                'isFile' => $isFile,
                'field_options' => $f->field_options,
                'is_publicly_verifiable' => (bool) ($f->is_publicly_verifiable ?? true),
                'is_sensitive' => (bool) ($f->is_sensitive ?? false),
            ];
        }

        // Fetch generic submission attachments (not form field files)
        $attachments = $submission->attachments
            ->map(function ($att) {
                return [
                    'id' => $att->id,
                    'filename' => $att->original_name,
                    'path' => $att->file_path,
                    'mime_type' => $att->mime_type,
                    'uploaded_at' => optional($att->created_at)->toDateTimeString(),
                ];
            })
            ->toArray();

        // Build payload with attachments
        $approverName = $progress->actor?->full_name ?? $progress->actor?->username ?? '—';
        $approvedBy = $progress->actor_id ?? auth()->id();
        $approvedAt = $progress->acted_at ?? now();
        $status = $progress->status === 'Completed' ? ($progress->action_taken ?? 'Approved') : $progress->status;

        // Capture the full approval history at snapshot creation time so the
        // verification page never needs to query the live tbl_workflow_step_progress.
        $approvalHistory = WorkflowStepProgress::with(['step', 'actor.profile'])
            ->where('submission_id', $submission->id)
            ->where('workflow_id', $progress->workflow_id)
            ->orderBy('acted_at')
            ->get()
            ->map(fn ($p) => [
                'step' => $p->step?->step_name ?? '—',
                'status' => $p->status,
                'actor' => $p->actor?->full_name ?? $p->actor?->username ?? '—',
                'acted_at' => optional($p->acted_at)->toDateTimeString(),
                'comment' => $p->comments,
            ])
            ->all();

        // True when no step is still Pending or Waiting — i.e. the workflow is fully resolved.
        $isWorkflowComplete = ! WorkflowStepProgress::where('submission_id', $submission->id)
            ->where('workflow_id', $progress->workflow_id)
            ->whereIn('status', ['Pending', 'Waiting'])
            ->exists();

        // When the workflow is still in progress, show the name of the next pending step so
        // the verification page's "Current step" label reflects the actual active step rather
        // than the step that was just completed.
        $currentWorkflowStep = $progress->step?->step_name ?? 'Step';
        if (! $isWorkflowComplete) {
            $nextPending = WorkflowStepProgress::with('step')
                ->where('tbl_workflow_step_progress.submission_id', $submission->id)
                ->where('tbl_workflow_step_progress.workflow_id', $progress->workflow_id)
                ->where('tbl_workflow_step_progress.status', 'Pending')
                ->whereHas('step')
                ->join('tbl_workflow_step', 'tbl_workflow_step.id', '=', 'tbl_workflow_step_progress.step_id')
                ->orderBy('tbl_workflow_step.step_group')
                ->orderBy('tbl_workflow_step.step_order')
                ->select('tbl_workflow_step_progress.*')
                ->first();
            if ($nextPending?->step) {
                $currentWorkflowStep = $nextPending->step->step_name;
            }
        }

        $payload = [
            // Top-level status/step are explicit payload keys so the controller
            // reads them from here, not from the mutable DB columns.
            'status' => $status,
            'workflow_step' => $currentWorkflowStep,

            'form' => [
                'id' => $form->id,
                'code' => $form->form_code,
                'name' => $form->form_name,
                'version' => $form->version,
            ],
            'submission' => [
                'id' => (int) $submission->id,
                'created_at' => (string) optional($submission->submitted_at ?? $submission->created_at)->toDateTimeString(),
                'submitted_by_account_id' => (int) $submission->account_id,
            ],
            'approval' => [
                'status' => $status,
                'step' => $progress->step?->step_name ?? 'Step',
                'approved_by' => $approverName,
                'approved_at' => optional($approvedAt)->toDateTimeString(),
                'comment' => $progress->comments,
            ],
            'fields' => $fields,
            'attachments' => $attachments,

            // Frozen at creation time — covered by the HMAC.
            'approval_history' => $approvalHistory,
            'is_workflow_complete' => $isWorkflowComplete,
        ];

        // Generate action hash: actor_id + timestamp + payload
        $actionHash = $this->securityService->generateActionHash(
            $approvedBy,
            $approvedAt->timestamp,
            $payload
        );

        return $this->createOrGetSnapshot([
            'public_id' => Str::random(32),
            'submission_id' => (int) $submission->id,
            'form_id' => $form->id,
            'workflow_id' => $progress->workflow_id,
            'step_id' => $progress->step_id,
            'workflow_step' => $progress->step?->step_name ?? 'Step',
            'status' => $status,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedAt,
            'comment' => $progress->comments,
            'payload_json' => $payload,
            'action_hash' => $actionHash,
            'rendered_html_path' => null, // populated below when HTML is provided
            'locked' => true,
            'created_at' => now(),
        ], $renderedHtml);
    }

    public function createOrGetSnapshot(array $attributes, ?string $renderedHtml = null): Snapshot
    {
        $actionHash = (string) ($attributes['action_hash'] ?? '');
        if ($actionHash !== '') {
            $existing = Snapshot::query()->where('action_hash', $actionHash)->first();
            if ($existing) {
                return $existing;
            }
        }

        try {
            // Upload rendered HTML to object storage before inserting, so the
            // path is present in the immutable row from the start.
            if ($renderedHtml !== null) {
                $publicId = (string) ($attributes['public_id'] ?? \Str::random(32));
                $attributes['rendered_html_path'] = $this->storageService->store($publicId, $renderedHtml);
            }

            return Snapshot::create($attributes);
        } catch (QueryException $e) {
            if ($actionHash !== '' && $this->isDuplicateActionHashException($e)) {
                return Snapshot::query()->where('action_hash', $actionHash)->firstOrFail();
            }

            throw $e;
        }
    }

    private function isDuplicateActionHashException(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint');
    }

    /**
     * Convert DB values into final display values.
     * - Files -> public URL
     * - Checkbox arrays -> comma text already normalized in your submitters
     */
    private function normalizeValue(mixed $raw, string $type, string $label, string $fieldName): mixed
    {
        if ($raw === null) {
            return null;
        }

        // Files -> public URL
        if ($type === 'file') {
            $path = ltrim((string) $raw, '/');

            return '/storage/'.$path;
        }

        // Participants -> int (handles "3.00" => 3)
        $ln = strtolower($fieldName);
        $ll = strtolower($label);
        $looksParticipants =
            str_contains($ln, 'participants') ||
            str_contains($ll, 'participants');

        if ($looksParticipants && is_numeric($raw)) {
            return (int) round((float) $raw);
        }

        // Default: keep as-is (JSON strings kept for frontend formatting)
        return $raw;
    }

    /**
     * Heuristic to decide if a field represents the schedule/slots concept.
     * We use the runtime table "slots" JSON when the field itself is empty.
     */
    private function looksLikeSlotsField(string $type, string $label, string $fieldName): bool
    {
        $t = strtolower($type ?? '');
        $l = strtolower($label ?? '');
        $n = strtolower($fieldName ?? '');

        // Explicit date fields (we don't create a dedicated column; we store to slots)
        if ($t === 'date' || str_contains($n, 'date')) {
            return true;
        }

        // Common labels/keys you already handle elsewhere
        if (
            str_contains($l, 'slot') ||
            str_contains($n, 'slot') ||
            str_contains($l, 'schedule') ||
            str_contains($n, 'schedule') ||
            str_contains($l, 'date & venue') ||
            str_contains($l, 'date and venue') ||
            $n === 'slots'
        ) {
            return true;
        }

        return false;
    }

    private function resolveCanonicalSubmission(WorkflowStepProgress $progress, array $relations = []): ?FormSubmission
    {
        return FormSubmission::query()->with($relations)->find($progress->submission_id);
    }

    private function canonicalSlotsPayload(FormSubmission $submission): array
    {
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];
        if (isset($payload['slots']) && is_array($payload['slots'])) {
            return array_values($payload['slots']);
        }

        $facilityIds = $submission->slots->pluck('facility_id')->filter()->unique()->values()->all();
        $facilities = empty($facilityIds)
            ? []
            : Facility::query()->whereIn('id', $facilityIds)->pluck('name', 'id')->toArray();

        return $submission->slots->map(function ($slot) use ($facilities): array {
            return [
                'date' => optional($slot->date)->toDateString(),
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'facility_id' => $slot->facility_id,
                'facility_name' => $slot->facility?->name ?? ($facilities[$slot->facility_id] ?? null),
            ];
        })->values()->all();
    }
}
