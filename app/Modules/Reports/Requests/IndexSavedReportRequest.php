<?php

namespace App\Modules\Reports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexSavedReportRequest extends FormRequest
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
        ];
    }
}
