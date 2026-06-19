<?php

namespace App\Modules\Reports\Services;

use Illuminate\Database\Eloquent\Builder;

class ReportQueryBuilderService
{
    /**
     * Operators currently representable by the reports builder UI payload.
     *
     * @var array<int, string>
     */
    private const UI_COMPATIBLE_OPERATORS = [
        'eq',
        'neq',
        'contains',
        'starts_with',
        'ends_with',
        'gt',
        'gte',
        'lt',
        'lte',
        'is_null',
        'is_not_null',
    ];

    /**
     * Apply a nested filter node to the query.
     *
     * Accepts either:
     *  - A group node: `['logic' => 'and'|'or', 'filters' => [...]]`
     *  - A flat array of filter clauses (backward compatible — treated as AND root)
     *
     * @param  array<mixed>  $filterNode
     */
    public function applyNestedFilters(Builder $query, array $filterNode, array $formFieldTypes = []): void
    {
        // Backward compat: flat sequential array → AND root; also handles mixed leaf/group lists
        if (array_is_list($filterNode)) {
            foreach ($filterNode as $child) {
                if (is_array($child)) {
                    $this->applyFilterChild($query, $child, $formFieldTypes, 'and');
                }
            }

            return;
        }

        $logic = strtolower((string) ($filterNode['logic'] ?? 'and'));
        $children = $filterNode['filters'] ?? [];

        if (! is_array($children) || $children === []) {
            return;
        }

        if ($logic === 'and') {
            // AND root: apply each child directly to the outer query
            foreach ($children as $child) {
                if (is_array($child)) {
                    $this->applyFilterChild($query, $child, $formFieldTypes, 'and');
                }
            }
        } else {
            // OR root: wrap everything in a single WHERE ( ... ) group
            $query->where(function (Builder $q) use ($children, $formFieldTypes): void {
                foreach ($children as $child) {
                    if (is_array($child)) {
                        $this->applyFilterChild($q, $child, $formFieldTypes, 'or');
                    }
                }
            });
        }
    }

    /**
     * @param  array<int, mixed>  $filters
     */
    public function applyBuilderFilters(Builder $query, array $filters, array $formFieldTypes = []): void
    {
        $filterableColumns = $this->resolveFilterableColumns($formFieldTypes);

        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $column = $filter['column'] ?? null;
            $operator = $filter['operator'] ?? null;
            $value = $filter['value'] ?? null;

            if (! is_string($column) || ! is_string($operator)) {
                continue;
            }

            if (! in_array($column, $filterableColumns, true)) {
                continue;
            }

            $allowedOperators = $this->resolveAllowedOperatorsForColumn($column, $formFieldTypes);

            if (! in_array($operator, $allowedOperators, true)) {
                continue;
            }

            $queryColumn = $this->resolveQueryColumn($column, $formFieldTypes);

            if ($queryColumn === null) {
                continue;
            }

            $this->applyOperator($query, $queryColumn, $operator, $value);
        }
    }

    /**
     * @param  mixed  $sort
     */
    public function applyBuilderSort(Builder $query, $sort, array $formFieldTypes = []): bool
    {
        if (! is_array($sort)) {
            return false;
        }

        $column = $sort['column'] ?? null;
        $direction = strtolower((string) ($sort['direction'] ?? ''));

        if (! is_string($column) || ! in_array($direction, ['asc', 'desc'], true)) {
            return false;
        }

        if (! in_array($column, $this->resolveSortableColumns($formFieldTypes), true)) {
            return false;
        }

        $queryColumn = $this->resolveQueryColumn($column, $formFieldTypes);

        if ($queryColumn === null) {
            return false;
        }

        $query->orderBy($queryColumn, $direction);

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $selectedKeys
     * @return array<int, array<string, mixed>>
     */
    public function projectRows(array $rows, array $selectedKeys): array
    {
        if ($selectedKeys === []) {
            return $rows;
        }

        $projectedRows = [];

        foreach ($rows as $row) {
            $projected = [];

            foreach ($selectedKeys as $selectedKey) {
                if (! array_key_exists($selectedKey, $row)) {
                    continue;
                }

                $projected[$selectedKey] = $row[$selectedKey];
            }

            $projectedRows[] = $projected;
        }

        return $projectedRows;
    }

    /**
     * @param  array<string, string>  $formFieldTypes
     * @return array<int, string>
     */
    public function resolveFilterableColumns(array $formFieldTypes): array
    {
        $systemColumns = [
            'id',
            'account_id',
            'submission_status',
            'workflow_status',
            'created_at',
        ];

        $formFieldColumns = array_keys($formFieldTypes);

        return array_values(array_unique([...$systemColumns, ...$formFieldColumns]));
    }

    /**
     * @param  array<string, string>  $formFieldTypes
     * @return array<int, string>
     */
    public function resolveSortableColumns(array $formFieldTypes): array
    {
        return $this->resolveFilterableColumns($formFieldTypes);
    }

    /**
     * @param  array<string, string>  $formFieldTypes
     * @return array<int, string>
     */
    public function resolveAllowedOperatorsForColumn(string $column, array $formFieldTypes): array
    {
        if (in_array($column, ['submission_status', 'workflow_status'], true)) {
            return [...self::UI_COMPATIBLE_OPERATORS, 'in'];
        }

        if (in_array($column, ['id', 'account_id'], true)) {
            return [...self::UI_COMPATIBLE_OPERATORS, 'between', 'in'];
        }

        if ($column === 'created_at') {
            return [...self::UI_COMPATIBLE_OPERATORS, 'between'];
        }

        $fieldType = $formFieldTypes[$column] ?? null;

        if (in_array($fieldType, ['number', 'integer', 'decimal'], true)) {
            return [...self::UI_COMPATIBLE_OPERATORS, 'between', 'in'];
        }

        if (in_array($fieldType, ['date', 'datetime'], true)) {
            return [...self::UI_COMPATIBLE_OPERATORS, 'between'];
        }

        return [...self::UI_COMPATIBLE_OPERATORS, 'in'];
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string}>  $allColumns
     * @param  array<string, string>  $formFieldTypes
     * @return array{filterable_columns: array<int, array{key: string, label: string, type: string}>, sortable_columns: array<int, array{key: string, label: string, type: string}>, operators_by_column: array<string, array<int, string>>}
     */
    public function resolveBuilderCapabilities(array $allColumns, array $formFieldTypes): array
    {
        $columnMap = [];

        foreach ($allColumns as $column) {
            $columnMap[$column['key']] = $column;
        }

        $filterableKeys = array_values(array_filter(
            $this->resolveFilterableColumns($formFieldTypes),
            static fn (string $key) => isset($columnMap[$key])
        ));

        $sortableKeys = array_values(array_filter(
            $this->resolveSortableColumns($formFieldTypes),
            static fn (string $key) => isset($columnMap[$key])
        ));

        $filterableColumns = array_map(
            static fn (string $key) => $columnMap[$key],
            $filterableKeys
        );

        $sortableColumns = array_map(
            static fn (string $key) => $columnMap[$key],
            $sortableKeys
        );

        $operatorsByColumn = [];
        foreach ($filterableKeys as $key) {
            $operatorsByColumn[$key] = array_values(array_intersect(
                $this->resolveAllowedOperatorsForColumn($key, $formFieldTypes),
                self::UI_COMPATIBLE_OPERATORS
            ));
        }

        return [
            'filterable_columns' => $filterableColumns,
            'sortable_columns' => $sortableColumns,
            'operators_by_column' => $operatorsByColumn,
        ];
    }

    public function operatorRequiresValue(string $operator): bool
    {
        return ! in_array($operator, ['is_null', 'is_not_null'], true);
    }

    public function operatorRequiresArrayValue(string $operator): bool
    {
        return in_array($operator, ['in', 'between'], true);
    }

    /**
     * Apply a single filter child (leaf or sub-group) using the given parent logic.
     *
     * @param  array<mixed>  $child
     * @param  array<string, string>  $formFieldTypes
     */
    private function applyFilterChild(Builder $query, array $child, array $formFieldTypes, string $parentLogic): void
    {
        if (isset($child['logic'])) {
            // Sub-group: wrap in a nested WHERE clause
            $subLogic = strtolower((string) ($child['logic'] ?? 'and'));
            $subFilters = $child['filters'] ?? [];

            if (! is_array($subFilters) || $subFilters === []) {
                return;
            }

            $wrapMethod = $parentLogic === 'or' ? 'orWhere' : 'where';

            $query->{$wrapMethod}(function (Builder $q) use ($subFilters, $subLogic, $formFieldTypes): void {
                foreach ($subFilters as $leaf) {
                    if (is_array($leaf)) {
                        $this->applyFilterChild($q, $leaf, $formFieldTypes, $subLogic);
                    }
                }
            });

            return;
        }

        // Leaf filter
        $this->applyLeafFilter($query, $child, $formFieldTypes, $parentLogic);
    }

    /**
     * Apply a single leaf filter clause to the query.
     *
     * @param  array<mixed>  $filter
     * @param  array<string, string>  $formFieldTypes
     */
    private function applyLeafFilter(Builder $query, array $filter, array $formFieldTypes, string $parentLogic): void
    {
        $column = $filter['column'] ?? null;
        $operator = $filter['operator'] ?? null;
        $value = $filter['value'] ?? null;

        if (! is_string($column) || ! is_string($operator)) {
            return;
        }

        $filterableColumns = $this->resolveFilterableColumns($formFieldTypes);

        if (! in_array($column, $filterableColumns, true)) {
            return;
        }

        $allowedOperators = $this->resolveAllowedOperatorsForColumn($column, $formFieldTypes);

        if (! in_array($operator, $allowedOperators, true)) {
            return;
        }

        $queryColumn = $this->resolveQueryColumn($column, $formFieldTypes);

        if ($queryColumn === null) {
            return;
        }

        if ($parentLogic === 'or') {
            $query->orWhere(function (Builder $q) use ($queryColumn, $operator, $value): void {
                $this->applyOperator($q, $queryColumn, $operator, $value);
            });
        } else {
            $this->applyOperator($query, $queryColumn, $operator, $value);
        }
    }

    /**
     * @param  array<string, string>  $formFieldTypes
     */
    private function resolveQueryColumn(string $column, array $formFieldTypes): ?string
    {
        return match ($column) {
            'id', 'account_id', 'submission_status' => $column,
            'workflow_status' => 'current_workflow_status',
            'created_at' => 'submitted_at',
            default => array_key_exists($column, $formFieldTypes) ? "payload_json->{$column}" : null,
        };
    }

    /**
     * @param  mixed  $value
     */
    private function applyOperator(Builder $query, string $column, string $operator, $value): void
    {
        if ($operator === 'eq') {
            $query->where($column, $value);

            return;
        }

        if ($operator === 'neq') {
            $query->where($column, '!=', $value);

            return;
        }

        if ($operator === 'in') {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            }

            return;
        }

        if ($operator === 'is_null') {
            $query->whereNull($column);

            return;
        }

        if ($operator === 'is_not_null') {
            $query->whereNotNull($column);

            return;
        }

        if ($operator === 'between' && is_array($value) && count($value) === 2) {
            $query->whereBetween($column, [$value[0], $value[1]]);

            return;
        }

        if (in_array($operator, ['gt', 'gte', 'lt', 'lte'], true)) {
            $comparison = match ($operator) {
                'gt' => '>',
                'gte' => '>=',
                'lt' => '<',
                'lte' => '<=',
            };

            $query->where($column, $comparison, $value);

            return;
        }

        if (in_array($operator, ['contains', 'starts_with', 'ends_with'], true) && is_scalar($value)) {
            $needle = strtolower((string) $value);

            $likePattern = match ($operator) {
                'contains' => '%'.$needle.'%',
                'starts_with' => $needle.'%',
                'ends_with' => '%'.$needle,
            };

            $query->whereRaw('LOWER(CAST('.$query->getGrammar()->wrap($column).' AS CHAR)) LIKE ?', [$likePattern]);
        }
    }
}
