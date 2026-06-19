<?php

namespace App\Modules\UserManagement\Requests;

use App\Modules\UserManagement\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'role_name' => [
                'required',
                'string',
                Rule::unique('tbl_role', 'role_name')->ignore($role->id),
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'exists:tbl_permission,id',
        ];
    }
}
