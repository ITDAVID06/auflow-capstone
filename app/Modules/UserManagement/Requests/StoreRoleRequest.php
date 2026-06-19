<?php

namespace App\Modules\UserManagement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role_name' => 'required|string|unique:tbl_role,role_name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:tbl_permission,id',
        ];
    }
}
