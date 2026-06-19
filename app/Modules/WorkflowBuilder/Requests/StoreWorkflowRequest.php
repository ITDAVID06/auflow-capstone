<?php

namespace App\Modules\WorkflowBuilder\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('workflows.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'workflow_name' => ['required', 'string', 'max:255'],
            'workflow_type' => ['nullable', 'string', 'in:Sequential,Parallel'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,active,archive,Draft,Active,Archived'],
            'form_id' => ['nullable', 'integer', 'exists:tbl_form,id'],
            'workflow_settings' => ['nullable', 'array'],
            'workflow_settings.nodes' => ['nullable', 'array'],
            'workflow_settings.edges' => ['nullable', 'array'],
            'steps' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = [
                'workflow_name',
                'workflow_type',
                'description',
                'status',
                'form_id',
                'workflow_settings',
                'steps',
                '_token',
                '_method',
            ];

            $unexpected = array_values(array_diff(array_keys($this->all()), $allowed));
            if (! empty($unexpected)) {
                $validator->errors()->add('payload', 'Unexpected fields: '.implode(', ', $unexpected));
            }
        });
    }
}
