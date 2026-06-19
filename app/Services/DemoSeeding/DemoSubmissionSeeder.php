<?php

namespace App\Services\DemoSeeding;

use App\Modules\FormBuilder\Models\Facility;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\Slot;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;

class DemoSubmissionSeeder
{
    /**
     * @param  array<int, Form>  $forms
     * @param  array<int, array{workflow: Workflow, steps: array<int, WorkflowStep>}>  $workflowMap
     * @return array{
     *     created_submission_ids: array<int, int>,
     *     scenario_counts: array<string, int>,
     *     totals: array<string, int>
     * }
     */
    public function seed(DemoSeedProfile $profile, array $forms, array $workflowMap, bool $withEdge): array
    {
        $this->seedFacilities();

        $students = User::query()
            ->where('email', 'like', 'student%@auf.test')
            ->orderBy('account_id')
            ->get(['account_id', 'email']);

        $staffSubmitters = User::query()
            ->where('email', 'like', 'staff%@auf.test')
            ->orderBy('account_id')
            ->get(['account_id', 'email']);

        if ($students->isEmpty() && $staffSubmitters->isEmpty()) {
            return [
                'created_submission_ids' => [],
                'scenario_counts' => [],
                'totals' => ['submissions' => 0, 'progress_entries' => 0],
            ];
        }

        $forms = collect($forms)->map(fn (Form $form) => $form->loadMissing('fields'))->values();

        $createdSubmissionIds = [];
        $scenarioCounts = [];
        $progressEntries = 0;
        $rejectedRootsByFormAndAccount = [];

        $deterministicCount = $profile->deterministicSubmissionCount;
        for ($i = 1; $i <= $deterministicCount; $i++) {
            $form = $forms[($i - 1) % $forms->count()];
            $workflowBundle = $workflowMap[$form->id] ?? null;

            if (! $workflowBundle) {
                continue;
            }

            $workflow = $workflowBundle['workflow'];
            $steps = collect($workflowBundle['steps'])->sortBy('step_order')->values();
            $submitterId = $this->resolveSubmitterIdForSequence(
                sequence: $i,
                students: $students,
                staffSubmitters: $staffSubmitters,
            );

            $scenario = $this->deterministicScenarioForIndex($i, $withEdge);
            $scenarioCounts[$scenario] = (int) ($scenarioCounts[$scenario] ?? 0) + 1;

            $seedKey = "demo:{$profile->name}:deterministic:{$i}";
            $result = $this->seedSingleSubmission(
                form: $form,
                workflow: $workflow,
                steps: $steps,
                submitterId: $submitterId,
                scenario: $scenario,
                seedKey: $seedKey,
                sequence: $i,
                rejectedRootsByFormAndAccount: $rejectedRootsByFormAndAccount,
            );

            $createdSubmissionIds[] = $result['submission_id'];
            $progressEntries += $result['progress_entries'];
        }

        $expandedCount = $profile->expandedSubmissionCount;
        if ($expandedCount > 0) {
            foreach (array_chunk(range(1, $expandedCount), 100) as $batch) {
                foreach ($batch as $i) {
                    $form = $forms[($i - 1) % $forms->count()];
                    $workflowBundle = $workflowMap[$form->id] ?? null;

                    if (! $workflowBundle) {
                        continue;
                    }

                    $workflow = $workflowBundle['workflow'];
                    $steps = collect($workflowBundle['steps'])->sortBy('step_order')->values();
                    $sequence = $deterministicCount + $i;
                    $submitterId = $this->resolveSubmitterIdForSequence(
                        sequence: $sequence,
                        students: $students,
                        staffSubmitters: $staffSubmitters,
                    );

                    $scenario = $this->expandedScenarioForIndex($i);
                    $scenarioCounts[$scenario] = (int) ($scenarioCounts[$scenario] ?? 0) + 1;

                    $seedKey = "demo:{$profile->name}:expanded:{$i}";
                    $result = $this->seedSingleSubmission(
                        form: $form,
                        workflow: $workflow,
                        steps: $steps,
                        submitterId: $submitterId,
                        scenario: $scenario,
                        seedKey: $seedKey,
                        sequence: $sequence,
                        rejectedRootsByFormAndAccount: $rejectedRootsByFormAndAccount,
                    );

                    $createdSubmissionIds[] = $result['submission_id'];
                    $progressEntries += $result['progress_entries'];
                }
            }
        }

        return [
            'created_submission_ids' => array_values(array_unique($createdSubmissionIds)),
            'scenario_counts' => $scenarioCounts,
            'totals' => [
                'submissions' => count(array_unique($createdSubmissionIds)),
                'progress_entries' => $progressEntries,
            ],
        ];
    }

    private function resolveSubmitterIdForSequence(int $sequence, $students, $staffSubmitters): int
    {
        if ($students->isEmpty() && $staffSubmitters->isNotEmpty()) {
            return (int) $staffSubmitters[($sequence - 1) % $staffSubmitters->count()]->account_id;
        }

        if ($students->isEmpty()) {
            return 0;
        }

        // Ensure staff "My Requests" tabs have seeded rows while keeping total volume unchanged.
        if ($staffSubmitters->isNotEmpty() && (($sequence - 1) % 6) === 0) {
            return (int) $staffSubmitters[0]->account_id;
        }

        if ($staffSubmitters->count() > 1 && (($sequence - 1) % 6) === 3) {
            return (int) $staffSubmitters[1]->account_id;
        }

        return (int) $students[($sequence - 1) % $students->count()]->account_id;
    }

    /**
     * @param  array<string, int>  $rejectedRootsByFormAndAccount
     * @return array{submission_id:int, progress_entries:int}
     */
    private function seedSingleSubmission(
        Form $form,
        Workflow $workflow,
        $steps,
        int $submitterId,
        string $scenario,
        string $seedKey,
        int $sequence,
        array &$rejectedRootsByFormAndAccount,
    ): array {
        $submittedAt = now()->subHours($sequence + 3);

        $schemaSnapshot = $form->fields
            ->sortBy('field_order')
            ->map(fn ($field) => [
                'name' => $field->field_name,
                'label' => $field->label,
                'type' => $field->data_type,
                'required' => (bool) $field->is_required,
            ])
            ->values()
            ->all();

        $payload = [
            'request_reason' => "Seeded {$scenario} scenario for {$form->form_code}",
        ];

        $payload = array_merge($payload, $this->buildPayloadForForm($form, $scenario, $sequence, $submittedAt));

        $currentStep = $steps->firstWhere('step_order', 2) ?: $steps->first();
        $currentActor = (int) ($currentStep?->assigned_account_id ?? $submitterId);

        $submissionStatus = 'Pending';
        $workflowStatus = 'Pending';

        if (in_array($scenario, ['approved', 'override_approved'], true)) {
            $submissionStatus = 'Completed';
            $workflowStatus = 'Approved';
            $currentStep = null;
            $currentActor = null;
        } elseif ($scenario === 'rejected') {
            $submissionStatus = 'Rejected';
            $workflowStatus = 'Rejected';
            $currentStep = null;
            $currentActor = null;
        }

        $revisionOf = null;
        $rootSubmissionId = null;
        $revisionLookupKey = $form->id.':'.$submitterId;
        if ($scenario === 'revision_pending' && isset($rejectedRootsByFormAndAccount[$revisionLookupKey])) {
            $revisionOf = $rejectedRootsByFormAndAccount[$revisionLookupKey];
            $rootSubmissionId = $rejectedRootsByFormAndAccount[$revisionLookupKey];
        }

        $submission = FormSubmission::query()->updateOrCreate(
            [
                'idempotency_key' => $seedKey,
            ],
            [
                'idempotency_key' => $seedKey,
                'form_id' => $form->id,
                'account_id' => $submitterId,
                'submission_status' => $submissionStatus,
                'current_workflow_status' => $workflowStatus,
                'current_step_id' => $currentStep?->id,
                'current_actor_id' => $currentActor,
                'payload_json' => $payload,
                'schema_snapshot_json' => $schemaSnapshot,
                'submitted_at' => $submittedAt,
                'revision_of' => $revisionOf,
                'root_submission_id' => $rootSubmissionId,
                'is_latest_revision' => true,
            ]
        );

        if ($revisionOf) {
            FormSubmission::query()->where('id', $revisionOf)->update(['is_latest_revision' => false]);
            $submission->update(['root_submission_id' => $rootSubmissionId]);
        }

        if ($scenario === 'rejected') {
            $rejectedRootsByFormAndAccount[$revisionLookupKey] = $submission->id;
        }

        $progressEntries = $this->seedProgressEntries(
            form: $form,
            workflow: $workflow,
            steps: $steps,
            submission: $submission,
            scenario: $scenario,
            submittedAt: $submittedAt,
        );

        $this->seedSlotForSubmission($submission, $form, $submitterId, $scenario, $submittedAt);

        return [
            'submission_id' => (int) $submission->id,
            'progress_entries' => $progressEntries,
        ];
    }

    private function seedProgressEntries(Form $form, Workflow $workflow, $steps, FormSubmission $submission, string $scenario, $submittedAt): int
    {
        $entries = 0;

        foreach ($steps as $step) {
            $base = [
                'form_id' => $form->id,
                'submission_id' => (int) $submission->id,
                'workflow_id' => $workflow->id,
                'workflow_version' => (int) ($workflow->version ?? 1),
                'step_id' => $step->id,
                'actor_id' => (int) ($step->assigned_account_id ?: $submission->account_id),
                'comments' => null,
                'last_reminder_at' => null,
                'reminder_count' => 0,
            ];

            $status = 'Waiting';
            $actionTaken = null;
            $startedAt = null;
            $actedAt = null;
            $completedAt = null;
            $duration = null;

            if ($step->step_order === 1) {
                $status = 'Approved';
                $actionTaken = 'Approve';
                $startedAt = $submittedAt->copy()->addMinutes(10);
                $actedAt = $submittedAt->copy()->addMinutes(90);
                $completedAt = $actedAt;
                $duration = $completedAt->diffInSeconds($startedAt);
            }

            if ($scenario === 'pending' || $scenario === 'revision_pending') {
                if ($step->step_order === 2) {
                    $status = 'Pending';
                    $actionTaken = null;
                    $startedAt = $submittedAt->copy()->addHours(2);
                }
                if ($step->step_order >= 3) {
                    $status = 'Waiting';
                }
            }

            if ($scenario === 'approved') {
                if ($step->step_order >= 2) {
                    $status = 'Approved';
                    $actionTaken = 'Approve';
                    $startedAt = $submittedAt->copy()->addHours($step->step_order * 2);
                    $actedAt = $submittedAt->copy()->addHours(($step->step_order * 2) + 1);
                    $completedAt = $actedAt;
                    $duration = $completedAt->diffInSeconds($startedAt);
                }
            }

            if ($scenario === 'override_approved') {
                if ($step->step_order === 2) {
                    $status = 'Approved';
                    $actionTaken = 'Override-Approve';
                    $startedAt = $submittedAt->copy()->addHours(4);
                    $actedAt = $submittedAt->copy()->addHours(5);
                    $completedAt = $actedAt;
                    $duration = $completedAt->diffInSeconds($startedAt);
                }

                if ($step->step_order >= 3) {
                    $status = 'Approved';
                    $actionTaken = 'Approve';
                    $startedAt = $submittedAt->copy()->addHours(6);
                    $actedAt = $submittedAt->copy()->addHours(7 + max(0, $step->step_order - 3));
                    $completedAt = $actedAt;
                    $duration = $completedAt->diffInSeconds($startedAt);
                }
            }

            if ($scenario === 'rejected') {
                if ($step->step_order === 2) {
                    $status = 'Rejected';
                    $actionTaken = 'Reject';
                    $startedAt = $submittedAt->copy()->addHours(3);
                    $actedAt = $submittedAt->copy()->addHours(5);
                    $completedAt = $actedAt;
                    $duration = $completedAt->diffInSeconds($startedAt);
                }

                if ($step->step_order >= 3) {
                    $status = 'Skipped';
                    $actionTaken = 'Auto-Rejected';
                    $startedAt = $submittedAt->copy()->addHours(6);
                    $actedAt = $submittedAt->copy()->addHours(6);
                    $completedAt = $actedAt;
                    $duration = 0;
                }
            }

            if ($scenario === 'delayed_pending' && $step->step_order === 2) {
                $status = 'Pending';
                $startedAt = $submittedAt->copy()->subDays(3);
                $actionTaken = null;
                $base['reminder_count'] = 2;
                $base['last_reminder_at'] = now()->subDay();
            }

            WorkflowStepProgress::query()->updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'step_id' => $step->id,
                ],
                array_merge($base, [
                    'status' => $status,
                    'action_taken' => $actionTaken,
                    'started_at' => $startedAt,
                    'acted_at' => $actedAt,
                    'completed_at' => $completedAt,
                    'duration_seconds' => $duration,
                ])
            );

            $entries++;
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadForForm(Form $form, string $scenario, int $sequence, $submittedAt): array
    {
        $payload = [];

        foreach ($form->fields->sortBy('field_order') as $field) {
            $fieldName = (string) $field->field_name;
            $fieldType = strtolower((string) $field->data_type);
            $options = is_array($field->options) ? array_values($field->options) : [];

            $payload[$fieldName] = match ($fieldType) {
                'number' => ($sequence % 45) + 5,
                'date' => $field->date_mode === 'range'
                    ? [
                        'start' => $submittedAt->copy()->addDays(($sequence % 5) + 1)->toDateString(),
                        'end' => $submittedAt->copy()->addDays(($sequence % 5) + 2)->toDateString(),
                    ]
                    : $submittedAt->copy()->addDays(($sequence % 7) + 1)->toDateString(),
                'select', 'radio' => $options !== []
                    ? (string) $options[$sequence % count($options)]
                    : 'Default Option',
                'checkbox' => $options !== []
                    ? array_slice($options, 0, min(2, count($options)))
                    : ['Default Option'],
                'email' => "student{$sequence}@auf.test",
                'phone' => '+63917123'.str_pad((string) (($sequence % 9000) + 1000), 4, '0', STR_PAD_LEFT),
                'file' => "seed/demo/{$form->form_code}/{$scenario}-{$sequence}.pdf",
                'textarea' => "Seeded {$scenario} details for {$form->form_code} (sequence {$sequence}).",
                default => "Demo {$fieldName} value {$sequence}",
            };
        }

        if (! array_key_exists('request_reason', $payload)) {
            $payload['request_reason'] = "Seeded {$scenario} scenario for {$form->form_code}";
        }

        if (! array_key_exists('supporting_notes', $payload)) {
            $payload['supporting_notes'] = 'Generated by seed:demo';
        }

        return $payload;
    }

    private function seedSlotForSubmission(FormSubmission $submission, Form $form, int $submitterId, string $scenario, $submittedAt): void
    {
        $facilityNames = ['Auditorium', 'Gymnasium', 'Library Hall'];
        $facilityName = $facilityNames[$submission->id % count($facilityNames)];

        $facility = Facility::query()->firstWhere('name', $facilityName);
        if (! $facility) {
            return;
        }

        $slotStatus = match ($scenario) {
            'approved', 'override_approved' => 'Approved',
            'rejected' => 'Rejected',
            default => 'Pending',
        };

        Slot::query()->updateOrCreate(
            [
                'submission_id' => $submission->id,
                'facility_id' => $facility->id,
                'date' => $submittedAt->copy()->addDay()->toDateString(),
            ],
            [
                'form_id' => $form->id,
                'submission_id' => (int) $submission->id,
                'account_id' => $submitterId,
                'start_time' => '09:00',
                'end_time' => '11:00',
                'status' => $slotStatus,
            ]
        );
    }

    private function seedFacilities(): void
    {
        $facilities = [
            ['name' => 'Auditorium', 'description' => 'Main campus auditorium'],
            ['name' => 'Gymnasium', 'description' => 'Indoor sports facility'],
            ['name' => 'Library Hall', 'description' => 'Library event space'],
        ];

        foreach ($facilities as $facility) {
            Facility::query()->updateOrCreate(
                ['name' => $facility['name']],
                [
                    'description' => $facility['description'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function deterministicScenarioForIndex(int $index, bool $withEdge): string
    {
        $base = ['approved', 'pending', 'rejected'];

        if ($withEdge) {
            $base = ['approved', 'pending', 'rejected', 'override_approved', 'revision_pending', 'delayed_pending'];
        }

        return $base[($index - 1) % count($base)];
    }

    private function expandedScenarioForIndex(int $index): string
    {
        $distribution = ['approved', 'pending', 'approved', 'pending', 'rejected'];

        return $distribution[($index - 1) % count($distribution)];
    }
}
