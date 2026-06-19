<?php

namespace App\Modules\FormBuilder\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFormCategoryRequest extends FormRequest
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
            'name' => 'required|string|max:100|unique:tbl_form_category,name',
            'slug' => 'nullable|string|max:100|unique:tbl_form_category,slug',
        ];
    }
}
