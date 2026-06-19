<?php

namespace App\Modules\StaffDashboard\Services;

use App\Jobs\ProcessFormSubmissionJob;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Support\FieldConditionEvaluator;
use App\Modules\VerificationSnapshot\Services\SnapshotService;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use App\Modules\WorkflowBuilder\Services\WorkflowProgressService;
use App\Modules\WorkflowBuilder\Support\WorkflowConditionEvaluator;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffSubmissionService
{
    public function __construct(
        protected SnapshotService $snapshot,
        protected NotificationService $notifier,
        protected StaffDashboardQueryService $queryService,
        protected StaffSubmissionDetailsService $detailsService
    ) {}

    private function stepWatchFields(WorkflowStep $step): array
    {
        $conditions = $step->step_conditions ?? [];
        if (is_string($conditions)) {
            $conditions = json_decode($conditions, true) ?: [];
        }

        $watchFields = $conditions['watch_fields'] ?? [];

        return array_values(array_filter((array) $watchFields));
    }

    private function shouldSkipStep(WorkflowStep $step, array $rowData): bool
    {
        $watchFields = $this->stepWatchFields($step);
        if (! empty($watchFields)) {
            foreach ($watchFields as $fieldName) {
                if (! array_key_exists($fieldName, $rowData)) {
                    continue;
                }

                $value = $rowData[$fieldName];
                if (is_string($value)) {
                    $decoded = $this->decodeJsonDeep($value);
                    if ($decoded['decoded']) {
                        $value = $decoded['value'];
                    }
                }

                if ($this->isNonEmpty($value)) {
                    return false;
                }
            }

            return true;
        }

        $conditions = is_array($step->step_conditions) ? $step->step_conditions : [];
        $branchCondition = isset($conditions['branch_condition']) && is_array($conditions['branch_condition'])
            ? $conditions['branch_condition']
            : null;

        if ($branchCondition !== null) {
            return ! WorkflowConditionEvaluator::evaluate($branchCondition, $rowData);
        }

        return false;
    }

    private function looksLikeJsonArray(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed !== '' && $trimmed[0] === '[' && substr($trimmed, -1) === ']';
    }

    private function isNonEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, fn ($item) => ! in_array($item, [null, '', []], true))) > 0;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return ! is_null($value);
    }

    /** -------- Metrics -------- */
    public function getMetricsForStaff(int $staffId): array
    {
        return $this->queryService->getMetricsForStaff($staffId);
    }

    public function approveStep($progressId, $actorId, $comments = null, array $attachments = []): array
    {
        return DB::transaction(function () use ($progressId, $actorId, $comments, $attachments) {
            // Lock the progress row first so concurrent requests serialize at the same step record.
            $progress = WorkflowStepProgress::with(['version', 'step.workflow', 'form'])
                ->where('id', $progressId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($progress->status, ['Pending', 'Waiting'], true)) {
                throw new \RuntimeException('Already processed');
            }

            // Lock the parent submission to prevent race conditions during concurrent approval attempts
            FormSubmission::where('id', $progress->submission_id)->lockForUpdate()->firstOrFail();

            // Verify user is authorized to approve (supports multi-approver OR condition)
            if (! $this->isAuthorizedToAct($progress, $actorId)) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You are not authorized to approve this step.'
                );
            }

            // Status update
            $progress->update([
                'status' => 'Approved',
                'comments' => $comments,
                'actor_id' => $actorId,
                'acted_at' => now(),
                'completed_at' => now(),
                'duration_seconds' => $progress->started_at ? now()->diffInSeconds($progress->started_at) : null,
            ]);

            // Attachments (after status update succeeds)
            try {
                $this->saveProgressAttachments($progress->id, (int) $actorId, $attachments);
            } catch (\Throwable $e) {
                \Log::error('[ProgressAttachment] Failed to save approval attachments', [
                    'progress_id' => $progress->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Generate snapshot after response (non-blocking, worker-independent)
            \App\Modules\VerificationSnapshot\Jobs\GenerateVerificationSnapshot::dispatchAfterResponse($progress->id);
            \Log::info('[Snapshot] Scheduled snapshot generation', ['progress_id' => $progress->id]);

            // Resolve step_group from frozen snapshot (falls back to live step if pre-version)
            $version = $progress->version;
            $stepsSnapshot = $version?->steps_snapshot ?? [];
            $currentGroup = $this->resolveStepGroup($progress, $stepsSnapshot);

            $this->advanceWorkflowIfNeeded($progress->workflow, $progress->submission_id, $currentGroup, $version);

            $allSteps = WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                ->where('submission_id', $progress->submission_id)->count();

            $doneSteps = WorkflowStepProgress::where('workflow_id', $progress->workflow_id)
                ->where('submission_id', $progress->submission_id)
                ->whereIn('status', ['Approved', 'Skipped'])->count();

            $isFinalApproval = $allSteps > 0 && $doneSteps === $allSteps;

            if ($isFinalApproval) {
                \DB::table('tbl_slots')->where('submission_id', $progress->submission_id)->update(['status' => 'Approved']);
                try {
                    $this->notifier->notifySubmissionCompletion($progress->workflow, (int) $progress->submission_id, 'approved');
                } catch (\Throwable $e) {
                    \Log::warning('[Email] Failed to send completion (approved)', ['submission_id' => $progress->submission_id, 'error' => $e->getMessage()]);
                }
            }

            return [
                'ok' => true,
                'final_approved' => $isFinalApproval,
                'submission_id' => (int) $progress->submission_id,
            ];
        });
    }

    public function rejectStep($progressId, $actorId, $comments = null, array $attachments = []): array
    {
        if (trim((string) $comments) === '') {
            throw ValidationException::withMessages([
                'comment' => ['A rejection comment is required.'],
            ]);
        }

        return DB::transaction(function () use ($progressId, $actorId, $comments, $attachments) {
            // Lock the progress row first so concurrent requests serialize at the same step record.
            $progress = WorkflowStepProgress::with(['version', 'step.workflow', 'actor.profile'])
                ->where('id', $progressId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($progress->status, ['Pending', 'Waiting'], true)) {
                throw new \RuntimeException('Already processed');
            }

            // Lock the parent submission to prevent race conditions during concurrent rejection attempts
            FormSubmission::where('id', $progress->submission_id)->lockForUpdate()->firstOrFail();

            // Verify user is authorized to reject (supports multi-approver OR condition)
            if (! $this->isAuthorizedToAct($progress, $actorId)) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'You are not authorized to reject this step.'
                );
            }

            $progress->update([
                'status' => 'Rejected',
                'comments' => $comments,
                'actor_id' => $actorId,
                'acted_at' => now(),
                'completed_at' => now(),
                'duration_seconds' => $progress->started_at ? now()->diffInSeconds($progress->started_at) : null,
            ]);

            // Attachments
            try {
                $this->saveProgressAttachments($progress->id, (int) $actorId, $attachments);
            } catch (\Throwable $e) {
                \Log::error('[ProgressAttachment] Failed to save rejection attachments', [
                    'progress_id' => $progress->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Snapshot (best-effort)
            try {
                \App\Modules\VerificationSnapshot\Jobs\GenerateVerificationSnapshot::dispatchAfterResponse($progress->id);
                \Log::info('[Snapshot] Scheduled snapshot generation', ['progress_id' => $progress->id]);
            } catch (\Throwable $e) {
                \Log::warning('[Snapshot] Failed', ['progress_id' => $progress->id, 'error' => $e->getMessage()]);
            }

            // Business rule: end workflow (unchanged)
            WorkflowStepProgress::where('submission_id', $progress->submission_id)
                ->whereIn('status', ['Pending', 'Waiting'])
                ->where('id', '>', $progress->id)
                ->update([
                    'status' => 'Rejected',
                    'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, started_at, NOW())'),
                    'updated_at' => now(),
                ]);

            \DB::table('tbl_slots')->where('submission_id', $progress->submission_id)
                ->where('status', 'Pending')->update(['status' => 'Rejected', 'updated_at' => now()]);

            try {
                $this->notifier->notifySubmissionCompletion($progress->workflow, (int) $progress->submission_id, 'rejected');
            } catch (\Throwable $e) {
                \Log::warning('[Email] Failed to send completion (rejected)', ['submission_id' => $progress->submission_id, 'error' => $e->getMessage()]);
            }

            return [
                'ok' => true,
                'final_approved' => false,
                'submission_id' => (int) $progress->submission_id,
            ];
        });
    }

    protected function advanceWorkflowIfNeeded($workflow, $submissionId, $currentGroup, ?WorkflowVersion $version = null)
    {
        $group = (int) $currentGroup;
        $payload = $this->resolveCanonicalPayload((int) $workflow->id, (int) $submissionId);

        // Use the version passed by the caller; if absent, attempt to look it up once (legacy path)
        if ($version === null) {
            $firstProgress = WorkflowStepProgress::with('version')->where('submission_id', $submissionId)->first();
            $version = $firstProgress?->version;
        }
        $stepsSnapshot = $version?->steps_snapshot ?? [];
        $snapshotByGroup = collect($stepsSnapshot)->groupBy('step_group');

        while (true) {
            if ($snapshotByGroup->isNotEmpty()) {
                $stepsInGroup = $snapshotByGroup->get($group) ?? collect();
                $stepIds = $stepsInGroup->pluck('id')->all();

                $statuses = WorkflowStepProgress::query()
                    ->where('submission_id', $submissionId)
                    ->whereIn('step_id', $stepIds)
                    ->pluck('status');
            } else {
                // Fallback for legacy
                $statuses = WorkflowStepProgress::query()
                    ->where('workflow_id', $workflow->id)
                    ->where('submission_id', $submissionId)
                    ->whereHas('step', fn ($q) => $q->where('step_group', $group))
                    ->pluck('status');
            }

            if ($statuses->isEmpty()) {
                break;
            }

            $hasActive = $statuses->contains(fn ($status) => in_array($status, ['Pending', 'Waiting'], true));
            if ($hasActive) {
                break;
            }

            $nextGroup = $group + 1;

            if ($snapshotByGroup->isNotEmpty()) {
                $nextGroupSteps = $snapshotByGroup->get($nextGroup) ?? collect();
                $nextStepIds = $nextGroupSteps->pluck('id')->all();

                $nextGroupStatuses = WorkflowStepProgress::query()
                    ->where('submission_id', $submissionId)
                    ->whereIn('step_id', $nextStepIds)
                    ->pluck('status');
            } else {
                $nextGroupStatuses = WorkflowStepProgress::query()
                    ->where('workflow_id', $workflow->id)
                    ->where('submission_id', $submissionId)
                    ->whereHas('step', fn ($q) => $q->where('step_group', $nextGroup))
                    ->pluck('status');
            }

            if ($nextGroupStatuses->isEmpty()) {
                break;
            }

            if ($snapshotByGroup->isNotEmpty()) {
                $nextGroupProgress = WorkflowStepProgress::query()
                    ->where('submission_id', $submissionId)
                    ->where('status', 'Waiting')
                    ->whereIn('step_id', $nextStepIds)
                    ->get();
            } else {
                $nextGroupProgress = WorkflowStepProgress::query()
                    ->with('step')
                    ->where('workflow_id', $workflow->id)
                    ->where('submission_id', $submissionId)
                    ->where('status', 'Waiting')
                    ->whereHas('step', fn ($q) => $q->where('step_group', $nextGroup))
                    ->get();
            }

            $pendingCount = 0;
            foreach ($nextGroupProgress as $progressRow) {
                // Determine step definition (from snapshot or DB)
                if ($snapshotByGroup->isNotEmpty()) {
                    $stepArr = collect($stepsSnapshot)->firstWhere('id', $progressRow->step_id);
                    $stepModel = new WorkflowStep;
                    $stepModel->forceFill($stepArr);
                } else {
                    $stepModel = $progressRow->step;
                }

                $status = $this->shouldSkipStep($stepModel, $payload) ? 'Skipped' : 'Pending';

                if ($status === 'Pending') {
                    $pendingCount++;
                }

                $progressRow->update([
                    'status' => $status,
                    'started_at' => now(),
                ]);
            }

            if ($pendingCount > 0) {
                try {
                    $form = $workflow->form()->first();
                    if ($form) {
                        $this->notifier->notifyNextSequentialApproversByGroup(
                            workflow: $workflow,
                            submissionId: (int) $submissionId,
                            currentGroup: $group,
                            form: $form
                        );
                    }
                } catch (\Throwable $e) {
                    \Log::warning('[Notify] Failed to notify next approvers', [
                        'workflow_id' => $workflow->id,
                        'submissionId' => $submissionId,
                        'step_group' => $nextGroup,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $group = $nextGroup;
        }
    }

    private function isAuthorizedToAct(WorkflowStepProgress $progress, int $actorId): bool
    {
        $version = $progress->version;
        $stepsSnapshot = $version ? (is_string($version->steps_snapshot) ? json_decode($version->steps_snapshot, true) : $version->steps_snapshot) : [];

        if (! empty($stepsSnapshot)) {
            $stepArr = collect($stepsSnapshot)->firstWhere('id', $progress->step_id);
            if (! $stepArr) {
                return false;
            }

            // Check direct assignment
            if ((int) ($stepArr['assigned_account_id'] ?? 0) === $actorId) {
                return true;
            }

            // Check multi-approvers
            $approvers = $stepArr['approvers'] ?? [];
            foreach ($approvers as $approver) {
                if ((int) ($approver['account_id'] ?? 0) === $actorId) {
                    return true;
                }
            }

            return false;
        }

        // Fallback for legacy
        $step = $progress->step;

        return (int) $step->assigned_account_id === $actorId
            || $step->approvers()->where('account_id', $actorId)->exists();
    }

    private function resolveCanonicalPayload(int $workflowId, int $submissionId): array
    {
        $submission = FormSubmission::query()->find($submissionId);

        if ($submission && is_array($submission->payload_json)) {
            return $submission->payload_json;
        }

        return [];
    }

    /**
     * Resolve the step_group for a given progress row.
     * Reads from the frozen snapshot when available; falls back to the live step relation for
     * pre-version (legacy) submissions.
     */
    private function resolveStepGroup(WorkflowStepProgress $progress, array $stepsSnapshot): int
    {
        if (! empty($stepsSnapshot)) {
            $stepArr = collect($stepsSnapshot)->firstWhere('id', $progress->step_id);
            if ($stepArr !== null) {
                return (int) ($stepArr['step_group'] ?? 0);
            }
        }

        // Legacy fallback: read from the live step relation
        return (int) ($progress->step?->step_group ?? 0);
    }

    public function getSubmissionDetailsForStaff(int $progressId, int $staffId): array
    {
        return $this->detailsService->getSubmissionDetailsForStaff($progressId, $staffId);
    }

    /** -------- Dashboard: Pending (search) -------- */
    public function getPendingRequestsForStaff(int $staffId, ?string $search = null): array
    {
        return $this->queryService->getPendingRequestsForStaff($staffId, $search);
    }

    /** -------- All Requests (status + q + pagination) -------- */
    public function getAllRequestsForStaff(
        int $staffId,
        ?string $status = null,
        ?string $q = null,
        int $perPage = 15
    ): array {
        return $this->queryService->getAllRequestsForStaff($staffId, $status, $q, $perPage);
    }

    /** -------- Staff submit a form (parity with Student) -------- */
    public function handleSubmission(Request $request, int $formId)
    {
        $form = Form::with('fields')->findOrFail($formId);
        $visibleFields = FieldConditionEvaluator::visibleFields($form->fields, $request->all());

        // Check submission limit first
        if ($form->submission_limit && $form->submission_limit > 0) {
            $existingCount = FormSubmission::query()
                ->where('form_id', $form->id)
                ->count();

            if ($existingCount >= $form->submission_limit) {
                return back()->withErrors([
                    'error' => 'This form has reached the maximum number of allowed submissions.',
                ]);
            }
        }

        // Dynamic validation
        $rules = [];
        foreach ($visibleFields as $field) {
            $key = $field->field_name;
            $isRangeMode = ($field->date_mode ?? 'single') === 'range';
            $isSlotBasedDate = (bool) ($field->use_slots || $field->require_facility || $isRangeMode);

            if ($field->data_type === 'date') {
                if (! $isSlotBasedDate) {
                    $rules[$key] = ($field->is_required ? 'required' : 'nullable').'|date';
                }

                continue;
            }

            if ($field->data_type === 'checkbox' && $field->options && count($field->options) > 1) {
                $rules[$key] = $field->is_required ? 'required|array' : 'nullable|array';
            } elseif ($field->data_type === 'table') {
                $rules[$key] = $field->is_required ? 'required|string' : 'nullable|string';
            } else {
                $rules[$key] = $field->is_required ? 'required' : 'nullable';
            }

            if ($field->data_type === 'file') {
                $rules[$key] .= '|file|mimes:jpg,png,pdf,docx|max:20480';
            }
        }

        $rules['slots'] = 'nullable|array';
        $rules['slots.*.date'] = 'required';
        $rules['slots.*.start_time'] = 'nullable|string';
        $rules['slots.*.end_time'] = 'nullable|string';
        $rules['slots.*.facility_id'] = 'nullable';

        $rules['date_ranges'] = 'nullable|array';
        $rules['date_ranges.*.from'] = 'required|date';
        $rules['date_ranges.*.to'] = 'required|date';

        $validated = $request->validate($rules);

        $data = [];
        foreach ($visibleFields as $field) {
            $key = $field->field_name;
            $val = $validated[$key] ?? null;
            $isRangeMode = ($field->date_mode ?? 'single') === 'range';
            $isSlotBasedDate = (bool) ($field->use_slots || $field->require_facility || $isRangeMode);

            if ($field->data_type === 'date') {
                if (! $isSlotBasedDate && ! empty($val)) {
                    $data[$key] = Carbon::parse($val)->format('Y-m-d');
                }

                continue;
            }

            // Handle file fields
            if ($field->data_type === 'file' && $request->hasFile($key)) {
                // Store on the default (private) disk so files are not publicly accessible.
                $data[$key] = $request->file($key)->store('submission-temp/submissions_uploads');
            }

            // Handle checkbox (normalize checkbox array to proper JSON)
            elseif ($field->data_type === 'checkbox') {
                if (is_null($val) || $val === '') {
                    $data[$key] = json_encode([], JSON_UNESCAPED_UNICODE);
                } else {
                    $normalized = $this->normalizeJsonField($val, $key);
                    $data[$key] = $normalized !== '' ? $normalized : json_encode([], JSON_UNESCAPED_UNICODE);
                }
            }

            // Handle radio button (normalize radio button value)
            elseif ($field->data_type === 'radio') {
                if (is_array($val) || is_object($val)) {
                    $data[$key] = json_encode($val, JSON_UNESCAPED_UNICODE); // JSON if array/object
                } else {
                    $data[$key] = (string) $val; // scalar value (string) directly
                }
            }

            // Handle multi-select (store as JSON)
            elseif ($field->data_type === 'select' && (is_array($val) || is_string($val))) {
                $normalized = $this->normalizeJsonField($val, $key);
                $data[$key] = $normalized !== '' ? $normalized : '';
            }

            // Handle other fields (comma-separated list if array)
            elseif (is_array($val)) {
                $data[$key] = implode(',', $val);
            } else {
                $data[$key] = $val; // store scalar values directly
            }
        }

        $accountId = (int) auth()->user()->account_id;
        $now = now();
        $schemaSnapshot = $form->loadMissing('permissions')->toSchemaArray();

        $data['account_id'] = $accountId;
        $data['created_at'] = $now;

        // Save slots JSON in runtime table
        $normalizedSlots = $this->normalizeSlotsInput($request->input('slots'));
        if ($normalizedSlots !== []) {
            $data['slots'] = json_encode($normalizedSlots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $normalizedRanges = $this->normalizeDateRangesInput($request->input('date_ranges'));
        if ($normalizedRanges !== []) {
            $data['date_ranges'] = json_encode($normalizedRanges, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $canonicalPayload = $this->buildCanonicalSubmissionPayload($data, $visibleFields);

        $workflow = Workflow::where('form_id', $form->id)->where('status', 'Active')->first();
        $workflowProgressPayloads = [];

        if ($workflow) {
            $version = WorkflowVersion::where('workflow_id', $workflow->id)
                ->where('is_current', true)
                ->first();

            if ($version) {
                $workflowProgressPayloads = app(WorkflowProgressService::class)
                    ->buildInitialProgress($version, $data);
            } else {
                // Legacy fallback for workflows that do not have a published version yet.
                $steps = $workflow->steps()->with('approvers')->orderBy('step_order')->get();

                foreach ($steps as $step) {
                    $hasApprovers = ! empty($step->assigned_account_id) || $step->approvers->isNotEmpty();
                    if (! $hasApprovers) {
                        continue;
                    }

                    $actorId = $step->assigned_account_id;
                    if (! $actorId) {
                        $firstApprover = $step->approvers->sortBy('order')->first();
                        $actorId = $firstApprover?->account_id;
                    }

                    if (! $actorId) {
                        continue;
                    }

                    $status = 'Waiting';
                    $startedAt = null;

                    if ((int) $step->step_group === 1) {
                        $status = 'Pending';
                        $startedAt = $now;
                    }

                    if ($this->shouldSkipStep($step, $data)) {
                        $status = 'Skipped';
                        $startedAt = $now;
                    }

                    $workflowProgressPayloads[] = [
                        'workflow_id' => $workflow->id,
                        'workflow_version_id' => null,
                        'step_id' => $step->id,
                        'actor_id' => $actorId,
                        'status' => $status,
                        'started_at' => $startedAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $currentProgressPayload = collect($workflowProgressPayloads)->firstWhere('status', 'Pending');
        $idempotencyKey = $this->buildSubmissionIdempotencyKey($request, $form->id, $accountId);
        $pendingReference = substr($idempotencyKey, 0, 12);

        ProcessFormSubmissionJob::dispatch(
            formId: $form->id,
            accountId: $accountId,
            payload: $canonicalPayload,
            schemaSnapshot: $schemaSnapshot,
            idempotencyKey: $idempotencyKey,
            currentStepId: $currentProgressPayload['step_id'] ?? null,
            currentActorId: $currentProgressPayload['actor_id'] ?? null,
            submittedAt: $now->toDateTimeString(),
            attachmentPayloads: [],
            slotPayloads: array_map(fn (array $slot): array => [
                'facility_id' => $slot['facility_id'] ?? null,
                'date' => $slot['date'] ?? null,
                'start_time' => $slot['start_time'] ?? null,
                'end_time' => $slot['end_time'] ?? null,
                'status' => 'Pending',
            ], $normalizedSlots),
            workflowProgressPayloads: $workflowProgressPayloads,
            workflowId: $workflow?->id,
            workflowType: $workflow?->workflow_type,
            pendingReference: $pendingReference,
            workflowVersionId: $version?->id,
        );

        return redirect()->route('staff-dashboard.index')
            ->with('submission_pending', [
                'status' => 'pending',
                'message' => 'Your submission is being processed.',
                'reference' => $pendingReference,
            ])
            ->with('success', "Your submission is being processed. Reference: {$pendingReference}");
    }

    private function buildSubmissionIdempotencyKey(Request $request, int $formId, int $accountId): string
    {
        $sessionId = $request->hasSession()
            ? (string) $request->session()->getId()
            : 'no-session';
        $clientTimestamp = (string) ($request->input('client_timestamp') ?? now()->toIso8601String());

        return hash('sha256', $accountId.$formId.$sessionId.$clientTimestamp.$clientTimestamp);
    }

    /** -------- Helpers -------- */
    private function saveProgressAttachments(int $progressId, int $uploaderAccountId, array $files): void
    {
        if (empty($files)) {
            return;
        }

        $dir = "submissions_comment_attachment/{$progressId}";
        $disk = \Storage::disk('private');

        foreach ($files as $file) {
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            $ext = $file->getClientOriginalExtension();
            $name = \Str::uuid()->toString().($ext ? ".{$ext}" : '');
            $path = $disk->putFileAs($dir, $file, $name);

            \App\Modules\WorkflowBuilder\Models\WorkflowStepProgressCommentAttachment::create([
                'progress_id' => $progressId,
                'uploaded_by' => $uploaderAccountId,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeJsonField($value, string $fieldKey): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value) && count($value) === 1 && is_string($value[0])) {
            $wrappedDecoded = $this->decodeJsonDeep($value[0]);
            if ($wrappedDecoded['decoded']) {
                $value = $wrappedDecoded['value'];
                \Log::info("🔧 [StaffSubmit] Unwrapped legacy JSON array wrapper for [{$fieldKey}]", [
                    'final_type' => gettype($value),
                ]);
            }
        }

        $decoded = $this->decodeJsonDeep($value);
        if ($decoded['decoded']) {
            $value = $decoded['value'];
        }

        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    /**
     * @param  mixed  $value
     * @return array{value:mixed,decoded:bool,original:mixed}
     */
    private function decodeJsonDeep($value): array
    {
        $current = $value;
        $decoded = false;

        for ($i = 0; $i < 6; $i++) {
            if (! is_string($current)) {
                break;
            }

            $trimmed = trim($current);
            if ($trimmed === '') {
                break;
            }

            $looksJsonLike =
                str_starts_with($trimmed, '[')
                || str_starts_with($trimmed, '{')
                || (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'));

            if (! $looksJsonLike) {
                break;
            }

            $next = json_decode($trimmed, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                break;
            }

            $current = $next;
            $decoded = true;
        }

        return [
            'value' => $current,
            'decoded' => $decoded,
            'original' => $value,
        ];
    }

    /**
     * @param  iterable<int, mixed>  $visibleFields
     * @return array<string, mixed>
     */
    private function buildCanonicalSubmissionPayload(array $rowData, iterable $visibleFields): array
    {
        $payload = [];

        foreach ($visibleFields as $field) {
            if (in_array($field->data_type, ['section', 'heading', 'image'], true)) {
                continue;
            }

            $fieldName = $field->field_name;
            if (! array_key_exists($fieldName, $rowData)) {
                continue;
            }

            $payload[$fieldName] = $this->normalizeCanonicalPayloadValue(
                (string) $field->data_type,
                $rowData[$fieldName]
            );
        }

        if (array_key_exists('slots', $rowData)) {
            $payload['slots'] = $this->decodeRuntimeJsonValue($rowData['slots']);
        }

        if (array_key_exists('date_ranges', $rowData)) {
            $payload['date_ranges'] = $this->decodeRuntimeJsonValue($rowData['date_ranges']);
        }

        return $payload;
    }

    private function normalizeCanonicalPayloadValue(string $dataType, mixed $value): mixed
    {
        if (in_array($dataType, ['checkbox', 'radio', 'select', 'table'], true)) {
            return $this->decodeRuntimeJsonValue($value);
        }

        return $value;
    }

    /**
     * @return array<int, array{date:?string,start_time:?string,end_time:?string,facility_id:?int}>
     */
    private function normalizeSlotsInput(mixed $rawSlots): array
    {
        if (is_string($rawSlots)) {
            $decodedSlots = json_decode($rawSlots, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rawSlots = $decodedSlots;
            }
        }

        if (! is_array($rawSlots) || empty($rawSlots)) {
            return [];
        }

        $normalizedSlots = [];
        $seenKeys = [];
        foreach ($rawSlots as $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $normalizedSlot = [
                'date' => isset($slot['date']) ? Carbon::parse($slot['date'])->format('Y-m-d') : null,
                'start_time' => ! empty($slot['start_time']) ? Carbon::parse($slot['start_time'])->format('H:i') : null,
                'end_time' => ! empty($slot['end_time']) ? Carbon::parse($slot['end_time'])->format('H:i') : null,
                'facility_id' => ! empty($slot['facility_id']) ? (int) $slot['facility_id'] : null,
            ];

            $slotKey = implode('|', [
                $normalizedSlot['date'] ?? '',
                $normalizedSlot['start_time'] ?? '',
                $normalizedSlot['end_time'] ?? '',
                $normalizedSlot['facility_id'] ?? '',
            ]);

            if (isset($seenKeys[$slotKey])) {
                continue;
            }

            $seenKeys[$slotKey] = true;
            $normalizedSlots[] = $normalizedSlot;
        }

        return $normalizedSlots;
    }

    /**
     * @return array<int, array{start_date:string,end_date:string}>
     */
    private function normalizeDateRangesInput(mixed $rawRanges): array
    {
        if (is_string($rawRanges)) {
            $decodedRanges = json_decode($rawRanges, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rawRanges = $decodedRanges;
            }
        }

        if (! is_array($rawRanges) || empty($rawRanges)) {
            return [];
        }

        $normalizedRanges = [];
        foreach ($rawRanges as $range) {
            if (! is_array($range)) {
                continue;
            }

            $from = $range['from'] ?? $range['start_date'] ?? null;
            $to = $range['to'] ?? $range['end_date'] ?? null;

            if (empty($from) || empty($to)) {
                continue;
            }

            $normalizedRanges[] = [
                'start_date' => Carbon::parse($from)->format('Y-m-d'),
                'end_date' => Carbon::parse($to)->format('Y-m-d'),
            ];
        }

        return array_values($normalizedRanges);
    }

    private function decodeRuntimeJsonValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
