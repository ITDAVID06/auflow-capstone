<?php

namespace App\Modules\ErrorReports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreErrorReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint is intentionally open; no auth required for error reporting
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'stack' => ['required', 'string', 'max:10000'],
            'url' => ['required', 'string', 'max:2048'],
            'user_agent' => ['required', 'string', 'max:512'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
