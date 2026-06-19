<?php

namespace App\Mail;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubmissionPendingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Form $form,
        public WorkflowStep $step,
        public int $submissionId,
        public ?int $progressId = null
    ) {
        $this->step->loadMissing('assignedUser.profile');
    }

    public function build(): self
    {
        $approverName = $this->step->assignedUser?->profile?->first_name
            ?: $this->step->assignedUser?->name
            ?: 'Approver';

        return $this->subject("Action Required: {$this->form->form_name} Submission")
            ->onQueue('mail')
            ->markdown('emails.submission.pending', [
                'formName' => $this->form->form_name,
                'submissionId' => $this->submissionId,
                'stepName' => $this->step->step_name ?? ('Step #'.$this->step->step_order),
                'approverName' => $approverName,
                'reviewUrl' => route('staff-dashboard.submission.view', [
                    'id' => $this->progressId ?? $this->submissionId,
                ]),
            ]);

    }
}
