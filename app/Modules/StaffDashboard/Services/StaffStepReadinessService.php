<?php

namespace App\Modules\StaffDashboard\Services;

use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;

class StaffStepReadinessService
{
    public function isStepReady(WorkflowStep $step, int $submissionId): bool
    {
        $progress = WorkflowStepProgress::with('version')->where('submission_id', $submissionId)->where('step_id', $step->id)->first();
        $version = $progress?->version;
        $stepsSnapshot = $version ? (is_string($version->steps_snapshot) ? json_decode($version->steps_snapshot, true) : $version->steps_snapshot) : [];

        if (! empty($stepsSnapshot)) {
            $snapshotSteps = collect($stepsSnapshot);
            $currentStep = $snapshotSteps->firstWhere('id', $step->id);
            if (! $currentStep) {
                return false;
            }

            $currentGroup = $currentStep['step_group'] ?? 0;
            if ($currentGroup > 0) {
                $priorStepIds = $snapshotSteps->filter(fn ($s) => ($s['step_group'] ?? 0) < $currentGroup)->pluck('id');
                if ($priorStepIds->isEmpty()) {
                    return true;
                }

                $statuses = WorkflowStepProgress::whereIn('step_id', $priorStepIds)
                    ->where('submission_id', $submissionId)
                    ->pluck('status');

                return $statuses->every(fn ($status) => in_array($status, ['Approved', 'Skipped'], true));
            }

            // Fallback to sequential in snapshot if no group
            $currentOrder = $currentStep['step_order'] ?? 0;
            $priorStepIds = $snapshotSteps->filter(fn ($s) => ($s['step_order'] ?? 0) < $currentOrder)->pluck('id');
            if ($priorStepIds->isEmpty()) {
                return true;
            }

            $statuses = WorkflowStepProgress::whereIn('step_id', $priorStepIds)
                ->where('submission_id', $submissionId)
                ->pluck('status');

            return $statuses->every(fn ($status) => in_array($status, ['Approved', 'Skipped'], true));
        }

        // FALLBACK to live table logic for pre-version (legacy) submissions
        $workflow = $step->workflow;

        if (! empty($step->step_group) && $step->step_group > 0) {
            $priorGroupSteps = WorkflowStep::where('workflow_id', $workflow->id)
                ->where('step_group', '<', $step->step_group)
                ->pluck('id');

            if ($priorGroupSteps->isEmpty()) {
                return true;
            }

            $statuses = WorkflowStepProgress::whereIn('step_id', $priorGroupSteps)
                ->where('submission_id', $submissionId)
                ->pluck('status');

            return $statuses->every(fn ($status) => in_array($status, ['Approved', 'Skipped'], true));
        }

        return $this->isSequentiallyReady($step, $submissionId);
    }

    private function isSequentiallyReady(WorkflowStep $step, int $submissionId): bool
    {
        $priorSteps = WorkflowStep::where('workflow_id', $step->workflow_id)
            ->where('step_order', '<', $step->step_order)
            ->pluck('id');

        if ($priorSteps->isEmpty()) {
            return true;
        }

        $statuses = WorkflowStepProgress::whereIn('step_id', $priorSteps)
            ->where('submission_id', $submissionId)
            ->pluck('status');

        return $statuses->every(fn ($status) => in_array($status, ['Approved', 'Skipped'], true));
    }
}
