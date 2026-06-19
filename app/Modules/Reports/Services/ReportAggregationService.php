<?php

namespace App\Modules\Reports\Services;

use App\Modules\FormBuilder\Models\Form;
use InvalidArgumentException;

class ReportAggregationService
{
    /** Aggregation functions supported for any column (count ignores $aggColumn). */
    private const SUPPORTED_FUNCTIONS = ['count', 'sum', 'avg', 'min', 'max'];

    /** Functions that require a numeric column (sum / avg). */
    private const NUMERIC_ONLY_FUNCTIONS = ['sum', 'avg'];

    /**
     * The `count` function does not require an explicit aggregate column.
     */
    private const COLUMN_OPTIONAL_FUNCTIONS = ['count'];

    public function __construct(
        private readonly SubmissionQueryService $submissionQueryService,
        private readonly ReportColumnRegistry $columnRegistry,
        private readonly ReportQueryBuilderService $queryBuilderService,
    ) {}

    /**
     * Aggregate submission data for the given form and filters.
     *
     * @param  array<string, mixed>  $filters  Validated filter payload (same shape as ReportsFilterRequest)
     * @param  string  $groupByColumn  The column key to group by
     * @param  string  $aggFunction  One of: count, sum, avg, min, max
     * @param  string|null  $aggColumn  Column key to aggregate (optional for count)
     * @return array{group_value: mixed, aggregate_value: int|float|string|null}[]
     *
     * @throws InvalidArgumentException on unsupported function/column
     */
    public function aggregate(
        Form $form,
        array $filters,
        string $groupByColumn,
        string $aggFunction,
        ?string $aggColumn = null,
    ): array {
        $aggFunction = strtolower(trim($aggFunction));

        if (! in_array($aggFunction, self::SUPPORTED_FUNCTIONS, true)) {
            throw new InvalidArgumentException(
                "Unsupported aggregation function '{$aggFunction}'. Supported: ".implode(', ', self::SUPPORTED_FUNCTIONS).'.'
            );
        }

        $formFieldTypes = $this->columnRegistry->resolveFormFieldTypes($form);
        $filterableColumns = $this->queryBuilderService->resolveFilterableColumns($formFieldTypes);

        // Validate groupBy column
        if (! in_array($groupByColumn, $filterableColumns, true)) {
            throw new InvalidArgumentException(
                "Column '{$groupByColumn}' is not available for grouping on this form."
            );
        }

        // Validate aggColumn (required for non-count functions)
        if (! in_array($aggFunction, self::COLUMN_OPTIONAL_FUNCTIONS, true)) {
            if ($aggColumn === null || $aggColumn === '') {
                throw new InvalidArgumentException(
                    "An aggregate column is required for function '{$aggFunction}'."
                );
            }

            if (! in_array($aggColumn, $filterableColumns, true)) {
                throw new InvalidArgumentException(
                    "Column '{$aggColumn}' is not available for aggregation on this form."
                );
            }

            if (in_array($aggFunction, self::NUMERIC_ONLY_FUNCTIONS, true)) {
                $fieldType = $formFieldTypes[$aggColumn] ?? null;

                if (! in_array($fieldType, ['number', 'integer', 'decimal'], true)) {
                    throw new InvalidArgumentException(
                        "Function '{$aggFunction}' requires a numeric column. Column '{$aggColumn}' has type '{$fieldType}'. ".
                        'Consider adding a generated column for this field if the data is numeric.'
                    );
                }
            }
        }

        $formId = (int) $form->id;
        $query = $this->submissionQueryService->buildFilteredSubmissionQuery($formId, $filters, $formFieldTypes);

        $groupByExpr = $this->resolveQueryExpression($groupByColumn, $formFieldTypes);
        $aggColExpr = $aggColumn !== null ? $this->resolveQueryExpression($aggColumn, $formFieldTypes) : null;

        $selectGroupBy = "COALESCE({$groupByExpr}, '(no value)') AS group_value";

        $selectAgg = match ($aggFunction) {
            'count' => 'COUNT(*) AS aggregate_value',
            'sum' => "SUM({$aggColExpr}) AS aggregate_value",
            'avg' => "AVG({$aggColExpr}) AS aggregate_value",
            'min' => "MIN({$aggColExpr}) AS aggregate_value",
            'max' => "MAX({$aggColExpr}) AS aggregate_value",
        };

        $results = $query
            ->select([])
            ->selectRaw("{$selectGroupBy}, {$selectAgg}")
            ->groupByRaw($groupByExpr)
            ->orderByDesc('aggregate_value')
            ->limit(500) // cap result set to avoid accidentally huge responses
            ->get();

        return $results->map(static fn ($row) => [
            'group_value' => $row->group_value,
            'aggregate_value' => is_numeric($row->aggregate_value)
                ? (str_contains((string) $row->aggregate_value, '.') ? (float) $row->aggregate_value : (int) $row->aggregate_value)
                : $row->aggregate_value,
        ])->values()->all();
    }

    /**
     * Resolve a column key to a raw SQL expression suitable for SELECT / GROUP BY.
     *
     * @param  array<string, string>  $formFieldTypes
     */
    private function resolveQueryExpression(string $column, array $formFieldTypes): string
    {
        return match ($column) {
            'id', 'account_id', 'submission_status' => $column,
            'workflow_status' => 'current_workflow_status',
            'created_at' => 'DATE(submitted_at)',
            default => array_key_exists($column, $formFieldTypes)
                ? "JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.{$column}'))"
                : throw new InvalidArgumentException("Unknown column '{$column}'."),
        };
    }
}
