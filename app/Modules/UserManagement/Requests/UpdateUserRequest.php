<?php

namespace App\Modules\UserManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Resource route param is {user}; implicit model binding uses account_id
        $routeParam = $this->route('user');
        $accountId = is_object($routeParam) ? $routeParam->account_id : (int) $routeParam;

        return [
            'username' => [
                'required',
                'string',
                Rule::unique('tbl_user', 'username')->ignore($accountId, 'account_id'),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('tbl_user', 'email')->ignore($accountId, 'account_id'),
            ],
            'password' => ['nullable', 'string', Password::defaults()],

            'user_status_id' => ['required', 'exists:tbl_user_status,id'],

            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'middle_name' => ['nullable', 'string'],
            'student_id' => ['nullable', 'string'],
            'employee_id' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', 'string'],

            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['exists:tbl_role,id'],

            'profile_picture' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
