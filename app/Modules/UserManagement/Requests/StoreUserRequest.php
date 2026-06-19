<?php

namespace App\Modules\UserManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware already protects
    }

    public function rules(): array
    {
        return [
            // core user
            'username' => 'required|string|unique:tbl_user,username',
            'email' => 'required|email|unique:tbl_user,email',
            'password' => ['required', 'string', Password::defaults()],

            // status
            'user_status_id' => 'required|exists:tbl_user_status,id',

            // profile
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'middle_name' => 'nullable|string',
            'student_id' => 'nullable|string',
            'employee_id' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => 'nullable|in:Male,Female,Other',

            // roles
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:tbl_role,id',

            'profile_picture' => 'nullable|image|max:2048',
        ];
    }
}
