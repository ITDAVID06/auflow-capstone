<?php

namespace App\Services\DemoSeeding;

use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Support\Facades\DB;

class DemoNotificationSeeder
{
    public function seed(): int
    {
        $now = now();
        $rows = [];

        $pendingProgresses = WorkflowStepProgress::query()
            ->where('status', 'Pending')
            ->orderBy('id')
            ->get(['id', 'step_id', 'submission_id', 'actor_id']);

        foreach ($pendingProgresses as $progress) {
            $rows[] = [
                'account_id' => (int) $progress->actor_id,
                'type' => 'workflow_pending_approval',
                'title' => 'New Approval Request',
                'message' => 'A seeded request is awaiting your review.',
                'action_url' => '/staff-dashboard',
                'action_text' => 'Review Request',
                'related_type' => 'workflow_step',
                'related_id' => (int) $progress->step_id,
                'icon' => 'bell',
                'priority' => 'high',
                'is_read' => false,
                'triggered_by' => null,
                'idempotency_key' => "seed:demo:pending:{$progress->submission_id}:{$progress->step_id}:{$progress->actor_id}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $completedSubmissions = FormSubmission::query()
            ->whereIn('current_workflow_status', ['Approved', 'Rejected'])
            ->orderBy('id')
            ->get(['id', 'account_id', 'current_workflow_status']);

        foreach ($completedSubmissions as $submission) {
            $outcome = strtolower((string) $submission->current_workflow_status);
            $rows[] = [
                'account_id' => (int) $submission->account_id,
                'type' => "submission_{$outcome}",
                'title' => $outcome === 'approved' ? 'Request Approved' : 'Request Rejected',
                'message' => $outcome === 'approved'
                    ? 'Your seeded request has been approved.'
                    : 'Your seeded request has been rejected.',
                'action_url' => '/student-dashboard',
                'action_text' => 'View Request',
                'related_type' => 'submission',
                'related_id' => (int) $submission->id,
                'icon' => $outcome === 'approved' ? 'check-circle' : 'x-circle',
                'priority' => 'normal',
                'is_read' => false,
                'triggered_by' => null,
                'idempotency_key' => "seed:demo:submission:{$submission->id}:{$outcome}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        DB::table('tbl_notification')->upsert(
            $rows,
            ['idempotency_key'],
            ['title', 'message', 'priority', 'updated_at']
        );

        return count($rows);
    }
}
