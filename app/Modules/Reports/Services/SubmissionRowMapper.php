<?php

namespace App\Modules\Reports\Services;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Support\Facades\DB;

class SubmissionRowMapper
{
    /**
     * Returns the single most-recent WorkflowStepProgress row per submission
     * using a ROW_NUMBER() window function.
     *
     * @param  array<int, int>  $submissionIds
     * @return array<int, WorkflowStepProgress>
     */
    public function latestProgressBySubmission(array $submissionIds): array
    {
        if (empty($submissionIds)) {
            return [];
        }

        /** @var array<int, WorkflowStepProgress> $map */
        $map = WorkflowStepProgress::fromSub(
            DB::table('tbl_workflow_step_progress')
                ->selectRaw(
                    'id, submission_id, status, action_taken, acted_at, updated_at,
                     ROW_NUMBER() OVER (
                         PARTITION BY submission_id
                         ORDER BY acted_at DESC, updated_at DESC, id DESC
                     ) AS rn'
                )
                ->whereIn('submission_id', $submissionIds),
            'ranked_wsp'
        )
            ->where('rn', 1)
            ->get([
                'id',
                'submission_id',
                'status',
                'action_taken',
                'acted_at',
                'updated_at',
            ])
            ->keyBy('submission_id')
            ->all();

        return $map;
    }

    /**
     * @param  array<int, int>  $submissionIds
     * @return array<int, Snapshot>
     */
    public function latestApprovedSnapshotBySubmission(array $submissionIds): array
    {
        if (empty($submissionIds)) {
            return [];
        }

        /** @var array<int, Snapshot> $map */
        $map = Snapshot::query()
            ->where('status', 'Approved')
            ->whereIn('submission_id', $submissionIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'submission_id',
                'public_id',
                'status',
                'approved_at',
                'comment',
            ])
            ->groupBy('submission_id')
            ->map(fn ($group) => $group->first())
            ->all();

        return $map;
    }

    /**
     * Transform a FormSubmission model into the array format used by the
     * frontend table and export writers.
     *
     * @return array<string, mixed>
     */
    public function mapSubmissionRow(
        FormSubmission $submission,
        Form $form,
        ?WorkflowStepProgress $latestProgress,
        ?Snapshot $snapshot,
    ): array {
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];
        $submitter = $submission->submitter;
        $submitterName = trim((string) ($submitter?->profile?->first_name ?? '').' '.(string) ($submitter?->profile?->last_name ?? ''));

        $attachments = $submission->attachments
            ->map(function (SubmissionAttachment $attachment): array {
                return [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'file_path' => $attachment->file_path,
                    'mime_type' => $attachment->mime_type,
                    'uploaded_by' => $attachment->uploaded_by,
                    'is_image' => str_starts_with((string) ($attachment->mime_type ?? ''), 'image/'),
                    'is_pdf' => $attachment->mime_type === 'application/pdf',
                ];
            })
            ->toArray();

        $row = [
            'id' => (int) $submission->id,
            'canonical_submission_id' => (int) $submission->id,
            'account_id' => (int) $submission->account_id,
            'username' => $submitter?->username,
            'email' => $submitter?->email,
            'submitter_name' => $submitterName !== '' ? $submitterName : ($submitter?->username ?? 'Unknown'),
            'submission_status' => $submission->submission_status,
            'workflow_status' => $latestProgress?->status ?? $submission->current_workflow_status ?? 'No Workflow',
            'workflow_action' => $latestProgress?->action_taken,
            'attachments' => $attachments,
            'attachment_count' => count($attachments),
            'snapshot' => $snapshot ? [
                'id' => $snapshot->id,
                'public_id' => $snapshot->public_id,
                'status' => $snapshot->status,
                'approved_at' => $snapshot->approved_at?->toDateTimeString(),
                'comment' => $snapshot->comment,
            ] : null,
            'created_at' => optional($submission->submitted_at ?? $submission->created_at)->toDateTimeString(),
        ];

        foreach ($form->fields as $field) {
            $fieldName = $field->field_name;
            $row[$fieldName] = $this->normalizeFieldValue($payload[$fieldName] ?? null, $field->data_type);
        }

        return $row;
    }

    /**
     * Flatten a mapped row into export-safe string values for the given columns.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, array{key: string, label: string, type: string}>  $columns
     * @return array<string, string>
     */
    public function normalizeExportRow(array $row, array $columns): array
    {
        $normalized = [];

        foreach ($columns as $column) {
            $key = $column['key'];
            $value = $row[$key] ?? null;

            if ($key === 'attachments') {
                $normalized[$key] = is_array($value)
                    ? implode(', ', array_filter(array_map(static function ($attachment): string {
                        if (! is_array($attachment)) {
                            return '';
                        }

                        return (string) ($attachment['original_name'] ?? '');
                    }, $value)))
                    : '';

                continue;
            }

            if ($key === 'snapshot') {
                if (! is_array($value) || empty($value)) {
                    $normalized[$key] = 'No Snapshot';

                    continue;
                }

                $normalized[$key] = sprintf('Available (%s)', (string) ($value['public_id'] ?? 'unknown'));

                continue;
            }

            if (is_array($value) || is_object($value)) {
                $normalized[$key] = implode(', ', $this->flattenStructuredValues($value));

                continue;
            }

            if (is_bool($value)) {
                $normalized[$key] = $value ? 'Yes' : 'No';

                continue;
            }

            $normalized[$key] = $value === null ? '' : (string) $value;
        }

        return $normalized;
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeFieldValue($value, string $dataType): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (in_array($dataType, ['checkbox', 'radio', 'select'], true)) {
            $decoded = $this->decodeJsonField($value);

            if (is_array($decoded)) {
                $displayValues = [];
                foreach ($decoded as $item) {
                    if (is_array($item) || is_object($item)) {
                        $item = (array) $item;
                        if (isset($item['text'])) {
                            $displayValues[] = $item['text'];
                        } elseif (isset($item['label'])) {
                            $displayValues[] = $item['label'];
                        } elseif (isset($item['value'])) {
                            $displayValues[] = $item['value'];
                        } else {
                            $normalizedItem = implode(', ', $this->flattenStructuredValues($item));
                            if ($normalizedItem !== '') {
                                $displayValues[] = $normalizedItem;
                            }
                        }
                    } elseif ($item !== null && $item !== '') {
                        $displayValues[] = (string) $item;
                    }
                }

                return implode(', ', array_filter($displayValues));
            }

            if (is_scalar($decoded)) {
                return (string) $decoded;
            }
        }

        if ($dataType === 'file' && is_string($value)) {
            return basename($value);
        }

        if ($dataType === 'number' && is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        }

        if (is_array($value) || is_object($value)) {
            return implode(', ', $this->flattenStructuredValues($value));
        }

        return (string) $value;
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function decodeJsonField($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        return $value;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function flattenStructuredValues($value): array
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (! is_array($value)) {
            $scalar = $this->stringifyScalar($value);

            return $scalar === '' ? [] : [$scalar];
        }

        $parts = [];

        foreach ($value as $key => $nestedValue) {
            if (is_array($nestedValue) || is_object($nestedValue)) {
                $nestedParts = $this->flattenStructuredValues($nestedValue);

                if (empty($nestedParts)) {
                    continue;
                }

                $joinedNested = implode(', ', $nestedParts);

                if (is_string($key) && $key !== '' && ! is_numeric($key) && $key !== 'meta') {
                    $parts[] = $key.': '.$joinedNested;

                    continue;
                }

                $parts[] = $joinedNested;

                continue;
            }

            $scalar = $this->stringifyScalar($nestedValue);

            if ($scalar === '') {
                continue;
            }

            if (is_string($key) && $key !== '' && ! is_numeric($key)) {
                $parts[] = $key.': '.$scalar;

                continue;
            }

            $parts[] = $scalar;
        }

        return $parts;
    }

    /**
     * @param  mixed  $value
     */
    private function stringifyScalar($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }
}
