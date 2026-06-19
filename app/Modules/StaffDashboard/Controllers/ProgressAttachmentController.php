<?php

namespace App\Modules\StaffDashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgressCommentAttachment as Attachment;

class ProgressAttachmentController extends Controller
{
    public function download(int $id)
    {
        $attachment = Attachment::with('progress.step.workflow.form')->findOrFail($id);
        $progress = $attachment->progress;

        // Authorization: staff/admin assigned OR the submission's owner (student/requester) OR admin with override permission
        $viewerId = (int) auth()->user()->account_id;
        $user = auth()->user();

        // FIX: Use hasPermission() instead of hasPermissionTo()
        $isAdmin = $user->hasPermission('submissions.override') || $user->hasPermission('submissions.view');

        // Submission owner check
        $submission = $this->resolveCanonicalSubmission($progress);
        $isOwner = $submission && (int) $submission->account_id === $viewerId;

        // Staff assigned on any progress of this submission
        $isAssignedStaff = WorkflowStepProgress::query()
            ->where('form_id', $progress->form_id)
            ->where('submission_id', $progress->submission_id)
            ->whereHas('step', fn ($q) => $q->where('assigned_account_id', $viewerId))
            ->exists();

        // Allow if: owner OR assigned staff OR admin with permissions
        if (! $isOwner && ! $isAssignedStaff && ! $isAdmin) {
            abort(403, 'You are not authorized to access this file.');
        }

        return response()->download(
            storage_path('app/private/'.ltrim($attachment->file_path, '/')),
            $attachment->original_name
        );
    }

    /**
     * Preview attachment inline (for PDFs, images, etc.)
     * Uses Content-Disposition: inline instead of attachment
     */
    public function preview(int $id)
    {
        $attachment = Attachment::with('progress.step.workflow.form')->findOrFail($id);
        $progress = $attachment->progress;

        // Same authorization as download
        $viewerId = (int) auth()->user()->account_id;
        $user = auth()->user();

        // FIX: Use hasPermission() instead of hasPermissionTo()
        $isAdmin = $user->hasPermission('submissions.override') || $user->hasPermission('submissions.view');

        $submission = $this->resolveCanonicalSubmission($progress);
        $isOwner = $submission && (int) $submission->account_id === $viewerId;

        $isAssignedStaff = WorkflowStepProgress::query()
            ->where('form_id', $progress->form_id)
            ->where('submission_id', $progress->submission_id)
            ->whereHas('step', fn ($q) => $q->where('assigned_account_id', $viewerId))
            ->exists();

        // Allow if: owner OR assigned staff OR admin with permissions
        if (! $isOwner && ! $isAssignedStaff && ! $isAdmin) {
            abort(403, 'You are not authorized to access this file.');
        }

        $path = storage_path('app/private/'.ltrim($attachment->file_path, '/'));

        if (! file_exists($path)) {
            abort(404, 'File not found.');
        }

        // Return file with inline disposition (preview in browser)
        return response()->file($path, [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$attachment->original_name.'"',
        ]);
    }

    private function resolveCanonicalSubmission(WorkflowStepProgress $progress): ?FormSubmission
    {
        return FormSubmission::query()->find($progress->submission_id);
    }
}
