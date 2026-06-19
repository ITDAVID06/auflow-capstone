<?php

namespace App\Services\DemoSeeding;

use Illuminate\Database\Eloquent\Model;

class DemoSeederOrchestrator
{
    public function __construct(
        protected DemoFormSeeder $formSeeder,
        protected DemoWorkflowSeeder $workflowSeeder,
        protected DemoSubmissionSeeder $submissionSeeder,
        protected DemoSnapshotSeeder $snapshotSeeder,
        protected DemoNotificationSeeder $notificationSeeder,
        protected DemoAuditSeeder $auditSeeder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(int $adminAccountId, DemoSeedProfile $profile, bool $withEdge): array
    {
        $forms = Model::withoutEvents(fn () => $this->formSeeder->seed($adminAccountId, $profile));
        $workflowMap = Model::withoutEvents(fn () => $this->workflowSeeder->seed($forms, $adminAccountId));
        $submissionResult = Model::withoutEvents(fn () => $this->submissionSeeder->seed($profile, $forms, $workflowMap, $withEdge));
        $snapshotCount = Model::withoutEvents(fn () => $this->snapshotSeeder->seed($withEdge));
        $notificationCount = $this->notificationSeeder->seed();
        $auditCount = $this->auditSeeder->seed();

        return [
            'forms' => count($forms),
            'workflows' => count($workflowMap),
            'submissions' => (int) ($submissionResult['totals']['submissions'] ?? 0),
            'progress_entries' => (int) ($submissionResult['totals']['progress_entries'] ?? 0),
            'snapshots' => $snapshotCount,
            'notifications' => $notificationCount,
            'audit_logs' => $auditCount,
            'scenario_counts' => $submissionResult['scenario_counts'] ?? [],
        ];
    }
}
