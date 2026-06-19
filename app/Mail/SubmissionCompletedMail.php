<?php

namespace App\Mail;

use App\Modules\FormBuilder\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubmissionCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Form $form,
        public int $submissionId,
        /** 'approved' | 'rejected' */
        public string $outcome,
        public string $submitterName,
        /** Absolute URL where submitter can view the final submission */
        public string $viewUrl
    ) {}

    public function build(): self
    {
        $statusWord = ucfirst($this->outcome); // Approved / Rejected

        return $this->subject("{$this->form->form_name}: Submission {$statusWord}")
            ->onQueue('mail')
            ->markdown('emails.submission.completed', [
                'formName' => $this->form->form_name,
                'submissionId' => $this->submissionId,
                'submitterName' => $this->submitterName,
                'statusWord' => $statusWord,
                'viewUrl' => $this->viewUrl,
            ]);
    }
}
