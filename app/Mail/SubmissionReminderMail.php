<?php

namespace App\Mail;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubmissionReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Allow more retries for transient provider throttling.
     */
    public int $tries = 5;

    /**
     * Exponential-ish backoff in seconds for retried reminder emails.
     *
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 30, 60];

    public function __construct(
        public Form $form,
        public WorkflowStep $step,
        public int $submissionId,
        public ?int $progressId = null,
        public bool $isReminder = false,
        public ?int $reminderNumber = null,
        public ?int $daysPending = null,
        public ?string $deadlineAt = null,
    ) {
        $this->step->loadMissing('assignedUser.profile');
    }

    public function build(): self
    {
        $approverName = $this->step->assignedUser?->profile?->first_name
            ?: $this->step->assignedUser?->name
            ?: 'Approver';

        // Use your real review route (progress id)
        $reviewUrl = route('staff-dashboard.submission.view', [
            'id' => $this->progressId ?? $this->submissionId,
        ]);

        $prefix = $this->isReminder
            ? 'Reminder'.($this->reminderNumber ? " #{$this->reminderNumber}" : '').': '
            : '';

        return $this->subject($prefix."Action Required: {$this->form->form_name} Submission")
            ->onQueue('mail')
            ->markdown('emails.submission.pending', [
                'formName' => $this->form->form_name,
                'submissionId' => $this->submissionId,
                'stepName' => $this->step->step_name ?? ('Step #'.$this->step->step_order),
                'approverName' => $approverName,
                'reviewUrl' => $reviewUrl,
                'isReminder' => $this->isReminder,
                'reminderNumber' => $this->reminderNumber,
                'daysPending' => $this->daysPending,
                'deadlineAt' => $this->deadlineAt,
            ]);
    }
}
