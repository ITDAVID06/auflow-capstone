<?php

namespace App\Actions\FormBuilder;

use App\Modules\FormBuilder\Models\FormSubmission;

class FindSubmissionByIdempotencyKeyAction
{
    public function execute(string $idempotencyKey): ?FormSubmission
    {
        return FormSubmission::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }
}
