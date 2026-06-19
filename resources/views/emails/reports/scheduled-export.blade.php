@component('mail::message')

<div style="margin-bottom: 32px;">
<div style="display: inline-block; background: #dbeafe; color: #1e40af; padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
{{ $frequency }} Report
</div>
<h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #111827; line-height: 1.3;">Your Scheduled Export is Ready</h1>
</div>

<p style="margin: 0 0 24px 0; font-size: 16px; line-height: 1.6; color: #374151;">
Your {{ strtolower($frequency) }} <strong>{{ $exportType }}</strong> export for <strong>{{ $formName }}</strong> has been generated and is attached to this email.
</p>

<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; margin: 24px 0;">
<table width="100%" cellpadding="0" cellspacing="0" style="font-size: 14px; color: #374151;">
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280; width: 140px;">Form</td>
<td style="padding: 8px 0; color: #111827; font-weight: 500;">{{ $formName }}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280;">Frequency</td>
<td style="padding: 8px 0; color: #111827;">{{ $frequency }}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280;">Format</td>
<td style="padding: 8px 0; color: #111827;">{{ $exportType }}</td>
</tr>
<tr>
<td style="padding: 8px 0; font-weight: 600; color: #6b7280;">Generated</td>
<td style="padding: 8px 0; color: #111827; font-family: monospace;">{{ now()->format('Y-m-d H:i') }} UTC</td>
</tr>
</table>
</div>

<p style="font-size: 13px; color: #9ca3af; margin-top: 32px;">
You are receiving this email because a scheduled export is configured for this form. To manage your scheduled exports, visit the Reports section in AUFlow.
</p>

@endcomponent
