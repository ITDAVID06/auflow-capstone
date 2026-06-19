<?php

namespace App\Modules\Reports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavedReportRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'filter_state' => ['sometimes', 'array'],
        ];
    }
}
