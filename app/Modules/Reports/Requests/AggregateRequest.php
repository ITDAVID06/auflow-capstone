<?php

namespace App\Modules\Reports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AggregateRequest extends FormRequest
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
            'form_id' => ['required', 'integer', 'exists:tbl_form,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'submission_status' => ['nullable', 'in:pending,approved,rejected,completed'],
            'account_id' => ['nullable', 'integer', 'exists:tbl_user,account_id'],
            'submitter' => ['nullable', 'string', 'max:120'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['required', 'array'],
            'group_by' => ['required', 'string', 'max:120'],
            'function' => ['required', 'string', 'in:count,sum,avg,min,max'],
            'column' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('submission_status');
        $fn = $this->input('function');

        $this->merge([
            'submission_status' => is_string($status) ? strtolower(trim($status)) : $status,
            'function' => is_string($fn) ? strtolower(trim($fn)) : $fn,
        ]);
    }
}
