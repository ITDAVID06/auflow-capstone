<?php

namespace App\Modules\Reports\Requests\Concerns;

use App\Modules\FormBuilder\Models\FormField;
use App\Modules\Reports\Services\ReportQueryBuilderService;
use Illuminate\Validation\Validator;

trait ValidatesFilterState
{
    /**
     * Returns [$selectableColumns, $formFieldTypes] from a single DB query.
     *
     * Replaces the two separate resolveSelectableColumns() / resolveFormFieldTypes() calls
     * that previously each issued their own query.
     *
     * @return array{0: list<string>, 1: array<string, string>}
     */
    protected function resolveFormFieldData(int $formId): array
    {
        $systemColumns = [
            'id', 'account_id', 'username', 'email', 'submitter_name',
            'submission_status', 'workflow_status', 'workflow_action',
            'attachment_count', 'attachments', 'snapshot', 'created_at',
        ];

        $fields = FormField::query()
            ->where('form_id', $formId)
            ->whereNotNull('field_name')
            ->get(['field_name', 'data_type']);

        $fieldColumns = $fields
            ->filter(static fn ($f) => is_string($f->field_name) && $f->field_name !== '')
            ->pluck('field_name')
            ->values()
            ->all();

        $selectableColumns = array_values(array_unique([...$systemColumns, ...$fieldColumns]));

        $formFieldTypes = $fields
            ->filter(static fn ($f) => is_string($f->field_name) && $f->field_name !== '')
            ->mapWithKeys(static fn ($f) => [
                $f->field_name => is_string($f->data_type) ? strtolower($f->data_type) : 'text',
            ])
            ->all();

        return [$selectableColumns, $formFieldTypes];
    }

    /**
     * Returns true when filter_state.filters contains a group nested within another group
     * (exceeds the allowed max depth of: group → leaf).
     *
     * @param array<mixed> $filterState
     */
    protected function filterStateExceedsMaxDepth(array $filterState): bool
    {
        $filters = $filterState['filters'] ?? [];
        if (! is_array($filters)) {
            return false;
        }

        foreach ($filters as $item) {
            if (! is_array($item) || ! isset($item['logic'])) {
                continue;
            }
            foreach ($item['filters'] ?? [] as $child) {
                if (is_array($child) && isset($child['logic'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate filter_state.filters column names and operators against the form's allowed set.
     *
     * @param array<mixed> $filterState
     */
    protected function validateFilterStateContents(
        Validator $validator,
        array $filterState,
        int $formId,
        string $prefix = 'filter_state'
    ): void {
        $filters = $filterState['filters'] ?? null;
        if (! is_array($filters) || $filters === []) {
            return;
        }

        [, $formFieldTypes] = $this->resolveFormFieldData($formId);
        $queryBuilderService = app(ReportQueryBuilderService::class);
        $filterableColumns = $queryBuilderService->resolveFilterableColumns($formFieldTypes);

        foreach ($filters as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['logic'])) {
                foreach ($item['filters'] ?? [] as $leafIndex => $leaf) {
                    if (! is_array($leaf) || isset($leaf['logic'])) {
                        continue;
                    }
                    $this->validateLeafFilter(
                        $validator,
                        $leaf,
                        $prefix . '.filters.' . $index . '.filters.' . $leafIndex,
                        $filterableColumns,
                        $queryBuilderService,
                        $formFieldTypes
                    );
                }
                continue;
            }

            $this->validateLeafFilter(
                $validator,
                $item,
                $prefix . '.filters.' . $index,
                $filterableColumns,
                $queryBuilderService,
                $formFieldTypes
            );
        }
    }

    /**
     * @param array<mixed>        $filter
     * @param list<string>        $filterableColumns
     * @param array<string,string> $formFieldTypes
     */
    protected function validateLeafFilter(
        Validator $validator,
        array $filter,
        string $prefix,
        array $filterableColumns,
        ReportQueryBuilderService $queryBuilderService,
        array $formFieldTypes,
    ): void {
        $column   = $filter['column'] ?? null;
        $operator = $filter['operator'] ?? null;
        $value    = $filter['value'] ?? null;

        if (! is_string($column) || ! in_array($column, $filterableColumns, true)) {
            $validator->errors()->add($prefix . '.column', 'The filter column is not allowed for this form.');
            return;
        }

        if (! is_string($operator)) {
            return;
        }

        $allowedOperators = $queryBuilderService->resolveAllowedOperatorsForColumn($column, $formFieldTypes);

        if (! in_array($operator, $allowedOperators, true)) {
            $validator->errors()->add($prefix . '.operator', 'The operator is not supported for the selected column.');
            return;
        }

        if (! $queryBuilderService->operatorRequiresValue($operator)) {
            return;
        }

        if ($queryBuilderService->operatorRequiresArrayValue($operator)) {
            if (! is_array($value)) {
                $validator->errors()->add($prefix . '.value', 'The value must be an array for the selected operator.');
                return;
            }
            if ($operator === 'in' && $value === []) {
                $validator->errors()->add($prefix . '.value', 'The value must include at least one item for the selected operator.');
                return;
            }
            if ($operator === 'between' && count($value) !== 2) {
                $validator->errors()->add($prefix . '.value', 'The value must include exactly two items for the between operator.');
            }
            return;
        }

        if (is_array($value)) {
            $validator->errors()->add($prefix . '.value', 'The value must be a scalar for the selected operator.');
            return;
        }

        if ($value === null || (is_string($value) && trim($value) === '')) {
            $validator->errors()->add($prefix . '.value', 'The value is required for the selected operator.');
        }
    }
}
