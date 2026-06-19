<?php

namespace App\Modules\Reports\Requests;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\Reports\Services\CrossFormComparisonService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompareRequest extends FormRequest
{
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
            'form_ids' => ['required', 'array', 'min:1', 'max:10'],
            'form_ids.*' => ['required', 'integer', 'exists:tbl_form,id'],
            'metric' => ['nullable', 'string', Rule::in(CrossFormComparisonService::SUPPORTED_METRICS)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $formIds = $this->input('form_ids');
        if (is_array($formIds)) {
            $this->merge(['form_ids' => array_map('intval', $formIds)]);
        }
    }

    /**
     * Verify all requested forms are active after base validation passes.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $formIds = $this->input('form_ids');
            if (! is_array($formIds) || count($formIds) === 0) {
                return;
            }

            $uniqueIds = array_unique(array_map('intval', $formIds));
            $activeCount = Form::whereIn('id', $uniqueIds)
                ->where('status', 'Active')
                ->count();

            if ($activeCount !== count($uniqueIds)) {
                $v->errors()->add('form_ids', 'One or more selected forms are not active.');
            }
        });
    }
}
