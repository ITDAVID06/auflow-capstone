<?php

namespace App\Modules\ErrorReports\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateErrorReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is protected by permission:error-reports.manage middleware
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:new,reviewed,in_progress,dismissed,resolved'],
        ];
    }
}
