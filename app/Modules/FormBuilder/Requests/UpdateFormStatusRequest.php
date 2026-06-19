<?php

namespace App\Modules\FormBuilder\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFormStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('forms.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:Active,Inactive',
        ];
    }
}
