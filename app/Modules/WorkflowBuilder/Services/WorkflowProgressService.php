<?php

namespace App\Modules\WorkflowBuilder\Services;

use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use App\Modules\WorkflowBuilder\Support\WorkflowConditionEvaluator;

class WorkflowProgressService
{
    /**
     * Build initial workflow progress payload rows from a frozen workflow version snapshot.
     *
     * @param  array<string, mixed>  $submissionData
     * @return array<int, array<string, mixed>>
     */
    public function buildInitialProgress(WorkflowVersion $version, array $submissionData): array
    {
        $stepsSnapshot = $this->normalizeStepsSnapshot($version->steps_snapshot);
        if (empty($stepsSnapshot)) {
            return [];
        }

        usort($stepsSnapshot, function (array $a, array $b): int {
            $orderA = (int) ($a['step_order'] ?? 0);
            $orderB = (int) ($b['step_order'] ?? 0);
            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
        });

        $now = now();
        $payloads = [];

        foreach ($stepsSnapshot as $step) {
            $assignedAccountId = $step['assigned_account_id'] ?? null;
            $approvers = is_array($step['approvers'] ?? null) ? $step['approvers'] : [];

            if (empty($assignedAccountId) && empty($approvers)) {
                continue;
            }

            $actorId = $assignedAccountId ?: $this->resolveFirstApproverId($approvers);
            if (empty($actorId)) {
                continue;
            }

            $status = 'Waiting';
            $startedAt = null;

            if ((int) ($step['step_group'] ?? 0) === 1) {
                $status = 'Pending';
                $startedAt = $now;
            }

            if ($this->shouldSkipStep($step, $submissionData)) {
                $status = 'Skipped';
                $startedAt = $now;
            }

            $payloads[] = [
                'workflow_id' => (int) $version->workflow_id,
                'workflow_version_id' => (int) $version->id,
                'step_id' => $step['id'] ?? null,
                'actor_id' => (int) $actorId,
                'status' => $status,
                'started_at' => $startedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $payloads;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStepsSnapshot(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $approvers
     */
    private function resolveFirstApproverId(array $approvers): ?int
    {
        usort($approvers, fn (array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));

        $first = $approvers[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        $accountId = $first['account_id'] ?? null;

        return $accountId ? (int) $accountId : null;
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $submissionData
     */
    private function shouldSkipStep(array $step, array $submissionData): bool
    {
        $conditions = $step['step_conditions'] ?? [];
        if (is_string($conditions)) {
            $decoded = json_decode($conditions, true);
            $conditions = is_array($decoded) ? $decoded : [];
        }

        $watchFields = $conditions['watch_fields'] ?? [];
        $watchFields = is_array($watchFields) ? array_values(array_filter($watchFields)) : [];

        if (! empty($watchFields)) {
            foreach ($watchFields as $fieldName) {
                if (! is_string($fieldName) || ! array_key_exists($fieldName, $submissionData)) {
                    continue;
                }

                $value = $submissionData[$fieldName];
                if (is_string($value)) {
                    $decoded = $this->decodeJsonDeep($value);
                    if ($decoded['decoded']) {
                        $value = $decoded['value'];
                    }
                }

                if ($this->isNonEmpty($value)) {
                    return false;
                }
            }

            return true;
        }

        $branchCondition = isset($conditions['branch_condition']) && is_array($conditions['branch_condition'])
            ? $conditions['branch_condition']
            : null;

        if ($branchCondition !== null) {
            return ! WorkflowConditionEvaluator::evaluate($branchCondition, $submissionData);
        }

        return false;
    }

    /**
     * @return array{value:mixed,decoded:bool}
     */
    private function decodeJsonDeep(string $value): array
    {
        $current = $value;
        $decoded = false;

        for ($i = 0; $i < 6; $i++) {
            $trimmed = trim($current);
            if ($trimmed === '') {
                break;
            }

            $looksJsonLike = str_starts_with($trimmed, '[')
                || str_starts_with($trimmed, '{')
                || (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'));

            if (! $looksJsonLike) {
                break;
            }

            $next = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }

            $current = $next;
            $decoded = true;

            if (! is_string($current)) {
                break;
            }
        }

        return [
            'value' => $current,
            'decoded' => $decoded,
        ];
    }

    private function isNonEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, fn ($item) => ! in_array($item, [null, '', []], true))) > 0;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return ! is_null($value);
    }
}
