<?php

namespace App\Modules\WorkflowBuilder\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCanvasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('workflows.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nodes' => ['required', 'array'],
            'edges' => ['required', 'array'],
        ];
    }
}
