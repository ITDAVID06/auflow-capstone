<?php

namespace App\Modules\VerificationSnapshot\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\VerificationSnapshot\Services\SnapshotSecurityService;
use App\Services\SnapshotStorageService;
use Inertia\Inertia;

class SnapshotController extends Controller
{
    protected SnapshotSecurityService $securityService;

    protected SnapshotStorageService $storageService;

    public function __construct(SnapshotSecurityService $securityService, SnapshotStorageService $storageService)
    {
        $this->securityService = $securityService;
        $this->storageService = $storageService;
    }

    public function show(string $public_id)
    {
        $snap = Snapshot::with(['approver.profile', 'canonicalSubmission'])->where('public_id', $public_id)->firstOrFail();
        $payload = $snap->payload_json;

        // ── Status and step are read from the frozen payload (payload wins).
        // The DB columns are only consulted for snapshots created before this
        // deploy that do not yet carry these top-level keys.
        $status = $payload['status'] ?? $snap->status;
        $step = $payload['workflow_step'] ?? $snap->workflow_step;

        // ── Approval timeline: exclusively from the frozen payload.
        // For legacy snapshots that pre-date this deploy, approval_history will
        // be absent; the UI will show "No approval history recorded yet."
        $approvalHistory = $payload['approval_history'] ?? [];

        // Accept both: [{label,value,type,isFile,name}, ...] OR {"Label":"Value", ...}
        $raw = $payload['fields'] ?? [];
        $fields = [];

        // Determine whether the viewer may see unredacted / unmasked values.
        // Eligible viewers: the original submitter, or any user with staff-level
        // submission permissions. Any other authenticated user (e.g. a different
        // student) is treated the same as an unauthenticated visitor.
        $user = auth()->user();
        $canViewUnredacted = false;

        if ($user !== null) {
            $submissionAccountId = $snap->canonicalSubmission?->account_id;
            $isSubmitter = $submissionAccountId !== null
                && (int) $user->account_id === (int) $submissionAccountId;
            $hasStaffAccess = $user->hasPermission('submissions.view')
                || $user->hasPermission('submissions.override')
                || $user->hasPermission('requests.approve')
                || $user->hasPermission('requests.view')
                || $user->hasPermission('requests.manage');

            $canViewUnredacted = $isSubmitter || $hasStaffAccess;
        }

        if (is_array($raw)) {
            $isList = array_keys($raw) === range(0, count($raw) - 1);

            if ($isList) {
                foreach ($raw as $f) {
                    $label = (string) ($f['label'] ?? $f['name'] ?? 'Field');
                    $name = isset($f['name']) ? (string) $f['name'] : null;
                    $type = (string) ($f['type'] ?? 'text');
                    $value = $f['value'] ?? null;
                    $isFile = (bool) ($f['isFile'] ?? false);

                    // Resolve is_publicly_verifiable: top-level key takes precedence, then
                    // field_options fallback, then default true for legacy fields.
                    $isPubliclyVerifiable = $f['is_publicly_verifiable'] ?? $f['field_options']['is_publicly_verifiable'] ?? true;

                    if (! $canViewUnredacted && ! $isPubliclyVerifiable) {
                        $value = '[ Redacted for Privacy ]';
                    } elseif (! $canViewUnredacted && ($f['is_sensitive'] ?? false) && ! $isFile) {
                        $value = $this->maskSensitiveValue(is_string($value) ? $value : (string) $value);
                    }

                    $fields[] = [
                        'name' => $name,
                        'label' => $label,
                        'value' => $value,
                        'type' => $type,
                        'isFile' => $isFile,
                        'field_options' => $f['field_options'] ?? null,
                    ];
                }
            } else {
                // Key/value map snapshot payload (legacy format — no is_public metadata,
                // so all fields are treated as public to avoid retroactive data loss).
                foreach ($raw as $label => $value) {
                    $labelStr = (string) $label;
                    $isFile = is_string($value) && str_starts_with((string) $value, '/storage/');

                    $fields[] = [
                        'name' => null,
                        'label' => $labelStr,
                        'value' => $value,
                        'type' => $isFile ? 'file' : 'text',
                        'isFile' => $isFile,
                        'field_options' => null,
                    ];
                }
            }
        }

        // ── Attachments: first check payload (new snapshots), fall back to DB
        // query only for old snapshots that pre-date this deploy.
        // Attachment identity data is non-critical (file paths), so the fallback
        // is acceptable here.
        $attachments = $payload['attachments'] ?? null;

        if (! is_array($attachments)) {
            $attachments = SubmissionAttachment::query()
                ->where('submission_id', $snap->submission_id)
                ->get()
                ->map(fn ($att) => [
                    'id' => $att->id,
                    'filename' => $att->original_name,
                    'path' => $att->file_path,
                    'mime_type' => $att->mime_type,
                    'uploaded_at' => optional($att->created_at)->toDateTimeString(),
                ])
                ->toArray();
        }

        $isWorkflowComplete = (bool) ($payload['is_workflow_complete'] ?? false);

        // If a rendered HTML blob was uploaded to object storage, resolve a
        // short-lived URL so the frontend can offer a "View HTML" link.
        $renderedHtmlUrl = null;
        if (! empty($snap->rendered_html_path)) {
            $renderedHtmlUrl = $this->storageService->temporaryUrl(
                $snap->rendered_html_path,
                now()->addMinutes(30)
            );
        }

        return Inertia::render('snapshots/Show', [
            'snapshot' => [
                'public_id' => $snap->public_id,
                'short_code' => substr($snap->public_id, -6),
                'status' => $status,
                'step' => $step,
                'approved_by' => $snap->approver?->full_name
                    ?? ($payload['approval']['approved_by'] ?? '—'),
                'approved_at' => optional($snap->approved_at)->toDateTimeString(),
                'comment' => $snap->comment,
                'rendered_html_url' => $renderedHtmlUrl,
                'form' => [
                    'id' => $payload['form']['id'] ?? null,
                    'code' => $payload['form']['code'] ?? null,
                    'name' => $payload['form']['name'] ?? 'Request',
                    'version' => $payload['form']['version'] ?? null,
                ],
                'submission' => [
                    'id' => $payload['submission']['id'] ?? null,
                    'created_at' => $payload['submission']['created_at'] ?? null,
                ],
                'fields' => $fields,
                'is_workflow_complete' => $isWorkflowComplete,
            ],
            'approval_history' => $approvalHistory,
            'is_workflow_complete' => $isWorkflowComplete,
            'total_steps' => count($approvalHistory),
            'attachments' => $attachments,
        ]);
    }

    public function pdf(string $public_id)
    {
        abort(501, 'PDF generation not implemented yet.');
    }

    public function latestSnapshot(int $id)
    {
        $progress = \App\Modules\WorkflowBuilder\Models\WorkflowStepProgress::findOrFail($id);

        $snap = \App\Modules\VerificationSnapshot\Models\Snapshot::where('form_id', $progress->form_id)
            ->where('submission_id', $progress->submission_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (! $snap) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'public_id' => $snap->public_id,
            'short_code' => substr($snap->public_id, -6),
            'status' => $snap->payload_json['status'] ?? $snap->status,
            'is_workflow_complete' => (bool) ($snap->payload_json['is_workflow_complete'] ?? false),
            'approved_at' => optional($snap->approved_at)->toDateTimeString(),
            'url' => route('snapshots.show', $snap->public_id),
        ]);
    }

    /**
     * Partially mask a sensitive string value for unauthenticated public viewers.
     * For emails, masks only the username part while preserving the domain.
     * For other strings, keeps the first and last character only.
     */
    private function maskSensitiveValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            [$username, $domain] = explode('@', $value, 2);
            $len = mb_strlen($username);
            $masked = $len <= 2 ? '**' : mb_substr($username, 0, 1).'***'.mb_substr($username, -1);

            return $masked.'@'.$domain;
        }

        $len = mb_strlen($value);
        if ($len <= 2) {
            return '**';
        }

        return mb_substr($value, 0, 1).'***'.mb_substr($value, -1);
    }

    /**
     * Verify a single snapshot's integrity.
     * Reads status and step from the frozen payload, never from DB columns.
     */
    public function verifyHash(string $publicId)
    {
        $snapshot = Snapshot::where('public_id', $publicId)
            ->with('approver.profile')
            ->firstOrFail();

        $payload = $snapshot->payload_json;
        $verification = $this->securityService->verifyActionHash($snapshot);

        return response()->json([
            'snapshot' => [
                'public_id' => $snapshot->public_id,
                'workflow_step' => $payload['workflow_step'] ?? $snapshot->workflow_step,
                'status' => $payload['status'] ?? $snapshot->status,
                'approved_by' => $snapshot->approver?->full_name
                    ?? $snapshot->approver?->username
                    ?? 'Unknown',
                'approved_at' => $snapshot->approved_at?->toIso8601String(),
                'is_workflow_complete' => (bool) ($payload['is_workflow_complete'] ?? false),
            ],
            'verification' => $verification,
        ]);
    }

    /**
     * Verify all snapshots for a submission (complete audit trail).
     */
    public function verifySubmission(int $submissionId)
    {
        $verification = $this->securityService->verifySubmissionSnapshots($submissionId);

        return response()->json($verification);
    }
}
