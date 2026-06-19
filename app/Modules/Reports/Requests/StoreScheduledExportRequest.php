<?php

namespace App\Modules\Reports\Requests;

use App\Modules\Reports\Requests\Concerns\ValidatesFilterState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScheduledExportRequest extends FormRequest
{
    use ValidatesFilterState;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'form_id'         => ['required', 'integer', 'exists:tbl_form,id'],
            'recipient_email' => ['required', 'email', 'max:255'],
            'frequency'       => ['required', 'in:daily,weekly,monthly'],
            'export_type'     => ['required', 'in:csv,pdf'],
            'filter_state'    => ['nullable', 'array'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('filter_state'))) {
            $this->merge(['filter_state' => []]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $filterState = $this->input('filter_state');

            if (! is_array($filterState)) {
                return;
            }

            if ($this->filterStateExceedsMaxDepth($filterState)) {
                $validator->errors()->add('filter_state', 'The filter state nesting exceeds the maximum allowed depth.');
                return;
            }

            $formId = (int) $this->input('form_id');
            if ($formId > 0) {
                $this->validateFilterStateContents($validator, $filterState, $formId);
            }
        });
    }
}
