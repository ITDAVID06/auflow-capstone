<?php

namespace App\Services\DemoSeeding;

use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Support\Collection;

class DemoSnapshotSeeder
{
    public function seed(bool $withEdge): int
    {
        $approvedSubmissions = FormSubmission::query()
            ->with(['form.fields', 'attachments', 'slots.facility'])
            ->whereRaw('LOWER(current_workflow_status) = ?', ['approved'])
            ->orderBy('id')
            ->get();

        $approvedProgressBySubmission = WorkflowStepProgress::query()
            ->with(['step', 'actor.profile'])
            ->whereIn('submission_id', $approvedSubmissions->pluck('id')->all())
            ->whereIn('status', ['Approved', 'Skipped'])
            ->orderByDesc('step_id')
            ->get()
            ->groupBy('submission_id')
            ->map(fn (Collection $progresses) => $progresses->first());

        $created = 0;

        foreach ($approvedSubmissions as $submission) {
            $finalProgress = $approvedProgressBySubmission->get($submission->id);

            if (! $finalProgress) {
                continue;
            }

            $actionHash = hash('sha256', "demo:snapshot:approved:{$submission->id}");

            Snapshot::query()->firstOrCreate(
                ['action_hash' => $actionHash],
                [
                    'public_id' => substr(hash('sha256', "demo:public:{$submission->id}:approved"), 0, 32),
                    'submission_id' => (int) $submission->id,
                    'form_id' => $submission->form_id,
                    'workflow_id' => $finalProgress->workflow_id,
                    'step_id' => $finalProgress->step_id,
                    'workflow_step' => $finalProgress->step?->step_name ?? 'Final Verification',
                    'status' => 'Approved',
                    'approved_by' => $finalProgress->actor_id,
                    'approved_at' => $finalProgress->acted_at ?? now(),
                    'comment' => $finalProgress->comments,
                    'payload_json' => $this->buildSnapshotPayload($submission, $finalProgress, 'approved'),
                    'locked' => true,
                    'created_at' => now(),
                ]
            );

            $created++;
        }

        if (! $withEdge) {
            return $created;
        }

        $rejectedSubmission = FormSubmission::query()
            ->with(['form.fields', 'attachments', 'slots.facility'])
            ->whereRaw('LOWER(current_workflow_status) = ?', ['rejected'])
            ->orderBy('id')
            ->first();

        if (! $rejectedSubmission) {
            return $created;
        }

        $progress = WorkflowStepProgress::query()
            ->with(['step', 'actor.profile'])
            ->where('submission_id', $rejectedSubmission->id)
            ->where('status', 'Rejected')
            ->orderByDesc('id')
            ->first();

        if (! $progress) {
            return $created;
        }

        $actionHash = hash('sha256', "demo:snapshot:rejected:{$rejectedSubmission->id}");

        Snapshot::query()->firstOrCreate(
            ['action_hash' => $actionHash],
            [
                'public_id' => substr(hash('sha256', "demo:public:{$rejectedSubmission->id}:rejected"), 0, 32),
                'submission_id' => (int) $rejectedSubmission->id,
                'form_id' => $rejectedSubmission->form_id,
                'workflow_id' => $progress->workflow_id,
                'step_id' => $progress->step_id,
                'workflow_step' => $progress->step?->step_name ?? 'Department Approval',
                'status' => 'Rejected',
                'approved_by' => $progress->actor_id,
                'approved_at' => $progress->acted_at ?? now(),
                'comment' => $progress->comments,
                'payload_json' => $this->buildSnapshotPayload($rejectedSubmission, $progress, 'rejected'),
                'locked' => true,
                'created_at' => now(),
            ]
        );

        return $created + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshotPayload(FormSubmission $submission, WorkflowStepProgress $progress, string $result): array
    {
        $submissionValues = is_array($submission->payload_json) ? $submission->payload_json : [];
        $form = $submission->form;
        $fields = [];

        if ($form) {
            foreach ($form->fields->sortBy('field_order') as $field) {
                $fieldName = (string) $field->field_name;
                $label = (string) ($field->label ?: $fieldName);
                $type = strtolower((string) ($field->data_type ?: 'text'));

                $value = $submissionValues[$fieldName] ?? null;
                if ($this->looksLikeSlotsField($type, $label, $fieldName) && empty($value)) {
                    $value = $this->slotsPayload($submission);
                }

                $fields[] = [
                    'name' => $fieldName,
                    'label' => $label,
                    'type' => $type,
                    'value' => $this->normalizeFieldValue($value, $type, $label, $fieldName),
                    'isFile' => $type === 'file',
                    'field_options' => $field->field_options,
                    'is_publicly_verifiable' => $field->is_publicly_verifiable ?? true,
                    'is_sensitive' => $field->is_sensitive ?? false,
                ];
            }
        }

        $attachments = $submission->attachments->map(function ($attachment): array {
            return [
                'id' => $attachment->id,
                'filename' => $attachment->original_name,
                'path' => $attachment->file_path,
                'mime_type' => $attachment->mime_type,
                'uploaded_at' => optional($attachment->created_at)->toDateTimeString(),
            ];
        })->values()->all();

        return [
            'source' => 'seed:demo',
            'result' => $result,
            'form' => [
                'id' => $form?->id,
                'code' => $form?->form_code,
                'name' => $form?->form_name ?? 'Request',
                'version' => $form?->version,
            ],
            'submission' => [
                'id' => (int) $submission->id,
                'created_at' => (string) optional($submission->submitted_at ?? $submission->created_at)->toDateTimeString(),
                'submitted_by_account_id' => (int) $submission->account_id,
            ],
            'approval' => [
                'status' => ucfirst($result),
                'step' => $progress->step?->step_name ?? 'Step',
                'approved_by' => $progress->actor?->full_name ?? $progress->actor?->username ?? 'Demo Approver',
                'approved_at' => optional($progress->acted_at ?? now())->toDateTimeString(),
                'comment' => $progress->comments,
            ],
            'fields' => $fields,
            'attachments' => $attachments,
        ];
    }

    private function normalizeFieldValue(mixed $value, string $type, string $label, string $fieldName): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($type === 'file') {
            return '/storage/'.ltrim((string) $value, '/');
        }

        $lowerName = strtolower($fieldName);
        $lowerLabel = strtolower($label);
        $isParticipantsField = str_contains($lowerName, 'participants') || str_contains($lowerLabel, 'participants');

        if ($isParticipantsField && is_numeric($value)) {
            return (int) round((float) $value);
        }

        return $value;
    }

    private function looksLikeSlotsField(string $type, string $label, string $fieldName): bool
    {
        $normalizedType = strtolower($type);
        $normalizedLabel = strtolower($label);
        $normalizedField = strtolower($fieldName);

        if ($normalizedType === 'date' || str_contains($normalizedField, 'date')) {
            return true;
        }

        return str_contains($normalizedLabel, 'slot')
            || str_contains($normalizedField, 'slot')
            || str_contains($normalizedLabel, 'schedule')
            || str_contains($normalizedField, 'schedule')
            || str_contains($normalizedLabel, 'date & venue')
            || str_contains($normalizedLabel, 'date and venue')
            || $normalizedField === 'slots';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slotsPayload(FormSubmission $submission): array
    {
        $submissionPayload = is_array($submission->payload_json) ? $submission->payload_json : [];
        if (isset($submissionPayload['slots']) && is_array($submissionPayload['slots'])) {
            return array_values($submissionPayload['slots']);
        }

        return $submission->slots->map(function ($slot): array {
            return [
                'date' => optional($slot->date)->toDateString(),
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'facility_id' => $slot->facility_id,
                'facility_name' => $slot->facility?->name,
            ];
        })->values()->all();
    }
}
