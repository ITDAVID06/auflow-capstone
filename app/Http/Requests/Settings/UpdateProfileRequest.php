<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth middleware on route is sufficient
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $accountId = $this->user()->account_id;

        return [
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z][a-zA-Z0-9._-]+$/',
                Rule::unique('tbl_user', 'username')->ignore($accountId, 'account_id'),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'student_id' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Username must start with a letter and may only contain letters, numbers, periods, underscores, or hyphens.',
            'username.unique' => 'This username is already taken.',
        ];
    }
}
