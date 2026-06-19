<?php

namespace App\Modules\Reports\Services;

use App\Modules\FormBuilder\Models\Form;

class ChartDataService
{
    /**
     * Field data-types that should be excluded from the categorical-field picker
     * (binary/long-text/sensitive fields that don't chart well).
     */
    private const EXCLUDED_TYPES = [
        'file', 'attachment', 'image', 'signature',
        'textarea', 'richtext', 'password', 'hidden',
    ];

    /** Maximum rows fetched for PHP-side field-distribution aggregation. */
    private const DISTRIBUTION_ROW_LIMIT = 1_000;

    /** Number of top values to return for the distribution chart. */
    private const DISTRIBUTION_TOP_N = 10;

    public function __construct(
        private readonly SubmissionQueryService $submissionQueryService,
        private readonly ReportColumnRegistry $columnRegistry,
        private readonly ReportSummaryService $summaryService,
    ) {}

    /**
     * Build all chart data for the given form and filter set.
     *
     * @param  array<string, mixed>  $filters  Validated filter payload
     * @param  string|null  $fieldKey  Optional form-field key for distribution chart
     * @return array{
     *   kpi: array{total_submissions: int, approved: int, pending: int, avg_completion_human: string|null},
     *   trend: list<array{date: string, count: int}>,
     *   status_breakdown: list<array{status: string, count: int}>,
     *   field_distribution: list<array{value: string, count: int}>|null,
     *   field_distribution_column: string|null,
     *   available_field_columns: list<array{key: string, label: string}>,
     * }
     */
    public function getChartData(Form $form, array $filters, ?string $fieldKey): array
    {
        $formFieldTypes = $this->columnRegistry->resolveFormFieldTypes($form);

        // Guarantee a date window for the trend chart (default: last 30 days)
        $trendFilters = $filters;
        if (empty($trendFilters['date_from']) && empty($trendFilters['date_to'])) {
            $trendFilters['date_from'] = now()->subDays(29)->format('Y-m-d');
            $trendFilters['date_to'] = now()->format('Y-m-d');
        }

        $baseQuery = $this->submissionQueryService->buildFilteredSubmissionQuery($form->id, $filters, $formFieldTypes);
        $summary = $this->summaryService->buildSummary($baseQuery, $filters);

        $categoricalFields = $this->resolveCategoricalFields($form);
        $resolvedFieldKey = ($fieldKey !== null && $fieldKey !== '') ? $fieldKey : null;
        $defaultFieldKey = $categoricalFields[0]['key'] ?? null;

        return [
            'kpi' => [
                'total_submissions' => (int) ($summary['total_submissions'] ?? 0),
                'approved'          => (int) ($summary['status_counts']['approved'] ?? 0),
                'pending'           => (int) ($summary['status_counts']['pending'] ?? 0),
                'avg_completion_human' => $summary['average_completion_human'] ?? null,
            ],
            'trend' => $this->buildTrend($form, $trendFilters, $formFieldTypes),
            'status_breakdown' => $this->buildStatusBreakdown($form, $filters, $formFieldTypes),
            'field_distribution' => ($resolvedFieldKey !== null)
                ? $this->buildFieldDistribution($form, $filters, $formFieldTypes, $resolvedFieldKey)
                : null,
            'field_distribution_column' => $resolvedFieldKey ?? $defaultFieldKey,
            'available_field_columns'   => $categoricalFields,
        ];
    }

    // -------------------------------------------------------------------------
    // Private builders
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $formFieldTypes
     * @return list<array{date: string, count: int}>
     */
    private function buildTrend(Form $form, array $filters, array $formFieldTypes): array
    {
        $query = clone $this->submissionQueryService->buildFilteredSubmissionQuery(
            $form->id,
            $filters,
            $formFieldTypes,
        );
        // Clear inherited columns so only the aggregate expressions are selected,
        // preventing MySQL ONLY_FULL_GROUP_BY violations.
        $query->getQuery()->columns = null;

        $rows = $query
            ->selectRaw('DATE(submitted_at) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return $rows->map(fn ($r) => [
            'date' => (string) $r->day,
            'count' => (int) $r->cnt,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $formFieldTypes
     * @return list<array{status: string, count: int}>
     */
    private function buildStatusBreakdown(Form $form, array $filters, array $formFieldTypes): array
    {
        $query = clone $this->submissionQueryService->buildFilteredSubmissionQuery(
            $form->id,
            $filters,
            $formFieldTypes,
        );
        $query->getQuery()->columns = null;

        $rows = $query
            ->selectRaw("COALESCE(LOWER(submission_status), 'unknown') as status, COUNT(*) as cnt")
            ->groupBy('status')
            ->orderByDesc('cnt')
            ->get();

        return $rows->map(fn ($r) => [
            'status' => (string) $r->status,
            'count' => (int) $r->cnt,
        ])->values()->all();
    }

    /**
     * Aggregate form-field values in PHP (payload_json is encrypted, so DB-level GROUP BY
     * is not feasible). Caps at DISTRIBUTION_ROW_LIMIT rows for performance.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $formFieldTypes
     * @return list<array{value: string, count: int}>
     */
    private function buildFieldDistribution(
        Form $form,
        array $filters,
        array $formFieldTypes,
        string $fieldKey,
    ): array {
        // Only distribute over known form fields
        if (! isset($formFieldTypes[$fieldKey])) {
            return [];
        }

        $submissions = (clone $this->submissionQueryService->buildFilteredSubmissionQuery(
            $form->id,
            $filters,
            $formFieldTypes,
        ))
            ->select(['id', 'payload_json'])
            ->latest('submitted_at')
            ->limit(self::DISTRIBUTION_ROW_LIMIT)
            ->get();

        /** @var array<string, int> $counts */
        $counts = [];
        foreach ($submissions as $submission) {
            $payload = $submission->payload_json;
            if (! is_array($payload)) {
                continue;
            }

            $rawValue = $payload[$fieldKey] ?? null;
            $strValue = is_array($rawValue)
                ? implode(', ', array_filter(
                    $rawValue,
                    fn ($v) => is_string($v) || is_numeric($v),
                ))
                : (string) ($rawValue ?? '');

            $strValue = trim($strValue);
            if ($strValue === '') {
                $strValue = '(empty)';
            }

            $counts[$strValue] = ($counts[$strValue] ?? 0) + 1;
        }

        arsort($counts);
        $top = array_slice($counts, 0, self::DISTRIBUTION_TOP_N, true);

        return array_values(array_map(
            fn (string $value, int $count) => ['value' => $value, 'count' => $count],
            array_keys($top),
            array_values($top),
        ));
    }

    /**
     * Return categorical form fields suitable for the distribution picker.
     *
     * @return list<array{key: string, label: string}>
     */
    private function resolveCategoricalFields(Form $form): array
    {
        $result = [];
        foreach ($form->fields as $field) {
            $type = strtolower(trim((string) ($field->data_type ?? 'text')));
            if (in_array($type, self::EXCLUDED_TYPES, true)) {
                continue;
            }

            $key = trim((string) ($field->field_name ?? ''));
            $label = trim((string) ($field->label ?? $key));
            if ($key === '') {
                continue;
            }

            $result[] = ['key' => $key, 'label' => $label];
        }

        return $result;
    }
}
