<?php

namespace App\Modules\StaffDashboard\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProgressActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $isRejectRoute = str_contains((string) $this->route()?->getName(), 'progress.reject');
        $commentRules = ['string', 'max:2000'];
        array_unshift($commentRules, $isRejectRoute ? 'required' : 'nullable');

        return [
            'comment' => $commentRules,

            // Attachments: up to 5 files, 20MB each, allowlist (no zip)
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:20480'],
        ];
    }
}
