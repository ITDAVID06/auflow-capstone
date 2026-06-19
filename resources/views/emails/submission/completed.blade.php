@component('mail::message')

{{-- Professional Header with Status --}}
<div style="margin-bottom: 32px;">
@if(strtolower($statusWord) === 'approved')
<div style="display: inline-block; background: #d1fae5; color: #065f46; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
Approved
</div>
<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #111827; line-height: 1.3;">Submission Approved</h1>
@elseif(strtolower($statusWord) === 'rejected')
<div style="display: inline-block; background: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
Rejected
</div>
<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #111827; line-height: 1.3;">Submission Rejected</h1>
@else
<div style="display: inline-block; background: #e0e7ff; color: #3730a3; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
Updated
</div>
<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #111827; line-height: 1.3;">Submission {{ $statusWord }}</h1>
@endif
</div>

{{-- Greeting --}}
<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #374151;">
Hello <strong>{{ $submitterName }}</strong>,
</p>

{{-- Status-specific message --}}
@if(strtolower($statusWord) === 'approved')
<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #374151;">
<strong style="color: #059669;">Great news!</strong> Your submission has been <strong style="color: #059669;">approved</strong> and processed successfully. All required approvals have been completed.
</p>
@elseif(strtolower($statusWord) === 'rejected')
<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #374151;">
We regret to inform you that your submission has been <strong style="color: #dc2626;">rejected</strong>. Please review the feedback provided and consider resubmitting if appropriate.
</p>
@else
<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #374151;">
Your submission status has been updated to <strong>{{ $statusWord }}</strong>.
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
<td style="padding: 8px 0; font-weight: 600; color: #6b7280;">Status</td>
<tr>
<td style="padding: 8px 0;">
@if(strtolower($statusWord) === 'approved')
<span style="display: inline-block; background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">
{{ $statusWord }}
</span>
@elseif(strtolower($statusWord) === 'rejected')
<span style="display: inline-block; background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">
{{ $statusWord }}
</span>
@else
<span style="display: inline-block; background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">
{{ $statusWord }}
</span>
@endif
</td>
</tr>
</tr>
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280;">Updated</td>
<td style="padding: 8px 0; color: #111827; font-size: 13px;">{{ now()->format('M d, Y \a\t g:i A') }}</td>
</tr>
</table>
</div>

{{-- Status-specific info box --}}
@if(strtolower($statusWord) === 'approved')
<div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 16px 20px; border-radius: 4px; margin: 24px 0;">
<p style="margin: 0; font-size: 14px; line-height: 1.6; color: #065f46;">
<strong>Success:</strong> Your request has been successfully completed. You can view the full details and download verification documents using the button below.
</p>
</div>
@elseif(strtolower($statusWord) === 'rejected')
<div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 16px 20px; border-radius: 4px; margin: 24px 0;">
<p style="margin: 0; font-size: 14px; line-height: 1.6; color: #991b1b;">
<strong>Next Steps:</strong> Please review your submission details for feedback from the approver. You may revise and resubmit your request if appropriate.
</p>
</div>
@endif

{{-- Action Button --}}
@component('mail::button', ['url' => $viewUrl, 'color' => strtolower($statusWord) === 'approved' ? 'success' : (strtolower($statusWord) === 'rejected' ? 'error' : 'primary')])
View Submission Details
@endcomponent

<p style="margin: 32px 0 0 0; font-size: 14px; line-height: 1.6; color: #6b7280;">
If you have any questions or concerns, please contact the system administrator or the approving department.
</p>

<p style="margin: 16px 0 0 0; font-size: 14px; line-height: 1.6; color: #374151;">
Best regards,<br>
<strong style="color: #111827;">AUFlow Team</strong><br>
<span style="color: #9ca3af; font-size: 13px;">Angeles University Foundation</span>
</p>

@component('mail::subcopy')
If you're having trouble clicking the "View Submission Details" button, copy and paste the URL below into your web browser:
<br><br>
<span style="color: #6b7280; word-break: break-all;">{{ $viewUrl }}</span>
@endcomponent
@endcomponent
