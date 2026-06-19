<?php

namespace App\Modules\Performance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PerformanceFilterRequest extends FormRequest
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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'form_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dateFrom = $this->input('date_from');
            $dateTo = $this->input('date_to');

            if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
                $validator->errors()->add('date_to', 'The date_to must be a date after or equal to date_from.');
            }
        });
    }
}
