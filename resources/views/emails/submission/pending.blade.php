@php
  // Safe defaults so the view never breaks
  $isReminder     = $isReminder     ?? false;
  $reminderNumber = $reminderNumber ?? null;
  $daysPending    = $daysPending    ?? null;
  $deadlineAt     = $deadlineAt     ?? null;
@endphp

@component('mail::message')

{{-- Professional Header --}}
<div style="margin-bottom: 32px;">
@if(($isReminder) && !empty($reminderNumber))
<div style="display: inline-block; background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
Reminder #{{ $reminderNumber }}
</div>
<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #111827; line-height: 1.3;">Action Required: Pending Review</h1>
@else
<div style="display: inline-block; background: #dbeafe; color: #1e40af; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
New Submission
</div>
<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #111827; line-height: 1.3;">Action Required: Pending Approval</h1>
@endif
</div>

{{-- Greeting --}}
<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #374151;">
Hello <strong>{{ $approverName }}</strong>,
</p>

@if($isReminder)
<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #374151;">
This is a friendly reminder that a submission is still awaiting your review and approval.
</p>
@else
<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #374151;">
A new submission has been assigned to you for review and approval.
</p>
@endif

{{-- Submission Details Card --}}
<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; margin: 24px 0;">
<table width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px; color: #374151;">
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280; width: 140px;">Form Name</td>
<td style="padding: 8px 0; color: #111827; font-weight: 500;">{{ $formName }}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280;">Submission ID</td>
<td style="padding: 8px 0; color: #111827; font-family: monospace; font-size: 13px;">#{{ $submissionId }}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280;">Your Step</td>
<td style="padding: 8px 0; color: #111827; font-weight: 500;">{{ $stepName }}</td>
</tr>
@if(!empty($daysPending) && $daysPending > 0)
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280;">Time Pending</td>
<td style="padding: 8px 0;">
<span style="display: inline-block; background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">
{{ $daysPending }} day{{ $daysPending > 1 ? 's' : '' }}
</span>
</td>
</tr>
@endif
@if(!empty($deadlineAt))
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280;">Deadline</td>
<td style="padding: 8px 0;">
<span style="display: inline-block; background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">
{{ \Carbon\Carbon::parse($deadlineAt)->format('M d, Y \a\t g:i A') }}
</span>
</td>
</tr>
@endif
</table>
</div>

{{-- Urgency Message --}}
@if(($isReminder) && !empty($daysPending) && $daysPending > 0)
<div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px 20px; border-radius: 4px; margin: 24px 0;">
<p style="margin: 0; font-size: 14px; line-height: 1.6; color: #92400e;">
<strong>Attention:</strong> This submission has been pending for <strong>{{ $daysPending }} day{{ $daysPending > 1 ? 's' : '' }}</strong>. Please review it at your earliest convenience to keep the workflow moving.
</p>
</div>
@endif

{{-- Action Button --}}
@component('mail::button', ['url' => $reviewUrl, 'color' => 'primary'])
Review & Take Action
@endcomponent

<p style="margin: 32px 0 0 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
If you have any questions or need assistance, please contact the system administrator.
</p>

<p style="margin: 16px 0 0 0; font-size: 14px; line-height: 1.6; color: #374151;">
Best regards,<br>
<strong style="color: #111827;">AUFlow Team</strong><br>
<span style="color: #9ca3af; font-size: 13px;">Angeles University Foundation</span>
</p>

@component('mail::subcopy')
If you're having trouble clicking the "Review & Take Action" button, copy and paste the URL below into your web browser:
<br><br>
<span style="color: #6b7280; word-break: break-all;">{{ $reviewUrl }}</span>
@endcomponent
@endcomponent

