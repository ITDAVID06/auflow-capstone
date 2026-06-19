<?php

namespace App\Mail;

use App\Modules\Reports\Models\ScheduledExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ScheduledExportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ScheduledExport $scheduledExport,
        public readonly string $attachmentFilename,
        public readonly string $attachmentContent,
    ) {}

    public function build(): self
    {
        $formName = $this->scheduledExport->form->form_name ?? 'Unknown Form';
        $frequency = ucfirst($this->scheduledExport->frequency);
        $mimeType = $this->scheduledExport->export_type === 'pdf'
            ? 'application/pdf'
            : 'text/csv; charset=UTF-8';

        return $this
            ->subject("{$frequency} Report Export: {$formName}")
            ->markdown('emails.reports.scheduled-export', [
                'formName' => $formName,
                'frequency' => $frequency,
                'exportType' => strtoupper($this->scheduledExport->export_type),
            ])
            ->attachData(
                $this->attachmentContent,
                $this->attachmentFilename,
                ['mime' => $mimeType],
            );
    }
}
