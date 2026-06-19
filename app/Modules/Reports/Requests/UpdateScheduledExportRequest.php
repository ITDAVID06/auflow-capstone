<?php

namespace App\Modules\Reports\Requests;

use App\Modules\Reports\Models\ScheduledExport;
use App\Modules\Reports\Requests\Concerns\ValidatesFilterState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateScheduledExportRequest extends FormRequest
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
            'recipient_email' => ['sometimes', 'email', 'max:255'],
            'frequency'       => ['sometimes', 'in:daily,weekly,monthly'],
            'export_type'     => ['sometimes', 'in:csv,pdf'],
            'filter_state'    => ['nullable', 'array'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
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

            // Resolve form_id from the existing ScheduledExport (not in request body)
            $exportId = (int) $this->route('id');
            $export   = ScheduledExport::find($exportId);
            $formId   = $export ? (int) $export->form_id : 0;

            if ($formId > 0) {
                $this->validateFilterStateContents($validator, $filterState, $formId);
            }
        });
    }
}
