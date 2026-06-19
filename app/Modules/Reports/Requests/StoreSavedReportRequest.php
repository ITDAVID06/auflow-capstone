<?php

namespace App\Modules\Reports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavedReportRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'filter_state' => ['required', 'array'],
        ];
    }
}
