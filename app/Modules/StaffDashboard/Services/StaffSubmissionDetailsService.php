<?php

namespace App\Modules\StaffDashboard\Services;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\Slot;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;

class StaffSubmissionDetailsService
{
    public function __construct(protected StaffStepReadinessService $readinessService) {}

    public function getSubmissionDetailsForStaff(int $progressId, int $staffId): array
    {
        $progress = WorkflowStepProgress::with('step.workflow.form')->findOrFail($progressId);
        $step = $progress->step;

        $isApprover = (int) $step->assigned_account_id === $staffId
            || $step->approvers()->where('account_id', $staffId)->exists();

        if (! $isApprover) {
            abort(403, 'You are not authorized to view this submission.');
        }

        $canAct = $this->readinessService->isStepReady($step, $progress->submission_id);

        $form = $step->workflow->form()->with('fields')->first();
        $submission = $this->resolveCanonicalSubmission($progress);

        if (! $submission) {
            abort(404, 'Submission not found.');
        }

        $history = $this->buildHistory($submission, $progress->workflow_id, $staffId);
        $isLatest = (bool) $submission->is_latest_revision;
        $submitterName = $this->resolveSubmitterName($submission);

        $workflowStepsCollection = WorkflowStepProgress::query()
            ->with(['step.assignedUser.profile', 'actor.profile', 'commentAttachments.uploader.profile'])
            ->join('tbl_workflow_step as ws', 'ws.id', '=', 'tbl_workflow_step_progress.step_id')
            ->where('tbl_workflow_step_progress.submission_id', $submission->id)
            ->where('tbl_workflow_step_progress.workflow_id', $progress->workflow_id)
            ->orderBy('ws.step_group')
            ->orderBy('ws.step_order')
            ->select('tbl_workflow_step_progress.*')
            ->get();

        $workflowSteps = $workflowStepsCollection
            ->map(function ($w) {
                $attachments = $w->commentAttachments->map(function ($a) {
                    $uploaderName = trim((string) ($a->uploader?->profile?->first_name ?? '').' '.(string) ($a->uploader?->profile?->last_name ?? ''));
                    if ($uploaderName === '') {
                        $uploaderName = $a->uploader?->username ?? 'Unknown';
                    }

                    return [
                        'id' => $a->id,
                        'original_name' => $a->original_name,
                        'mime_type' => $a->mime_type,
                        'size_bytes' => $a->size_bytes,
                        'uploaded_by_id' => $a->uploaded_by,
                        'uploaded_by_name' => $uploaderName,
                        'uploaded_at' => optional($a->created_at)?->toIso8601String(),
                        'download_url' => route('staff-dashboard.progress-attachments.download', $a->id),
                        'preview_url' => route('staff-dashboard.progress-attachments.preview', $a->id),

                    ];
                })->values()->all();

                return [
                    'step' => $w->step->step_name ?? '-',
                    'status' => $w->status ?? 'Pending',
                    'actor' => $w->actor?->full_name ?? $w->step?->assignedUser?->full_name ?? '—',
                    'acted_at' => optional($w->acted_at)?->toIso8601String(),
                    'comments' => $w->comments ?? null,
                    'duration' => $w->duration_seconds,
                    'duration_human' => $w->duration_seconds
                        ? CarbonInterval::seconds($w->duration_seconds)->cascade()->forHumans()
                        : null,
                    'attachments' => $attachments,
                ];
            })
            ->toArray();

        $totalDurationSeconds = collect($workflowSteps)->sum(fn ($w) => $w['duration'] ?? 0);
        $totalDurationHuman = $totalDurationSeconds > 0
            ? CarbonInterval::seconds($totalDurationSeconds)->cascade()->forHumans()
            : null;

        $fieldsWithLabels = $this->buildSubmissionFields($submission, $form);
        $attachments = $submission->attachments;
        $slots = $this->extractSlots($submission);
        $dateRanges = $this->extractDateRanges($submission);

        $snapshot = Snapshot::query()
            ->where('submission_id', $submission->id)
            ->orderByDesc('id')
            ->first();

        $snapshotData = $snapshot ? [
            'exists' => true,
            'public_id' => $snapshot->public_id,
            'short_code' => substr($snapshot->public_id, -6),
            'status' => $snapshot->status,
            'approved_at' => optional($snapshot->approved_at)?->toIso8601String(),
            'url' => route('snapshots.show', $snapshot->public_id),
        ] : ['exists' => false];

        return [
            'id' => $progressId,
            'progress_id' => $progressId,
            'submission_id' => (int) $submission->id,
            'form_id' => $form->id,
            'form_code' => $form->form_code ?? '-',
            'form_name' => $form->form_name,
            'created_at' => optional($submission->submitted_at ?? $submission->created_at)?->toIso8601String(),
            'updated_at' => optional($submission->updated_at ?? $submission->submitted_at ?? $submission->created_at)?->toIso8601String(),
            'can_act' => $canAct,
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
            'fields' => $fieldsWithLabels,
            'attachments' => $attachments,
            'slots' => $slots,
            'date_ranges' => $dateRanges,
            'snapshot' => $snapshotData,
            'workflow' => $workflowSteps,
            'workflow_duration' => [
                'total_seconds' => $totalDurationSeconds,
                'total_human' => $totalDurationHuman,
            ],
            'submitter' => $submitterName,
            'can_review' => $isLatest && $progress->status === 'Pending' && $isApprover,
            'is_latest' => $isLatest,
            'history' => $history,
        ];
    }

    private function resolveCanonicalSubmission(WorkflowStepProgress $progress): ?FormSubmission
    {
        return FormSubmission::query()
            ->with(['attachments', 'slots.facility', 'submitter.profile', 'parentRevision'])
            ->find($progress->submission_id);
    }

    private function resolveSubmitterName(FormSubmission $submission): string
    {
        $submitter = $submission->submitter;
        $name = trim((string) ($submitter?->profile?->first_name ?? '').' '.(string) ($submitter?->profile?->last_name ?? ''));

        return $name !== '' ? $name : ($submitter?->username ?? 'Unknown');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSubmissionFields(FormSubmission $submission, Form $form): array
    {
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];

        return collect($this->schemaFieldsForSubmission($submission, $form))
            ->map(fn (array $field): array => [
                'field_name' => $field['field_name'],
                'label' => $field['label'] ?? $field['field_name'],
                'value' => $payload[$field['field_name']] ?? null,
                'type' => strtolower((string) ($field['data_type'] ?? 'text')),
                'field_options' => $field['field_options'] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function schemaFieldsForSubmission(FormSubmission $submission, Form $form): array
    {
        $schemaSnapshot = is_array($submission->schema_snapshot_json) ? $submission->schema_snapshot_json : [];
        $fields = $schemaSnapshot['fields'] ?? null;

        if (is_array($fields) && $fields !== []) {
            return array_values($fields);
        }

        $formFields = $form->toSchemaArray()['fields'] ?? [];

        if ($formFields instanceof Collection) {
            return $formFields->values()->all();
        }

        return is_array($formFields) ? $formFields : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildHistory(FormSubmission $submission, int $workflowId, int $staffId): array
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

        $progressRecordsByCanonical = WorkflowStepProgress::query()
            ->where('workflow_id', $workflowId)
            ->whereIn('submission_id', $chain->pluck('id'))
            ->whereHas('step', function ($q) use ($staffId) {
                $q->where('assigned_account_id', $staffId)
                    ->orWhereHas('approvers', fn ($aq) => $aq->where('account_id', $staffId));
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('submission_id')
            ->map(fn ($group) => $group->first());

        $latestStatuses = WorkflowStepProgress::query()
            ->where('workflow_id', $workflowId)
            ->whereIn('submission_id', $chain->pluck('id'))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('submission_id')
            ->map(fn ($group) => $group->first()?->status ?? 'Pending');

        return $chain->values()->map(function (FormSubmission $row, int $index) use ($latestStatuses, $progressRecordsByCanonical): array {
            $progressRecord = $progressRecordsByCanonical->get($row->id);

            return [
                'id' => (int) $row->id,
                'progress_id' => $progressRecord?->id,
                'version' => $index + 1,
                'created_at' => optional($row->submitted_at ?? $row->created_at)?->toIso8601String(),
                'updated_at' => optional($row->updated_at ?? $row->submitted_at ?? $row->created_at)?->toIso8601String(),
                'status' => $progressRecord?->status ?? ($latestStatuses->get($row->id) ?? 'Pending'),
                'latest_status' => $latestStatuses->get($row->id) ?? 'Pending',
                'is_latest' => (bool) $row->is_latest_revision,
            ];
        })->all();
    }
}
