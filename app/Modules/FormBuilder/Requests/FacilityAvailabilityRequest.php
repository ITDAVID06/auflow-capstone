<?php

namespace App\Modules\FormBuilder\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FacilityAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facility_id' => 'nullable|integer|exists:tbl_facility,id',
            'date' => 'required|date_format:Y-m-d',
        ];
    }
}
