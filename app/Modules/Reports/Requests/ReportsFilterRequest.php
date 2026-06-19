<?php

namespace App\Modules\Reports\Requests;

use App\Modules\Reports\Requests\Concerns\ValidatesFilterState;
use App\Modules\Reports\Services\ReportQueryBuilderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReportsFilterRequest extends FormRequest
{
    use ValidatesFilterState;

    private const MAX_EXPORT_LIMIT = 5000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $formIdRule = ['integer', 'exists:tbl_form,id'];

        if ($this->routeIs('reports.index')) {
            array_unshift($formIdRule, 'nullable');
        } else {
            array_unshift($formIdRule, 'required');
        }

        return [
            'form_id'          => $formIdRule,
            'date_from'        => ['nullable', 'date_format:Y-m-d'],
            'date_to'          => ['nullable', 'date_format:Y-m-d'],
            'submission_status' => ['nullable', 'in:pending,approved,rejected,completed'],
            'account_id'       => ['nullable', 'integer', 'exists:tbl_user,account_id'],
            'submitter'        => ['nullable', 'string', 'max:120'],
            'select'           => ['nullable', 'array', 'min:1'],
            'select.*'         => ['required', 'string', 'max:120'],
            'filters'          => ['nullable', 'array'],
            'filters.*'        => ['required', 'array'],
            'sort'             => ['nullable', 'array'],
            'sort.column'      => ['required_with:sort', 'string', 'max:120'],
            'sort.direction'   => ['required_with:sort', 'string', 'in:asc,desc'],
            'per_page'         => ['nullable', 'integer', 'min:1', 'max:100'],
            'export_limit'     => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_EXPORT_LIMIT],
            'page'             => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $submissionStatus = $this->input('submission_status');
        $submitter        = $this->input('submitter');
        $select           = $this->input('select');
        $filters          = $this->input('filters');
        $sort             = $this->input('sort');
        $exportLimit      = $this->input('export_limit');

        $this->merge([
            'submission_status' => is_string($submissionStatus) ? strtolower(trim($submissionStatus)) : $submissionStatus,
            'submitter'         => is_string($submitter) ? trim($submitter) : $submitter,
            'select'            => is_array($select)
                ? array_values(array_map(static fn ($column) => is_string($column) ? trim($column) : $column, $select))
                : $select,
            'filters'           => is_array($filters)
                ? array_values(array_map(static function ($filter) {
                    if (! is_array($filter)) {
                        return $filter;
                    }
                    if (isset($filter['logic'])) {
                        $filter['logic'] = strtolower(trim((string) ($filter['logic'] ?? 'and')));
                        if (isset($filter['filters']) && is_array($filter['filters'])) {
                            $filter['filters'] = array_values(array_map(static function ($leaf) {
                                if (! is_array($leaf)) {
                                    return $leaf;
                                }
                                $column   = $leaf['column'] ?? null;
                                $operator = $leaf['operator'] ?? null;
                                $leaf['column']   = is_string($column) ? trim($column) : $column;
                                $leaf['operator'] = is_string($operator) ? strtolower(trim($operator)) : $operator;
                                return $leaf;
                            }, $filter['filters']));
                        }
                        return $filter;
                    }
                    $column   = $filter['column'] ?? null;
                    $operator = $filter['operator'] ?? null;
                    $filter['column']   = is_string($column) ? trim($column) : $column;
                    $filter['operator'] = is_string($operator) ? strtolower(trim($operator)) : $operator;
                    return $filter;
                }, $filters))
                : $filters,
            'sort'        => is_array($sort)
                ? [
                    'column'    => is_string($sort['column'] ?? null) ? trim((string) $sort['column']) : ($sort['column'] ?? null),
                    'direction' => is_string($sort['direction'] ?? null) ? strtolower(trim((string) $sort['direction'])) : ($sort['direction'] ?? null),
                ]
                : $sort,
            'export_limit' => $exportLimit === 'all'
                ? self::MAX_EXPORT_LIMIT
                : (is_numeric($exportLimit) ? (int) $exportLimit : $exportLimit),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dateFrom = $this->input('date_from');
            $dateTo   = $this->input('date_to');

            if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
                $validator->errors()->add('date_to', 'The date_to must be a date after or equal to date_from.');
            }

            $select  = $this->input('select');
            $filters = $this->input('filters');
            $sort    = $this->input('sort');

            if (! is_array($select) && ! is_array($filters) && ! is_array($sort)) {
                return;
            }

            $formId = $this->input('form_id');

            if (! is_numeric($formId)) {
                return;
            }

            [$allowedColumns, $formFieldTypes] = $this->resolveFormFieldData((int) $formId);
            $queryBuilderService = app(ReportQueryBuilderService::class);
            $filterableColumns   = $queryBuilderService->resolveFilterableColumns($formFieldTypes);
            $sortableColumns     = $queryBuilderService->resolveSortableColumns($formFieldTypes);

            if (is_array($select)) {
                foreach ($select as $index => $column) {
                    if (! is_string($column) || ! in_array($column, $allowedColumns, true)) {
                        $validator->errors()->add('select.' . $index, 'The selected column is not allowed for this form.');
                    }
                }
            }

            if (is_array($filters)) {
                foreach ($filters as $index => $filter) {
                    if (! is_array($filter)) {
                        continue;
                    }

                    if (isset($filter['logic'])) {
                        if (! in_array($filter['logic'], ['and', 'or'], true)) {
                            $validator->errors()->add('filters.' . $index . '.logic', 'The filter group logic must be "and" or "or".');
                        }

                        foreach ($filter['filters'] ?? [] as $leafIndex => $leaf) {
                            if (! is_array($leaf)) {
                                continue;
                            }
                            if (isset($leaf['logic'])) {
                                continue;
                            }
                            $this->validateLeafFilter(
                                $validator,
                                $leaf,
                                'filters.' . $index . '.filters.' . $leafIndex,
                                $filterableColumns,
                                $queryBuilderService,
                                $formFieldTypes,
                            );
                        }

                        continue;
                    }

                    $this->validateLeafFilter(
                        $validator,
                        $filter,
                        'filters.' . $index,
                        $filterableColumns,
                        $queryBuilderService,
                        $formFieldTypes,
                    );
                }
            }

            if (is_array($sort)) {
                $sortColumn = $sort['column'] ?? null;

                if (! is_string($sortColumn) || ! in_array($sortColumn, $sortableColumns, true)) {
                    $validator->errors()->add('sort.column', 'The sort column is not allowed for this form.');
                }
            }
        });
    }
}
