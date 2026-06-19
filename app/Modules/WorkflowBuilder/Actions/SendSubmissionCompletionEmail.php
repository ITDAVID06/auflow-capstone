<?php

namespace App\Modules\WorkflowBuilder\Actions;

use App\Mail\SubmissionCompletedMail;
use App\Modules\FormBuilder\Models\Form;
use Illuminate\Support\Facades\Mail;

final class SendSubmissionCompletionEmail
{
    /**
     * @param  string  $outcome  'approved' | 'rejected'
     * @param  string  $viewUrl  Absolute URL for submitter to view the final submission
     */
    public static function run(
        Form $form,
        int $submissionId,
        string $outcome,
        string $submitterEmail,
        string $submitterName,
        string $viewUrl
    ): void {
        // guard: only two outcomes allowed
        $outcome = strtolower($outcome);
        if (! in_array($outcome, ['approved', 'rejected'], true)) {
            $outcome = 'approved';
        }

        Mail::to($submitterEmail)->sendNow(
            new SubmissionCompletedMail(
                form: $form,
                submissionId: $submissionId,
                outcome: $outcome,
                submitterName: $submitterName,
                viewUrl: $viewUrl
            )
        );
    }
}
