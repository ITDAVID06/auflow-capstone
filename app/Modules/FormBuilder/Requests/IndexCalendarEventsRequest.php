<?php

namespace App\Modules\FormBuilder\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexCalendarEventsRequest extends FormRequest
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
            'facility_id' => 'nullable|integer|exists:tbl_facility,id',
            'status' => 'nullable|in:Pending,Approved,Rejected',
            'start' => 'nullable|date_format:Y-m-d|required_with:end',
            'end' => 'nullable|date_format:Y-m-d|required_with:start|after_or_equal:start',
        ];
    }
}
