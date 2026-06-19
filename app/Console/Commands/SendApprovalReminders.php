<?php

namespace App\Console\Commands;

use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendApprovalReminders extends Command
{
    private const MAX_TOTAL_REMINDERS = 3;

    private const REMINDER_DISPATCH_SPACING_SECONDS = 1;

    protected $signature = 'workflow:send-approval-reminders';

    protected $description = 'Email approvers for Pending steps that have aged past configured days';

    public function handle(): int
    {
        $now = now();
        $legacyGlobalSpec = (string) config('workflow.reminder_delays', '1d,2d,3d');
        $defaultIntervalSpec = (string) config('workflow.reminder_default_interval', '1d');

        $dispatchOffsetSeconds = 0;

        WorkflowStepProgress::with(['version', 'step.assignedUser.profile', 'step.workflow.form', 'form'])
            ->where('status', 'Pending')
            ->whereNotNull('started_at')
            ->where(function ($query) {
                // If we have a version, we assume it has approvers/assignment (we validated this at submission)
                // Otherwise fallback to live step check
                $query->whereNotNull('workflow_version_id')
                    ->orWhereHas('step', function ($q) {
                        $q->whereNotNull('assigned_account_id')
                            ->orWhereHas('approvers');
                    });
            })
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($legacyGlobalSpec, $defaultIntervalSpec, $now, &$dispatchOffsetSeconds) {
                foreach ($chunk as $p) {
                    /** @var \App\Modules\WorkflowBuilder\Models\WorkflowStepProgress $p */
                    $version = $p->version;
                    $stepsSnapshot = $version ? (is_string($version->steps_snapshot) ? json_decode($version->steps_snapshot, true) : $version->steps_snapshot) : [];
                    $stepArr = ! empty($stepsSnapshot) ? collect($stepsSnapshot)->firstWhere('id', $p->step_id) : null;

                    if ($stepArr) {
                        $step = new WorkflowStep;
                        $step->forceFill($stepArr);
                        // Use snapshot approvers for email resolution
                        $emails = $this->resolveRecipientEmailsFromSnapshot($stepArr);
                    } else {
                        $step = $p->step;
                        if (! $step) {
                            continue;
                        }
                        $emails = $this->resolveRecipientEmails($step);
                    }

                    $form = $p->form ?? $step?->workflow?->form;
                    if (! $form || $emails->isEmpty()) {
                        continue;
                    }

                    // Get step-specific reminder settings from step_conditions
                    $stepConditions = $this->decodeStepConditions($step->step_conditions ?? []);

                    $workflowSettings = is_array($step->workflow?->workflow_settings)
                        ? $step->workflow->workflow_settings
                        : [];

                    $maxDurationHours = $stepConditions['max_duration_hours'] ?? $step->max_duration_hours;

                    $thresholdMinutes = $this->resolveReminderThresholdMinutes(
                        stepConditions: $stepConditions,
                        workflowSettings: $workflowSettings,
                        legacyGlobalSpec: $legacyGlobalSpec,
                        defaultIntervalSpec: $defaultIntervalSpec
                    );

                    if ($thresholdMinutes->isEmpty()) {
                        continue; // Skip if no valid intervals
                    }

                    // Use seconds for more precise timing (avoids scheduler minute boundary issues)
                    $ageSeconds = $p->started_at?->diffInSeconds($now) ?? 0;
                    $ageMinutes = intdiv($ageSeconds, 60);
                    $sentSoFar = (int) ($p->reminder_count ?? 0);
                    $cappedThresholdMinutes = $thresholdMinutes
                        ->map(fn ($minutes) => (int) $minutes)
                        ->filter(fn ($minutes) => $minutes > 0)
                        ->values()
                        ->take(self::MAX_TOTAL_REMINDERS)
                        ->values();

                    $maxAllowedReminders = $cappedThresholdMinutes->count();

                    if ($maxAllowedReminders === 0 || $sentSoFar >= $maxAllowedReminders) {
                        continue;
                    }

                    $nextReminderIndex = $sentSoFar;
                    $nextThresholdMinutes = (int) $cappedThresholdMinutes->get($nextReminderIndex);

                    if ($ageMinutes < $nextThresholdMinutes) {
                        continue;
                    }

                    // Debug logging
                    if ($this->option('verbose')) {
                        $reminderInterval = strtolower(trim((string) ($stepConditions['reminder_interval'] ?? 'default')));
                        $this->info("Progress #{$p->id} - Step: {$step->step_name}");
                        $this->info("  Interval: {$reminderInterval} -> thresholds: ".$cappedThresholdMinutes->implode(',').' mins');
                        $this->info("  Age: {$ageSeconds}s ({$ageMinutes}min) | Sent: {$sentSoFar} | Next threshold: {$nextThresholdMinutes}");
                    }

                    $deadlineAt = null;
                    if ($p->started_at && ! empty($maxDurationHours)) {
                        $deadlineAt = $p->started_at->copy()->addHours((int) $maxDurationHours)->toIso8601String();
                    }

                    foreach ($emails as $email) {
                        $dispatchAt = $now->copy()->addSeconds($dispatchOffsetSeconds);

                        Mail::to($email)->later($dispatchAt, new \App\Mail\SubmissionReminderMail(
                            form: $form,
                            step: $step,
                            submissionId: $p->submission_id,
                            progressId: $p->id,
                            isReminder: true,
                            reminderNumber: $sentSoFar + 1,
                            daysPending: intdiv($ageSeconds, 60 * 60 * 24), // Use seconds for calculation
                            deadlineAt: $deadlineAt
                        ));

                        $dispatchOffsetSeconds += self::REMINDER_DISPATCH_SPACING_SECONDS;
                    }

                    $p->forceFill([
                        'reminder_count' => $sentSoFar + 1,
                        'last_reminder_at' => $now,
                    ])->save();
                }
            });

        $this->info('Reminder sweep complete.');

        return self::SUCCESS;
    }

    private function resolveReminderThresholdMinutes(array $stepConditions, array $workflowSettings, string $legacyGlobalSpec, string $defaultIntervalSpec): Collection
    {
        $reminderMode = strtolower(trim((string) ($stepConditions['reminder_mode'] ?? '')));
        $reminderInterval = strtolower(trim((string) ($stepConditions['reminder_interval'] ?? 'default')));

        if ($reminderMode === 'none' || $reminderInterval === 'none') {
            return collect();
        }

        if ($reminderMode === 'custom') {
            $customValue = (int) ($stepConditions['reminder_value'] ?? 0);
            $customUnit = strtolower(trim((string) ($stepConditions['reminder_unit'] ?? 'hours')));

            if ($customValue > 0) {
                $customSpec = $this->buildIntervalSpecFromValueAndUnit($customValue, $customUnit);

                return $this->expandSingleIntervalToThreeReminders(
                    $this->parseIntervalSpec($customSpec)
                );
            }
        }

        if ($reminderInterval !== '' && $reminderInterval !== 'default') {
            return $this->expandSingleIntervalToThreeReminders(
                $this->parseIntervalSpec($this->convertIntervalToSpec($reminderInterval))
            );
        }

        $workflowDefaultSpec = $this->resolveWorkflowDefaultIntervalSpec($workflowSettings);
        if ($workflowDefaultSpec === 'none') {
            return collect();
        }

        if ($workflowDefaultSpec !== null && $workflowDefaultSpec !== '') {
            return $this->expandSingleIntervalToThreeReminders(
                $this->parseIntervalSpec($this->convertIntervalToSpec($workflowDefaultSpec))
            );
        }

        $legacyThresholds = $this->parseIntervalSpec($legacyGlobalSpec);
        if ($legacyThresholds->isNotEmpty()) {
            return $legacyThresholds;
        }

        return $this->expandSingleIntervalToThreeReminders(
            $this->parseIntervalSpec($defaultIntervalSpec)
        );
    }

    private function buildIntervalSpecFromValueAndUnit(int $value, string $unit): string
    {
        $normalizedUnit = match ($unit) {
            'minute', 'minutes', 'min', 'mins', 'm' => 'm',
            'hour', 'hours', 'h' => 'h',
            'day', 'days', 'd' => 'd',
            default => 'h',
        };

        return $value.$normalizedUnit;
    }

    private function resolveWorkflowDefaultIntervalSpec(array $workflowSettings): ?string
    {
        $direct = $workflowSettings['reminder_default_interval']
            ?? $workflowSettings['default_reminder_interval']
            ?? $workflowSettings['reminder_interval']
            ?? null;

        if (is_string($direct) && trim($direct) !== '') {
            return strtolower(trim($direct));
        }

        $reminder = $workflowSettings['reminder'] ?? null;
        if (is_array($reminder)) {
            $nested = $reminder['default_interval']
                ?? $reminder['interval']
                ?? null;

            if (is_string($nested) && trim($nested) !== '') {
                return strtolower(trim($nested));
            }
        }

        return null;
    }

    private function expandSingleIntervalToThreeReminders(Collection $thresholdMinutes): Collection
    {
        if ($thresholdMinutes->count() !== 1) {
            return $thresholdMinutes;
        }

        $singleInterval = (int) $thresholdMinutes->first();
        if ($singleInterval <= 0) {
            return collect();
        }

        return collect([
            $singleInterval,
            $singleInterval * 2,
            $singleInterval * 3,
        ]);
    }

    /**
     * Convert frontend interval format to spec format
     * Examples: "15min" => "15m", "1hour" => "1h", "2days" => "2d"
     * Also handles dynamic formats like "3min", "5min", "7days", etc.
     */
    private function convertIntervalToSpec(string $interval): string
    {
        $interval = strtolower(trim($interval));

        if ($interval === '') {
            return '';
        }

        // Normalize multiple spaces to support legacy values like "10 minutes".
        $normalized = preg_replace('/\s+/', ' ', $interval) ?? $interval;

        // Try predefined mappings first
        $mapped = match ($normalized) {
            '15min' => '15m',
            '30min' => '30m',
            '1hour' => '1h',
            '2hours' => '2h',
            '4hours' => '4h',
            '8hours' => '8h',
            '12hours' => '12h',
            '1day' => '1d',
            '2days' => '2d',
            '3days' => '3d',
            '7days' => '7d',
            default => null,
        };

        if ($mapped) {
            return $mapped;
        }

        // Handle dynamic formats: "3min" => "3m", "5hours" => "5h", "7days" => "7d"
        if (preg_match('/^(\d+)\s*(min|mins|minute|minutes|h|hr|hrs|hour|hours|d|day|days)$/', $normalized, $matches)) {
            $number = $matches[1];
            $unit = $matches[2];

            $shortUnit = match ($unit) {
                'min', 'mins', 'minute', 'minutes' => 'm',
                'h', 'hr', 'hrs', 'hour', 'hours' => 'h',
                'd', 'day', 'days' => 'd',
                default => 'd',
            };

            return $number.$shortUnit;
        }

        // Pass through if already in correct format (e.g., "3m", "1h", "2d")
        return str_replace(' ', '', $normalized);
    }

    /**
     * Parse interval spec string into collection of minutes
     * Example: "15m,30m,1h" => [15, 30, 60]
     */
    private function parseIntervalSpec(string $spec): Collection
    {
        return collect(explode(',', $spec))
            ->map(fn ($t) => strtolower(trim($t)))
            ->map(function ($t) {
                if (preg_match('/^(\d+)\s*([mhd])?$/', $t, $m)) {
                    $n = (int) $m[1];
                    $u = $m[2] ?? 'd'; // default to days for bare numbers

                    return match ($u) {
                        'm' => $n,
                        'h' => $n * 60,
                        'd' => $n * 60 * 24,
                    };
                }

                return null;
            })
            ->filter()
            ->sort()
            ->values();
    }

    private function decodeStepConditions(mixed $stepConditions): array
    {
        if (is_array($stepConditions)) {
            return $stepConditions;
        }

        $decoded = $stepConditions;
        for ($i = 0; $i < 3; $i++) {
            if (! is_string($decoded)) {
                break;
            }

            $attempt = json_decode($decoded, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }

            $decoded = $attempt;
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveRecipientEmails(WorkflowStep $step): Collection
    {
        $emails = collect();

        $step->loadMissing(['approvers.user.profile', 'assignedUser.profile']);

        if ($step->approvers->isNotEmpty()) {
            foreach ($step->approvers as $approver) {
                $email = $approver->user?->email ?? $approver->user?->profile?->email;
                if ($email) {
                    $emails->push($email);
                }
            }

            return $emails->filter()->unique()->values();
        }

        $fallback = $step->assignedUser?->email ?? $step->assignedUser?->profile?->email;
        if ($fallback) {
            $emails->push($fallback);
        }

        return $emails->filter()->unique()->values();
    }

    private function resolveRecipientEmailsFromSnapshot(array $stepArr): Collection
    {
        $emails = collect();

        $approvers = $stepArr['approvers'] ?? [];
        if (! empty($approvers)) {
            foreach ($approvers as $approver) {
                // The snapshot should contain the email if it was captured,
                // but if not, we might need to look up the user by account_id.
                // However, for consistency with the 'frozen' goal, we should have captured it.
                // If not, we fall back to a live lookup for the specific user.
                $email = $approver['user']['email'] ?? null;
                if (! $email && ! empty($approver['account_id'])) {
                    $user = \App\Modules\UserManagement\Models\User::with('profile')->where('account_id', $approver['account_id'])->first();
                    $email = $user?->email ?? $user?->profile?->email;
                }

                if ($email) {
                    $emails->push($email);
                }
            }
        }

        if ($emails->isEmpty() && ! empty($stepArr['assigned_account_id'])) {
            $user = \App\Modules\UserManagement\Models\User::with('profile')->where('account_id', $stepArr['assigned_account_id'])->first();
            $email = $user?->email ?? $user?->profile?->email;
            if ($email) {
                $emails->push($email);
            }
        }

        return $emails->filter()->unique()->values();
    }
}
