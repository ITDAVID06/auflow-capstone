<?php

namespace App\Modules\FormBuilder\Requests;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Support\FieldConditionEvaluator;
use App\Modules\FormBuilder\Support\FormFieldTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;

class StoreFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by route middleware (permission slugs)
    }

    public function rules(): array
    {
        $formId = (int) $this->route('id');
        $form = Form::with('fields')->findOrFail($formId);

        $visibleFields = FieldConditionEvaluator::visibleFields($form->fields, $this->all());

        $rules = [];
        $requiresSlotsPayload = false;
        $requiresDateRangesPayload = false;

        foreach ($visibleFields as $field) {
            if (FormFieldTypeRegistry::isNonInput((string) $field->data_type)) {
                continue;
            }

            $key = $field->field_name;
            $isRequired = (bool) $field->is_required;
            $requiredRule = $isRequired ? 'required' : 'nullable';
            $isRangeMode = ($field->date_mode ?? 'single') === 'range';
            $isSlotMode = (bool) ($field->use_slots || $field->require_facility) && ! $isRangeMode;
            $isSlotBasedDate = (bool) ($field->use_slots || $field->require_facility || $isRangeMode);

            if ($field->data_type === 'date') {
                if ($isRequired && $isSlotMode) {
                    $requiresSlotsPayload = true;
                }

                if ($isRequired && $isRangeMode) {
                    $requiresDateRangesPayload = true;
                }

                if (! $isSlotBasedDate) {
                    $rules[$key] = $requiredRule.'|date';
                }

                continue;
            }

            if ($field->data_type === 'checkbox' && $field->options && count($field->options) > 1) {
                $rules[$key] = $requiredRule.'|array';
            } elseif ($field->data_type === 'table') {
                $rules[$key] = [
                    $requiredRule,
                    'string',
                    function (string $attribute, mixed $value, \Closure $fail) use ($field): void {
                        if ($value === null || $value === '') {
                            return;
                        }

                        if (! is_string($value)) {
                            $fail('The '.$attribute.' must be a valid JSON string.');

                            return;
                        }

                        $rows = json_decode($value, true);
                        if (! is_array($rows) || ! array_is_list($rows)) {
                            $fail('The '.$attribute.' must be a JSON array of table rows.');

                            return;
                        }

                        $fieldOptions = is_array($field->field_options ?? null) ? $field->field_options : [];
                        $tableColumns = is_array($fieldOptions['table_columns'] ?? null) ? $fieldOptions['table_columns'] : [];
                        $columnKeys = [];

                        foreach ($tableColumns as $column) {
                            if (! is_array($column)) {
                                continue;
                            }

                            $columnId = trim((string) ($column['id'] ?? ''));
                            if ($columnId !== '') {
                                $columnKeys[] = $columnId;
                            }
                        }

                        $columnKeys = array_values(array_unique($columnKeys));
                        if ($columnKeys === []) {
                            return;
                        }

                        foreach ($rows as $index => $row) {
                            if (! is_array($row)) {
                                $fail('The '.$attribute.' row #'.($index + 1).' must be an object.');

                                return;
                            }

                            $rowKeys = array_keys($row);
                            $missingKeys = array_diff($columnKeys, $rowKeys);
                            $unexpectedKeys = array_diff($rowKeys, $columnKeys);

                            if ($missingKeys !== [] || $unexpectedKeys !== []) {
                                $fail('The '.$attribute.' row #'.($index + 1).' does not match the configured table columns.');

                                return;
                            }
                        }
                    },
                ];
            } elseif ($field->data_type === 'file') {
                $rules[$key] = $requiredRule
                    .'|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:10240';
            } elseif ($field->data_type === 'textarea') {
                $rules[$key] = $requiredRule.'|string|max:100000';
            } elseif ($field->data_type === 'email') {
                $rules[$key] = $requiredRule.'|email|max:10000';
            } elseif ($field->data_type === 'number') {
                $fieldOptions = is_array($field->field_options ?? null) ? $field->field_options : [];

                $numberRules = [$requiredRule, 'numeric'];
                if (array_key_exists('min', $fieldOptions) && is_numeric($fieldOptions['min'])) {
                    $numberRules[] = 'min:'.$fieldOptions['min'];
                }

                if (array_key_exists('max', $fieldOptions) && is_numeric($fieldOptions['max'])) {
                    $numberRules[] = 'max:'.$fieldOptions['max'];
                }

                $rules[$key] = $numberRules;
            } elseif (in_array($field->data_type, ['text', 'phone'], true)) {
                $rules[$key] = $requiredRule.'|string|max:10000';
            } else {
                $rules[$key] = $requiredRule;
            }
        }

        $rules['attachments'] = 'nullable|array';
        $rules['attachments.*'] = 'file|mimes:jpg,webp,jpeg,png,pdf,doc,docx|max:10240';

        $rules['slots'] = $requiresSlotsPayload ? 'required|array|min:1' : 'nullable|array';
        $rules['slots.*.field_name'] = 'nullable|string|max:255';
        $rules['slots.*.slot_id'] = ($requiresSlotsPayload ? 'required' : 'nullable').'|integer|exists:tbl_slots,id';
        $rules['slots.*.date'] = ($requiresSlotsPayload ? 'required' : 'nullable').'|date_format:Y-m-d';
        $rules['slots.*.start_time'] = 'nullable|date_format:H:i';
        $rules['slots.*.end_time'] = 'nullable|date_format:H:i';
        $rules['slots.*.facility_id'] = 'nullable';

        $rules['date_ranges'] = $requiresDateRangesPayload ? 'required|array|min:1' : 'nullable|array';
        $rules['date_ranges.*.field_name'] = 'nullable|string|max:255';
        $rules['date_ranges.*.from'] = 'nullable|date_format:Y-m-d';
        $rules['date_ranges.*.to'] = 'nullable|date_format:Y-m-d';
        $rules['date_ranges.*.start_date'] = 'nullable|date_format:Y-m-d';
        $rules['date_ranges.*.start'] = ($requiresDateRangesPayload ? 'required' : 'nullable').'|date_format:Y-m-d';
        $rules['date_ranges.*.end'] = [
            $requiresDateRangesPayload ? 'required' : 'nullable',
            'date_format:Y-m-d',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (! preg_match('/^date_ranges\.(\d+)\.end$/', $attribute, $matches)) {
                    return;
                }

                $index = (int) $matches[1];
                $start = $this->input("date_ranges.$index.start");
                if ($start === null || $start === '') {
                    return;
                }

                $startDate = \DateTime::createFromFormat('Y-m-d', (string) $start);
                $endDate = \DateTime::createFromFormat('Y-m-d', (string) $value);
                if ($startDate === false || $endDate === false) {
                    return;
                }

                if ($endDate < $startDate) {
                    $fail('The '.$attribute.' must be a date after or equal to the start date.');
                }
            },
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'attachments.*.mimes' => 'Each file must be JPG, JPEG, PNG, WEBP, PDF, DOC, or DOCX.',
            'attachments.*.max' => 'Each file must be at most 10 MB.',
        ];
    }
}
