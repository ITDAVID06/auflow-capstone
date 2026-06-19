<?php

namespace App\Modules\FormBuilder\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteFormImageRequest extends FormRequest
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
            'path' => ['required', 'string', 'not_regex:/\.\./'],
        ];
    }
}
