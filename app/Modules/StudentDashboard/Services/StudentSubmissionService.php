<?php

namespace App\Modules\StudentDashboard\Services;

use App\Actions\FormBuilder\WriteCanonicalSubmissionAction;
use App\Jobs\ProcessFormSubmissionJob;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Models\Slot;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\FormBuilder\Support\FieldConditionEvaluator;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use App\Modules\WorkflowBuilder\Services\WorkflowProgressService;
use App\Modules\WorkflowBuilder\Support\WorkflowConditionEvaluator;
use App\Services\NotificationService;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StudentSubmissionService
{
    public function __construct(
        protected NotificationService $notifier
    ) {}

    public function hasReachedSubmissionLimit(Form $form, int $accountId): bool
    {
        $limit = (int) ($form->submission_limit ?? 0);
        if ($limit <= 0) {
            return false;
        }

        $existingCount = FormSubmission::query()
            ->where('form_id', $form->id)
            ->where('account_id', $accountId)
            ->count();

        return $existingCount >= $limit;
    }

    /**
     * Batch-fetch submission counts per form for a user.
     *
     * @param  int[]  $formIds
     * @return array<int, int> keyed by form_id
     */
    public function getSubmissionCountsForForms(array $formIds, int $accountId): array
    {
        if (empty($formIds)) {
            return [];
        }

        return FormSubmission::query()
            ->whereIn('form_id', $formIds)
            ->where('account_id', $accountId)
            ->selectRaw('form_id, COUNT(*) as total')
            ->groupBy('form_id')
            ->pluck('total', 'form_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Check the submission limit using a pre-fetched count (no DB query).
     */
    public function hasReachedSubmissionLimitWithCount(Form $form, int $count): bool
    {
        $limit = (int) ($form->submission_limit ?? 0);
        if ($limit <= 0) {
            return false;
        }

        return $count >= $limit;
    }

    public function hasReachedGlobalSubmissionLimit(Form $form): bool
    {
        $limit = (int) ($form->submission_limit ?? 0);
        if ($limit <= 0) {
            return false;
        }

        return FormSubmission::query()
            ->where('form_id', $form->id)
            ->count() >= $limit;
    }

    /**
     * @return array{
     *   can_submit: bool,
     *   code: string|null,
     *   message: string|null,
     *   has_permission: bool,
     *   has_active_workflow: bool,
     *   accepts_submissions: bool,
     *   submission_limit_reached: bool
     * }
     */
    public function getFormSubmissionAvailability(Form $form, int $accountId): array
    {
        $hasPermission = $this->userCanAccessForm($form, $accountId);
        $hasActiveWorkflow = Workflow::query()
            ->where('form_id', $form->id)
            ->where('status', 'Active')
            ->exists();
        $acceptsSubmissions = strcasecmp((string) ($form->status ?? ''), 'Active') === 0
            && (bool) $form->is_locked;
        $submissionLimitReached = $this->hasReachedGlobalSubmissionLimit($form);

        $code = null;
        $message = null;

        if (! $hasPermission) {
            $code = 'no_permission';
            $message = "You don't have access to this feature. Contact your administrator.";
        } elseif (! $acceptsSubmissions) {
            $code = 'form_locked';
            $message = 'This form is currently locked and not accepting submissions.';
        } elseif (! $hasActiveWorkflow) {
            $code = 'workflow_inactive';
            $message = 'This form does not have an active workflow assigned.';
        } elseif ($submissionLimitReached) {
            $code = 'submission_limit_reached';
            $message = 'This form has reached the maximum number of allowed submissions.';
        }

        return [
            'can_submit' => $code === null,
            'code' => $code,
            'message' => $message,
            'has_permission' => $hasPermission,
            'has_active_workflow' => $hasActiveWorkflow,
            'accepts_submissions' => $acceptsSubmissions,
            'submission_limit_reached' => $submissionLimitReached,
        ];
    }

    private function userCanAccessForm(Form $form, int $accountId): bool
    {
        if (! $form->relationLoaded('permissions')) {
            $form->load('permissions');
        }

        if ($form->permissions->isEmpty()) {
            return true;
        }

        return $form->permissions()
            ->whereIn('tbl_permission.id', function ($sub) use ($accountId) {
                $sub->select('tbl_role_permission.permission_id')
                    ->from('tbl_user_role')
                    ->join('tbl_role_permission', 'tbl_user_role.role_id', '=', 'tbl_role_permission.role_id')
                    ->where('tbl_user_role.account_id', $accountId);
            })
            ->exists();
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function getPaginatedSubmissionSummaries(
        int $accountId,
        string $status = 'all',
        string $search = '',
        int $page = 1,
        int $perPage = 10,
    ): array {
        $normalizedSearch = trim($search);
        $requestedStatus = strtolower(trim($status));

        $submissionsQuery = FormSubmission::query()
            ->select([
                'id',
                'form_id',
                'account_id',
                'current_workflow_status',
                'current_step_id',
                'submitted_at',
                'created_at',
                'updated_at',
            ])
            ->with(['form:id,form_name,form_code', 'slots.facility'])
            ->where('account_id', $accountId)
            ->when($normalizedSearch !== '', function ($query) use ($normalizedSearch) {
                $query->whereHas('form', function ($formQuery) use ($normalizedSearch) {
                    $formQuery->where('form_name', 'like', '%'.$normalizedSearch.'%')
                        ->orWhere('form_code', 'like', '%'.$normalizedSearch.'%');
                });
            })
            ->when($requestedStatus !== '' && $requestedStatus !== 'all', function ($query) use ($requestedStatus) {
                if ($requestedStatus === 'rejected') {
                    $query->where(function ($statusQuery) {
                        $statusQuery->whereRaw('LOWER(COALESCE(current_workflow_status, "")) LIKE ?', ['rejec%'])
                            ->orWhereRaw('LOWER(COALESCE(current_workflow_status, "")) LIKE ?', ['%auto%reject%']);
                    });

                    return;
                }

                if ($requestedStatus === 'approved') {
                    $query->whereRaw('LOWER(COALESCE(current_workflow_status, "")) LIKE ?', ['appr%']);

                    return;
                }

                if ($requestedStatus === 'pending') {
                    $query->whereRaw('LOWER(COALESCE(current_workflow_status, "")) LIKE ?', ['pend%']);

                    return;
                }

                if ($requestedStatus === 'revision') {
                    $query->whereRaw('LOWER(COALESCE(current_workflow_status, "")) LIKE ?', ['%revision%']);

                    return;
                }

                $query->whereRaw('LOWER(COALESCE(current_workflow_status, "")) = ?', [$requestedStatus]);
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $paginator = $submissionsQuery->paginate($perPage, ['*'], 'page', $page);
        $submissions = collect($paginator->items());

        if ($submissions->isEmpty()) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ];
        }

        $progressBySubmission = WorkflowStepProgress::query()
            ->whereIn('submission_id', $submissions->pluck('id'))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'submission_id',
                'step_id',
                'status',
                'action_taken',
                'acted_at',
                'updated_at',
            ])
            ->groupBy('submission_id');

        $attachmentCounts = SubmissionAttachment::query()
            ->selectRaw('submission_id, COUNT(*) as total')
            ->whereIn('submission_id', $submissions->pluck('id'))
            ->groupBy('submission_id')
            ->pluck('total', 'submission_id');

        $rows = $submissions
            ->map(function (FormSubmission $submission) use ($attachmentCounts, $progressBySubmission) {
                $progresses = $progressBySubmission->get($submission->id, collect());
                $latestProgress = $progresses->first();
                $totalSteps = $progresses->count();
                $approvedSteps = $progresses->whereIn('status', ['Approved', 'Skipped'])->count();
                $latestStatus = (string) ($latestProgress?->status ?? $submission->current_workflow_status ?? 'Pending');

                return [
                    'id' => (int) $submission->id,
                    'form_id' => (int) $submission->form_id,
                    'form_name' => $submission->form?->form_name,
                    'form_code' => $submission->form?->form_code,
                    'status' => $latestStatus,
                    'submitted_at' => $submission->submitted_at ?? $submission->created_at,
                    'updated_at' => $latestProgress?->updated_at ?? $submission->updated_at ?? $submission->created_at,
                    'progress' => $totalSteps > 0 ? (int) round(($approvedSteps / $totalSteps) * 100) : 0,
                    'current_step' => $latestProgress?->step_id ?? $submission->current_step_id,
                    'last_action' => $latestProgress?->action_taken,
                    'last_action_at' => $latestProgress?->acted_at,
                    'attachments' => (int) ($attachmentCounts[$submission->id] ?? 0),
                    'slots' => $submission->slots
                        ->map(fn (Slot $slot): array => [
                            'date' => optional($slot->date)->toDateString(),
                            'start_time' => $slot->start_time,
                            'end_time' => $slot->end_time,
                            'facility' => $slot->facility?->name,
                        ])
                        ->values(),
                ];
            })
            ->map(function (array $row): array {
                $row['status_normalized'] = $this->normalizeEditStatus($row['status']);

                return $row;
            })
            ->values();

        return [
            'data' => $rows->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @return array{total: int, approved: int, pending: int, rejected: int, revision: int}
     */
    public function getSubmissionMetrics(int $accountId): array
    {
        return Cache::remember(
            "auflow:dashboard:metrics:student:{$accountId}",
            now()->addMinutes(5),
            function () use ($accountId) {
                $counts = [
                    'total' => 0,
                    'approved' => 0,
                    'pending' => 0,
                    'rejected' => 0,
                    'revision' => 0,
                ];

                $statusCounts = FormSubmission::query()
                    ->where('account_id', $accountId)
                    ->selectRaw('LOWER(COALESCE(current_workflow_status, "")) as status_key, COUNT(*) as total')
                    ->groupByRaw('LOWER(COALESCE(current_workflow_status, ""))')
                    ->pluck('total', 'status_key');

                foreach ($statusCounts as $rawStatus => $total) {
                    $count = (int) $total;
                    $counts['total'] += $count;

                    $normalizedStatus = $this->normalizeEditStatus((string) $rawStatus);
                    if ($normalizedStatus === 'approved') {
                        $counts['approved'] += $count;
                    } elseif ($normalizedStatus === 'pending') {
                        $counts['pending'] += $count;
                    } elseif ($normalizedStatus === 'revision') {
                        $counts['revision'] += $count;
                    } elseif (in_array($normalizedStatus, ['rejected', 'auto-rejected'], true)) {
                        $counts['rejected'] += $count;
                    }
                }

                return $counts;
            }
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSubmissionEditPayload(int $formId, int $submissionId, int $accountId): ?array
    {
        $form = Form::with('fields')->find($formId);
        if (! $form) {
            return null;
        }

        $submission = $this->findCanonicalSubmissionRecord($formId, $submissionId, ['attachments', 'slots']);
        if (! $submission || (int) $submission->account_id !== $accountId) {
            return null;
        }

        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];
        $fields = [];
        foreach ($form->fields as $field) {
            $fields[$field->field_name] = $payload[$field->field_name] ?? null;
        }

        return [
            'id' => (int) $submission->id,
            'form_id' => $form->id,
            'form_name' => $form->form_name,
            'description' => $form->description,
            'form_fields' => $form->fields->map(fn ($field): array => [
                'id' => $field->id,
                'field_name' => $field->field_name,
                'label' => $field->label,
                'data_type' => $field->data_type,
                'is_required' => $field->is_required,
                'options' => $field->options,
                'options_meta' => $field->options_meta,
                'field_order' => $field->field_order,
                'help_text' => $field->help_text,
                'use_slots' => $field->use_slots ?? false,
                'require_facility' => $field->require_facility ?? false,
                'date_mode' => $field->date_mode ?? 'single',
            ]),
            'fields' => $fields,
            'attachments' => $submission->attachments->map(fn (SubmissionAttachment $attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'file_path' => $attachment->file_path,
            ]),
            'slots' => $this->extractSlots($submission),
            'date_ranges' => $this->extractDateRanges($submission),
            'update_route_name' => 'student-dashboard.submission.update',
        ];
    }

    public function studentOwnsSubmission(int $formId, int $submissionId, int $accountId): bool
    {
        return FormSubmission::query()
            ->whereKey($submissionId)
            ->where('form_id', $formId)
            ->where('account_id', $accountId)
            ->exists();
    }

    private function stepWatchFields(\App\Modules\WorkflowBuilder\Models\WorkflowStep $step): array
    {
        $cond = $step->step_conditions ?? [];
        if (is_string($cond)) {
            $cond = json_decode($cond, true) ?: [];
        }
        $wf = $cond['watch_fields'] ?? [];

        return array_values(array_filter((array) $wf));
    }

    /** Determine if ALL watched fields are empty in $rowData (runtime row payload we insert). */
    private function shouldSkipStep(\App\Modules\WorkflowBuilder\Models\WorkflowStep $step, array $rowData): bool
    {
        $watch = $this->stepWatchFields($step);
        if (! empty($watch)) {
            foreach ($watch as $field) {
                if (! array_key_exists($field, $rowData)) {
                    continue;
                }
                $val = $rowData[$field];

                if (is_string($val) && $this->looksLikeJsonArray($val)) {
                    $decoded = json_decode($val, true);
                    $val = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
                }

                if ($this->isNonEmpty($val)) {
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

    private function looksLikeJsonArray(string $v): bool
    {
        $t = trim($v);

        return $t !== '' && $t[0] === '[' && substr($t, -1) === ']';
    }

    private function isNonEmpty(mixed $val): bool
    {
        if (is_array($val)) {
            return count(array_filter($val, fn ($x) => ! in_array($x, [null, '', []], true))) > 0;
        }
        if (is_string($val)) {
            return trim($val) !== '';
        }

        return ! is_null($val);
    }

    /**
     * @return array<int, array{date:?string,start_time:?string,end_time:?string,facility_id:?int}>
     */
    private function normalizeSlotsInput(mixed $rawSlots): array
    {
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
     * @param  array<int, array{date:?string,start_time:?string,end_time:?string,facility_id:?int}>  $normalizedSlots
     * @param  array<int, int>  $ignoreSubmissionIds
     */
    private function assertNoSlotConflicts(array $normalizedSlots, array $ignoreSubmissionIds = []): void
    {
        foreach ($normalizedSlots as $slot) {
            if (empty($slot['date']) || empty($slot['start_time']) || empty($slot['end_time'])) {
                continue;
            }

            $conflictExists = Slot::query()
                ->where('date', $slot['date'])
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->where('status', '!=', 'Rejected')
                ->when(! empty($ignoreSubmissionIds), fn ($q) => $q->whereNotIn('submission_id', $ignoreSubmissionIds))
                ->when(
                    is_null($slot['facility_id']),
                    fn ($q) => $q->whereNull('facility_id'),
                    fn ($q) => $q->where('facility_id', $slot['facility_id'])
                )
                ->where('start_time', '<', $slot['end_time'])
                ->where('end_time', '>', $slot['start_time'])
                ->exists();

            if ($conflictExists) {
                throw ValidationException::withMessages([
                    'slots' => [
                        "The selected slot {$slot['date']} {$slot['start_time']}-{$slot['end_time']} is already booked.",
                    ],
                ]);
            }
        }
    }

    public function handleSubmission(Request $request, int $formId)
    {
        Log::info('[Submission] Incoming request', [
            'form_id' => $formId,
            'account_id' => (int) auth()->user()->account_id,
            'has_attachments' => $request->hasFile('attachments'),
            'slots_count' => is_array($request->input('slots')) ? count($request->input('slots')) : 0,
        ]);

        $form = Form::with('fields')->findOrFail($formId);
        $schemaSnapshot = $form->loadMissing('permissions')->toSchemaArray();
        Log::info('[Submission] Loaded form', ['form_id' => $form->id, 'form_name' => $form->form_name]);

        $accountId = (int) auth()->user()->account_id;
        $availability = $this->getFormSubmissionAvailability($form, $accountId);
        if (! $availability['can_submit']) {
            return back()->with('error', $availability['message'] ?? 'Unable to submit this form.');
        }

        $visibleFields = FieldConditionEvaluator::visibleFields($form->fields, $request->all());

        // Validation is handled by StoreFormSubmissionRequest (FormRequest) before the controller
        // calls this service. The service trusts its input.
        $validated = $request->all();

        // Normalize field values
        $data = [];
        foreach ($visibleFields as $field) {
            $key = $field->field_name;
            $isRangeMode = ($field->date_mode ?? 'single') === 'range';
            $isSlotBasedDate = (bool) ($field->use_slots || $field->require_facility || $isRangeMode);

            // Date fields: store as Y-m-d format
            if ($field->data_type === 'date') {
                if (! $isSlotBasedDate && array_key_exists($key, $validated) && $validated[$key]) {
                    try {
                        $data[$key] = Carbon::parse($validated[$key])->format('Y-m-d');
                    } catch (\Exception $e) {
                        $data[$key] = null;
                    }
                }

                continue;
            }

            if (array_key_exists($key, $validated)) {
                $val = $validated[$key];

                if ($field->data_type === 'file' && $request->hasFile($key)) {
                    // Store on the default (private) disk so files are not publicly accessible.
                    $data[$key] = $request->file($key)->store('submission-temp/submissions_uploads');
                }

                // Checkbox: normalize to clean JSON (prevent double encoding)
                elseif ($field->data_type === 'checkbox') {
                    $data[$key] = $this->normalizeJsonField($val, $key);
                }

                // Radio: normalize to clean JSON
                elseif ($field->data_type === 'radio') {
                    $data[$key] = $this->normalizeJsonField($val, $key);
                }

                // Select: normalize to clean JSON
                elseif ($field->data_type === 'select') {
                    $data[$key] = $this->normalizeJsonField($val, $key);
                }

                // Table: normalize to clean JSON (array of row objects)
                elseif ($field->data_type === 'table') {
                    $data[$key] = $this->normalizeJsonField($request->input($key, $val), $key);
                }

                // Fallback: scalar fields
                elseif (is_array($val)) {
                    $data[$key] = implode(',', $val);
                } else {
                    $data[$key] = $val;
                }
            }
        }

        $now = now();
        $data['account_id'] = $accountId;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        // Handle slots (tbl_slots + runtime JSON)
        $rawSlots = $request->input('slots');
        $normalizedSlots = $this->normalizeSlotsInput($rawSlots);
        if (! empty($normalizedSlots)) {
            $this->assertNoSlotConflicts($normalizedSlots);
            $data['slots'] = json_encode($normalizedSlots);
        }

        // NEW: Handle date_ranges -> runtime JSON column
        $rawRanges = $request->input('date_ranges');
        if (is_array($rawRanges) && count($rawRanges) > 0) {
            $normalizedRanges = [];
            foreach ($rawRanges as $r) {
                $from = $r['from'] ?? $r['start_date'] ?? null;
                $to = $r['to'] ?? $r['end_date'] ?? null;
                if ($from && $to) {
                    $normalizedRanges[] = [
                        'start_date' => Carbon::parse($from)->format('Y-m-d'),
                        'end_date' => Carbon::parse($to)->format('Y-m-d'),
                    ];
                }
            }
            if (! empty($normalizedRanges)) {
                $data['date_ranges'] = json_encode(array_values($normalizedRanges));
            }
        }

        Log::info('[Submission] Normalized JSON columns (student path)', [
            'slots_set' => array_key_exists('slots', $data),
            'date_ranges_set' => array_key_exists('date_ranges', $data),
            'slots_count' => isset($data['slots']) ? count(json_decode($data['slots'], true) ?? []) : 0,
            'date_ranges_count' => isset($data['date_ranges']) ? count(json_decode($data['date_ranges'], true) ?? []) : 0,
        ]);

        $canonicalPayload = $this->buildCanonicalSubmissionPayload($data, $visibleFields);

        $attachmentPayloads = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('submission-temp/submissions_attachments');
                $attachmentPayloads[] = [
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'uploaded_by' => $accountId,
                ];
            }
        }

        /** @var \App\Modules\WorkflowBuilder\Models\Workflow|null $workflow */
        $workflow = Workflow::where('form_id', $form->id)
            ->where('status', 'Active')
            ->first();

        $workflowProgressPayloads = [];

        if ($workflow) {
            $version = WorkflowVersion::where('workflow_id', $workflow->id)
                ->where('is_current', true)
                ->first();

            if ($version) {
                $workflowProgressPayloads = app(WorkflowProgressService::class)
                    ->buildInitialProgress($version, $data);
            } else {
                throw new \App\Exceptions\WorkflowVersionNotFoundException(
                    'No published workflow version found for this form. Please contact your administrator.'
                );
            }
        }

        $payloadWithAttachments = $canonicalPayload;
        if (! empty($attachmentPayloads)) {
            $payloadWithAttachments['attachments'] = array_map(fn (array $attachment): array => [
                'original_name' => $attachment['original_name'],
                'file_path' => $attachment['file_path'],
                'mime_type' => $attachment['mime_type'],
            ], $attachmentPayloads);
        }

        $currentProgressPayload = collect($workflowProgressPayloads)->firstWhere('status', 'Pending');
        $idempotencyKey = $this->buildSubmissionIdempotencyKey($request, $form->id, $accountId);
        $pendingReference = substr($idempotencyKey, 0, 12);

        ProcessFormSubmissionJob::dispatch(
            formId: $form->id,
            accountId: $accountId,
            payload: $payloadWithAttachments,
            schemaSnapshot: $schemaSnapshot,
            idempotencyKey: $idempotencyKey,
            currentStepId: $currentProgressPayload['step_id'] ?? null,
            currentActorId: $currentProgressPayload['actor_id'] ?? null,
            submittedAt: $now->toDateTimeString(),
            attachmentPayloads: $attachmentPayloads,
            slotPayloads: array_map(fn (array $slot): array => [
                'facility_id' => $slot['facility_id'],
                'date' => $slot['date'],
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

        return redirect()
            ->route($this->submissionRedirectRoute($request))
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

    private function submissionRedirectRoute(Request $request): string
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        if (str_starts_with($routeName, 'user.')) {
            return 'user.forms';
        }

        if (str_starts_with($routeName, 'staff-dashboard.')) {
            return 'staff-dashboard.index';
        }

        return 'student-dashboard.index';
    }

    private function bootstrapPendingGroups(Workflow $workflow, int $submissionId): void
    {
        $group = 1;
        while (true) {
            $active = WorkflowStepProgress::where('workflow_id', $workflow->id)
                ->where('submission_id', $submissionId)
                ->whereHas('step', fn ($q) => $q->where('step_group', $group))
                ->pluck('status');

            if ($active->isEmpty()) {
                break;
            }

            $allDone = $active->every(fn ($s) => in_array($s, ['Skipped', 'Approved', 'Rejected'], true)) &&
                       ! $active->contains('Pending') &&
                       ! $active->contains('Waiting');

            if (! $allDone) {
                break;
            }

            $updated = WorkflowStepProgress::where('workflow_id', $workflow->id)
                ->where('submission_id', $submissionId)
                ->where('status', 'Waiting')
                ->whereHas('step', fn ($q) => $q->where('step_group', $group + 1))
                ->update([
                    'status' => 'Pending',
                    'started_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                break;
            }
            $group++;
        }
    }

    /** ---------------- Submission Details for Viewer ---------------- */
    public function getSubmissionDetails(
        int $formId,
        int $submissionId,
        int $accountId,
        string $progressAttachmentDownloadRoute = 'student-dashboard.progress-attachments.download'
    ): ?array {
        $form = Form::with('fields')->find($formId);
        if (! $form) {
            return null;
        }

        $submission = $this->findCanonicalSubmissionRecord(
            $formId,
            $submissionId,
            ['attachments', 'slots.facility', 'submitter.profile']
        );

        if (! $submission || (int) $submission->account_id !== $accountId) {
            return null;
        }

        $attachments = $submission->attachments;
        $slots = $this->extractSlots($submission);
        $dateRanges = $this->extractDateRanges($submission);
        $fieldsWithLabels = $this->buildSubmissionFields($submission, $form);

        $workflowId = WorkflowStepProgress::query()
            ->where('submission_id', $submission->id)
            ->orderByDesc('id')
            ->value('workflow_id');

        $progresses = $workflowId
            ? WorkflowStepProgress::query()
                ->with([
                    'step.assignedUser.profile',
                    'step.approvers.user.profile',
                    'actor.profile',
                    'commentAttachments.uploader.profile',
                ])
                ->join('tbl_workflow_step as ws', 'ws.id', '=', 'tbl_workflow_step_progress.step_id')
                ->where('tbl_workflow_step_progress.submission_id', $submission->id)
                ->where('tbl_workflow_step_progress.workflow_id', $workflowId)
                ->orderBy('ws.step_group')
                ->orderBy('ws.step_order')
                ->select('tbl_workflow_step_progress.*')
                ->get()
                ->map(function ($p) use ($progressAttachmentDownloadRoute) {
                    $seconds = $p->duration_seconds;
                    if ($seconds === null && $p->acted_at) {
                        $start = $p->started_at ?? $p->created_at;
                        if ($start) {
                            $seconds = $p->acted_at->diffInSeconds($start);
                        }
                    }
                    $seconds = $seconds !== null ? (int) $seconds : null;

                    $atts = $p->commentAttachments->map(function ($a) use ($progressAttachmentDownloadRoute) {
                        $name = $a->uploader?->profile
                            ? trim(($a->uploader->profile->first_name ?? '').' '.($a->uploader->profile->last_name ?? ''))
                            : ($a->uploader->name ?? 'Unknown');

                        return [
                            'id' => $a->id,
                            'original_name' => $a->original_name,
                            'mime_type' => $a->mime_type,
                            'size_bytes' => $a->size_bytes,
                            'uploaded_by_id' => $a->uploaded_by,
                            'uploaded_by_name' => $name ?: 'Unknown',
                            'uploaded_at' => optional($a->created_at)?->toIso8601String(),
                            'download_url' => route($progressAttachmentDownloadRoute, $a->id),
                            'preview_url' => route('staff-dashboard.progress-attachments.preview', $a->id),
                        ];
                    })->values()->all();

                    $stepAssignedUser = $p->step?->assignedUser?->profile?->full_name;
                    $approverNames = collect($p->step?->approvers ?? [])
                        ->map(function ($approver) {
                            $fullName = trim((string) ($approver->user?->profile?->first_name ?? '').' '.(string) ($approver->user?->profile?->last_name ?? ''));

                            return $fullName !== '' ? $fullName : ($approver->user?->username ?? null);
                        })
                        ->filter()
                        ->values();

                    $assignee = $stepAssignedUser;
                    if (! $assignee) {
                        if ($approverNames->count() === 1) {
                            $assignee = $approverNames->first();
                        } elseif ($approverNames->count() > 1) {
                            $assignee = $approverNames->first().' +'.($approverNames->count() - 1).' more';
                        } else {
                            $assignee = 'Approver role';
                        }
                    }

                    $statusKey = $this->normalizeTrackerStatus((string) $p->status);

                    return [
                        'step' => $p->step->step_name ?? 'Step '.$p->step_id,
                        'status' => $p->status,
                        'status_key' => $statusKey,
                        'status_label' => $this->trackerStatusLabel($statusKey),
                        'assignee' => $assignee,
                        'actor' => $p->actor?->profile?->full_name
                                            ?? $p->step?->assignedUser?->profile?->full_name
                                            ?? null,
                        'acted_at' => optional($p->acted_at)?->toIso8601String(),
                        'comments' => $p->comments,
                        'duration' => $seconds,
                        'duration_human' => $seconds
                            ? CarbonInterval::seconds($seconds)->cascade()->forHumans()
                            : null,
                        'attachments' => $atts,
                    ];
                })
                ->values()
            : collect();

        $totalWorkflowSteps = $progresses->count();
        $progresses = $progresses->values()->map(function (array $step, int $index) use ($totalWorkflowSteps): array {
            $step['step_index'] = $index + 1;
            $step['total_steps'] = $totalWorkflowSteps;

            return $step;
        });

        $totalDurationSeconds = $progresses->sum(fn ($p) => $p['duration'] ?? 0);
        $totalDurationHuman = $totalDurationSeconds > 0
            ? CarbonInterval::seconds($totalDurationSeconds)->cascade()->forHumans()
            : null;

        $chain = $this->buildRevisionHistory($submission, $workflowId);
        $isLatest = (bool) $submission->is_latest_revision;
        $history = $chain['history'];

        $snapshot = Snapshot::query()
            ->where('submission_id', $submission->id)
            ->orderByDesc('id')
            ->first();

        $submitter = $submission->submitter;
        $submitterName = trim((string) ($submitter?->profile?->first_name ?? '').' '.(string) ($submitter?->profile?->last_name ?? ''));
        if ($submitterName === '') {
            $submitterName = $submitter?->username ?? 'Unknown';
        }

        return [
            'id' => (int) $submission->id,
            'form_id' => $form->id,
            'form_code' => $form->form_code,
            'form_name' => $form->form_name,
            'created_at' => optional($submission->submitted_at ?? $submission->created_at)?->toIso8601String(),
            'updated_at' => optional($submission->updated_at ?? $submission->submitted_at ?? $submission->created_at)?->toIso8601String(),
            'form_fields' => collect($this->schemaFieldsForSubmission($submission, $form))->map(fn (array $field): array => [
                'id' => $field['id'] ?? null,
                'field_name' => $field['field_name'],
                'label' => $field['label'] ?? $field['field_name'],
                'data_type' => $field['data_type'] ?? 'text',
                'is_required' => (bool) ($field['is_required'] ?? false),
                'options' => $field['options'] ?? [],
                'options_meta' => $field['options_meta'] ?? [],
                'field_order' => (int) ($field['field_order'] ?? 0),
                'help_text' => $field['help_text'] ?? null,
                'use_slots' => (bool) ($field['use_slots'] ?? false),
                'require_facility' => (bool) ($field['require_facility'] ?? false),
                'date_mode' => $field['date_mode'] ?? 'single',
                'field_options' => $field['field_options'] ?? [],
            ])->values(),

            'fields' => $fieldsWithLabels,
            'attachments' => $attachments,
            'slots' => $slots,
            'date_ranges' => $dateRanges,

            'workflow' => $progresses->toArray(),
            'workflow_duration' => [
                'total_seconds' => $totalDurationSeconds,
                'total_human' => $totalDurationHuman,
            ],

            'snapshot' => $snapshot ? [
                'exists' => true,
                'status' => $snapshot->status,
                'approved_at' => optional($snapshot->approved_at)?->toIso8601String(),
                'short_code' => substr($snapshot->public_id, -6),
                'url' => route('snapshots.show', $snapshot->public_id),
                'approved_by' => $snapshot->approved_by ?? null,
                'comment' => $snapshot->comment ?? null,
            ] : ['exists' => false],

            'submitter' => $submitterName,
            'is_latest' => $isLatest,
            'history' => $history,
        ];
    }

    public function updateSubmission(
        Request $request,
        int $formId,
        int $submissionId,
        string $viewRouteName = 'student-dashboard.submission.view'
    ) {
        Log::info('[UpdateSubmission] Incoming request', [
            'form_id' => $formId,
            'submission_id' => $submissionId,
            'account_id' => (int) auth()->user()->account_id,
            'has_attachments' => $request->hasFile('attachments'),
            'slots_count' => is_array($request->input('slots')) ? count($request->input('slots')) : 0,
        ]);

        $this->assertSubmissionEditable($formId, $submissionId, (int) auth()->user()->account_id);

        $form = Form::with('fields')->findOrFail($formId);
        $schemaSnapshot = $form->loadMissing('permissions')->toSchemaArray();
        $oldSubmission = $this->findCanonicalSubmissionRecord($formId, $submissionId, ['attachments', 'slots']);

        if (! $oldSubmission) {
            abort(404, 'Submission not found or unauthorized.');
        }

        $oldPayload = is_array($oldSubmission->payload_json) ? $oldSubmission->payload_json : [];

        Log::info('[UpdateSubmission] Loaded existing canonical submission', [
            'submission_id' => $oldSubmission->id,
        ]);

        // Validation
        $rules = [];
        foreach ($form->fields as $field) {
            if (in_array($field->data_type, ['section', 'heading', 'image'], true)) {
                continue;
            }

            $key = $field->field_name;
            $isRangeMode = ($field->date_mode ?? 'single') === 'range';
            $isSlotBasedDate = (bool) ($field->use_slots || $field->require_facility || $isRangeMode);

            // Date fields: validate as date
            if ($field->data_type === 'date') {
                if (! $isSlotBasedDate) {
                    $rules[$key] = 'nullable|date';
                }

                continue;
            }

            if ($field->data_type === 'checkbox' && $field->options && count($field->options) > 1) {
                $rules[$key] = 'nullable|array';
            } elseif ($field->data_type === 'table') {
                // Table field: sent as a JSON-encoded string from FormData
                $rules[$key] = 'nullable|string';
            } elseif ($field->data_type === 'file') {
                $rules[$key] = 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240';
            } else {
                $rules[$key] = 'nullable';
            }
        }
        $rules['attachments'] = 'nullable|array';
        $rules['attachments.*'] = 'nullable|file|mimes:jpg,webp,jpeg,png,pdf,doc,docx|max:10240';

        $rules['slots'] = 'nullable|array';
        $rules['slots.*.date'] = 'required';
        $rules['slots.*.start_time'] = 'nullable|string';
        $rules['slots.*.end_time'] = 'nullable|string';
        $rules['slots.*.facility_id'] = 'nullable';

        // NEW: ranges for update path too
        $rules['date_ranges'] = 'nullable|array';
        $rules['date_ranges.*.from'] = 'nullable|date';
        $rules['date_ranges.*.to'] = 'nullable|date';
        $rules['date_ranges.*.start_date'] = 'nullable|date';
        $rules['date_ranges.*.end_date'] = 'nullable|date';

        $validated = $request->validate(
            $rules,
            [
                'attachments.*.mimes' => 'Each file must be JPG, JPEG, PNG, PDF, DOC, or DOCX.',
                'attachments.*.max' => 'Each file must be at most 10 MB.',
            ]
        );

        Log::info('[UpdateSubmission] Validation passed', [
            'field_count' => count($validated),
            'has_attachments' => isset($validated['attachments']),
            'has_slots' => isset($validated['slots']),
        ]);

        // New row data
        $data = [
            'account_id' => auth()->user()->account_id,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($form->fields as $field) {
            if (in_array($field->data_type, ['section', 'heading', 'image'], true)) {
                continue;
            }

            $key = $field->field_name;
            $isRangeMode = ($field->date_mode ?? 'single') === 'range';
            $isSlotBasedDate = (bool) ($field->use_slots || $field->require_facility || $isRangeMode);

            // Date fields: store as Y-m-d format
            if ($field->data_type === 'date') {
                if ($isSlotBasedDate) {
                    continue;
                }

                $val = $request->input($key, $oldPayload[$key] ?? null);
                if ($val) {
                    try {
                        $data[$key] = Carbon::parse($val)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $data[$key] = null;
                    }
                }

                continue;
            }

            if ($field->data_type === 'file') {
                if ($request->hasFile($key)) {
                    $stored = $request->file($key)->store('submissions_uploads', 'private');
                    $data[$key] = $stored;
                } else {
                    $data[$key] = $oldPayload[$key] ?? null;
                }
            } elseif ($field->data_type === 'checkbox' || $field->data_type === 'radio' || $field->data_type === 'select' || $field->data_type === 'table') {
                // CRITICAL: Get the NEW value from request, DO NOT merge with old
                $val = $request->input($key);

                // Normalize to clean JSON (prevent double encoding)
                $data[$key] = $this->normalizeJsonField($val, $key);
            } else {
                $val = $request->input($key, $oldPayload[$key] ?? null);
                $data[$key] = $val;
            }
        }

        // Slots for revised submission
        $accountId = (int) auth()->user()->account_id;
        $rawSlots = $request->input('slots');
        $normalizedSlots = $this->normalizeSlotsInput($rawSlots);
        $compatibilitySubmissionId = (int) $oldSubmission->id;

        if (! empty($normalizedSlots)) {
            $this->assertNoSlotConflicts($normalizedSlots, [$compatibilitySubmissionId]);
            $data['slots'] = json_encode($normalizedSlots);
        }

        // NEW: ranges for revised submission
        $rawRanges = $request->input('date_ranges');
        if (is_array($rawRanges) && count($rawRanges) > 0) {
            $normalizedRanges = [];
            foreach ($rawRanges as $r) {
                $from = $r['from'] ?? $r['start_date'] ?? null;
                $to = $r['to'] ?? $r['end_date'] ?? null;
                if ($from && $to) {
                    $normalizedRanges[] = [
                        'start_date' => Carbon::parse($from)->format('Y-m-d'),
                        'end_date' => Carbon::parse($to)->format('Y-m-d'),
                    ];
                }
            }
            if (! empty($normalizedRanges)) {
                $data['date_ranges'] = json_encode(array_values($normalizedRanges));
            }
        }

        $canonicalPayload = $this->buildCanonicalSubmissionPayload($data, $form->fields);

        $revisionResult = DB::transaction(function () use (
            $accountId,
            $canonicalPayload,
            $data,
            $form,
            $normalizedSlots,
            $request,
            $schemaSnapshot,
            $submissionId,
            $oldSubmission

        ): array {
            $now = now();

            Log::info('[UpdateSubmission] Creating new revision', [
                'revision_of' => $submissionId,
                'slots_set' => array_key_exists('slots', $data),
                'ranges_set' => array_key_exists('date_ranges', $data),
            ]);

            $attachmentPayloads = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('submissions_attachments', 'private');
                    $attachmentPayloads[] = [
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'uploaded_by' => $accountId,
                    ];
                }
            } else {
                $keepIds = json_decode($request->input('keep_attachments', '[]'), true);
                $existingAttachments = SubmissionAttachment::query()
                    ->where('submission_id', $oldSubmission->id)
                    ->whereIn('id', is_array($keepIds) ? $keepIds : [])
                    ->get();

                foreach ($existingAttachments as $attachment) {
                    $attachmentPayloads[] = [
                        'file_path' => $attachment->file_path,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'uploaded_by' => $accountId,
                    ];
                }
            }

            WorkflowStepProgress::query()
                ->where('submission_id', $oldSubmission->id)
                ->where('workflow_id', function ($query) use ($form) {
                    $query->select('id')->from('tbl_workflow')->where('form_id', $form->id)->limit(1);
                })
                ->where('status', 'Pending')
                ->update([
                    'status' => 'Rejected',
                    'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, started_at, NOW())'),
                    'updated_at' => $now,
                ]);

            DB::table('tbl_slots')
                ->where('submission_id', $oldSubmission->id)
                ->where('status', 'Pending')
                ->update([
                    'status' => 'Rejected',
                    'updated_at' => $now,
                ]);

            /** @var \App\Modules\WorkflowBuilder\Models\Workflow|null $workflow */
            $workflow = Workflow::where('form_id', $form->id)
                ->where('status', 'Active')
                ->first();

            $workflowProgressPayloads = [];
            $isParallel = false;
            $version = null;

            if ($workflow) {
                $workflowType = $workflow->workflow_type ?? 'Sequential';
                $isParallel = strcasecmp($workflowType, 'Parallel') === 0;

                $version = WorkflowVersion::where('workflow_id', $workflow->id)
                    ->where('is_current', true)
                    ->first();

                if ($version) {
                    $workflowProgressPayloads = app(WorkflowProgressService::class)
                        ->buildInitialProgress($version, $data);

                    if ($isParallel) {
                        foreach ($workflowProgressPayloads as &$payload) {
                            if ($payload['status'] === 'Waiting') {
                                $payload['status'] = 'Pending';
                                $payload['started_at'] = $now;
                            }
                        }
                        unset($payload);
                    }
                } else {
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

                        $status = $isParallel ? 'Pending' : 'Waiting';
                        $startedAt = null;

                        if (! $isParallel && (int) $step->step_group === 1) {
                            $status = 'Pending';
                            $startedAt = $now;
                        }

                        if ($isParallel) {
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

            $payloadWithAttachments = $canonicalPayload;
            if (! empty($attachmentPayloads)) {
                $payloadWithAttachments['attachments'] = array_map(fn (array $attachmentPayload): array => [
                    'original_name' => $attachmentPayload['original_name'],
                    'file_path' => $attachmentPayload['file_path'],
                    'mime_type' => $attachmentPayload['mime_type'],
                ], $attachmentPayloads);
            }

            $currentProgressPayload = collect($workflowProgressPayloads)->firstWhere('status', 'Pending');

            $canonicalSubmission = app(WriteCanonicalSubmissionAction::class)->execute(
                form: $form,
                accountId: $accountId,
                payload: $payloadWithAttachments,
                schemaSnapshot: $schemaSnapshot,
                revisionOf: (int) $oldSubmission->id,
                currentStepId: $currentProgressPayload['step_id'] ?? null,
                currentActorId: $currentProgressPayload['actor_id'] ?? null,
                submittedAt: $now,
                attachmentPayloads: $attachmentPayloads,
                slotPayloads: array_map(fn (array $slot): array => [
                    'facility_id' => $slot['facility_id'],
                    'date' => $slot['date'],
                    'start_time' => $slot['start_time'] ?? null,
                    'end_time' => $slot['end_time'] ?? null,
                    'status' => 'Pending',
                ], $normalizedSlots),
                workflowProgressPayloads: $workflowProgressPayloads,
                workflowVersionId: $version?->id,
            );

            if ($workflow) {
                $this->bootstrapPendingGroups($workflow, (int) $canonicalSubmission->id);

                $currentProgress = WorkflowStepProgress::query()
                    ->where('workflow_id', $workflow->id)
                    ->where('submission_id', $canonicalSubmission->id)
                    ->where('status', 'Pending')
                    ->orderBy('started_at')
                    ->orderBy('id')
                    ->first();

                $canonicalSubmission->forceFill([
                    'current_workflow_status' => 'Pending',
                    'current_step_id' => $currentProgress?->step_id,
                    'current_actor_id' => $currentProgress?->actor_id,
                ])->save();
            }

            return [
                'new_submission_id' => (int) $canonicalSubmission->id,
                'workflow' => $workflow,
                'is_parallel' => $isParallel,
            ];
        });

        if ($revisionResult['workflow']) {
            try {
                if ($revisionResult['is_parallel']) {
                    $this->notifier->notifyAllParallelApprovers($revisionResult['workflow'], $revisionResult['new_submission_id'], $form);
                } else {
                    $this->notifier->notifyFirstSequentialApprovers($revisionResult['workflow'], $revisionResult['new_submission_id'], $form);
                }
            } catch (\Throwable $e) {
                \Log::warning('[Notify] Failed to notify initial approvers on revision reset', [
                    'workflow_id' => $revisionResult['workflow']->id,
                    'submissionId' => $revisionResult['new_submission_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[UpdateSubmission] Revision workflow reset', ['new_submission_id' => $revisionResult['new_submission_id']]);

        session()->flash('submission_success', [
            'form_name' => $form->form_name,
            'submission_id' => $revisionResult['new_submission_id'],
        ]);

        return \Inertia\Inertia::location(
            route($viewRouteName, [
                'formId' => $formId,
                'submissionId' => $revisionResult['new_submission_id'],
            ])
        );
    }

    public function assertSubmissionEditable(int $formId, int $submissionId, int $accountId): void
    {
        Form::findOrFail($formId);

        $submission = $this->findCanonicalSubmissionRecord($formId, $submissionId);
        if (! $submission) {
            abort(404, 'Submission not found or unauthorized.');
        }

        if ((int) $submission->account_id !== $accountId) {
            abort(404, 'Submission not found or unauthorized.');
        }

        if (! $submission->is_latest_revision) {
            abort(403, 'Only the latest revision can be edited.');
        }

        $latestStatus = WorkflowStepProgress::query()
            ->where('submission_id', $submission->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('status') ?? $submission->current_workflow_status ?? 'Pending';

        if (! in_array($this->normalizeEditStatus($latestStatus), ['rejected', 'auto-rejected', 'revision'], true)) {
            abort(403, 'Only rejected submissions can be edited.');
        }
    }

    private function findCanonicalSubmissionRecord(int $formId, int $submissionId, array $relations = []): ?FormSubmission
    {
        return FormSubmission::query()->with($relations)->where('form_id', $formId)->find($submissionId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractSlots(FormSubmission $submission): array
    {
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];
        $payloadSlots = $payload['slots'] ?? null;
        if (is_array($payloadSlots)) {
            return array_values($payloadSlots);
        }

        return $submission->slots
            ->map(fn (Slot $slot): array => [
                'date' => optional($slot->date)->toDateString(),
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'facility_id' => $slot->facility_id,
                'facility' => $slot->facility?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractDateRanges(FormSubmission $submission): array
    {
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];
        $dateRanges = $payload['date_ranges'] ?? [];
        if (! is_array($dateRanges)) {
            return [];
        }

        return array_values(array_map(function (array $range): array {
            $start = $range['start'] ?? $range['from'] ?? $range['start_date'] ?? null;
            $end = $range['end'] ?? $range['to'] ?? $range['end_date'] ?? null;

            return [
                'start' => $start,
                'end' => $end,
                'start_date' => $range['start_date'] ?? $start,
                'end_date' => $range['end_date'] ?? $end,
            ];
        }, $dateRanges));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSubmissionFields(FormSubmission $submission, Form $form): array
    {
        $payload = is_array($submission->payload_json) ? $submission->payload_json : [];

        return collect($this->schemaFieldsForSubmission($submission, $form))
            ->map(fn (array $field): array => [
                'field_name' => $field['field_name'],
                'label' => $field['label'] ?? $field['field_name'],
                'value' => $payload[$field['field_name']] ?? null,
                'type' => strtolower((string) ($field['data_type'] ?? 'text')),
                'field_options' => $field['field_options'] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function schemaFieldsForSubmission(FormSubmission $submission, Form $form): array
    {
        $schemaSnapshot = is_array($submission->schema_snapshot_json) ? $submission->schema_snapshot_json : [];
        $fields = $schemaSnapshot['fields'] ?? null;
        if (is_array($fields) && $fields !== []) {
            return array_values($fields);
        }

        $formFields = $form->toSchemaArray()['fields'] ?? [];

        if ($formFields instanceof \Illuminate\Support\Collection) {
            return $formFields->values()->all();
        }

        return is_array($formFields) ? $formFields : [];
    }

    /**
     * @return array{history: Collection<int, array<string, mixed>>}
     */
    private function buildRevisionHistory(FormSubmission $submission, ?int $workflowId): array
    {
        $rootSubmissionId = $submission->root_submission_id ?: $submission->id;
        $chain = FormSubmission::query()
            ->where(function ($query) use ($rootSubmissionId, $submission) {
                $query->where('root_submission_id', $rootSubmissionId)
                    ->orWhere('id', $rootSubmissionId);

                if ($submission->root_submission_id === null) {
                    $query->orWhere('revision_of', $submission->id);
                }
            })
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        if ($chain->isEmpty()) {
            $chain = new EloquentCollection([$submission]);
        }

        return [
            'history' => $chain->values()->map(function (FormSubmission $row, int $index) use ($workflowId): array {
                $latestStatus = WorkflowStepProgress::query()
                    ->where('submission_id', $row->id)
                    ->when($workflowId, fn ($query) => $query->where('workflow_id', $workflowId))
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->value('status') ?? $row->current_workflow_status ?? 'Pending';

                return [
                    'id' => (int) $row->id,
                    'version' => $index + 1,
                    'created_at' => optional($row->submitted_at ?? $row->created_at)?->toIso8601String(),
                    'updated_at' => optional($row->updated_at ?? $row->submitted_at ?? $row->created_at)?->toIso8601String(),
                    'status' => $latestStatus,
                    'is_latest' => (bool) $row->is_latest_revision,
                ];
            }),
        ];
    }

    private function normalizeEditStatus(?string $raw): string
    {
        $value = strtolower(trim((string) ($raw ?? '')));

        if ($value === '') {
            return 'other';
        }

        if (str_starts_with($value, 'appr')) {
            return 'approved';
        }

        if (str_starts_with($value, 'pend')) {
            return 'pending';
        }

        if (str_contains($value, 'revision')) {
            return 'revision';
        }

        if (str_contains($value, 'auto') && str_contains($value, 'reject')) {
            return 'auto-rejected';
        }

        if (str_starts_with($value, 'rejec')) {
            return 'rejected';
        }

        return 'other';
    }

    private function normalizeTrackerStatus(?string $raw): string
    {
        $value = strtolower(trim((string) ($raw ?? '')));

        return match (true) {
            $value === 'pending' => 'in_progress',
            $value === 'waiting' => 'pending',
            str_starts_with($value, 'appr') || $value === 'completed' => 'approved',
            str_starts_with($value, 'rejec') || str_contains($value, 'auto-reject') => 'rejected',
            $value === 'skipped' => 'skipped',
            default => 'pending',
        };
    }

    private function trackerStatusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'in_progress' => 'In progress',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'skipped' => 'Skipped',
            default => 'Pending',
        };
    }

    private function passStatusFilterForListing(string $normalizedStatus, string $requestedStatus): bool
    {
        if ($requestedStatus === 'all') {
            return true;
        }

        if ($requestedStatus === 'rejected') {
            return in_array($normalizedStatus, ['rejected', 'auto-rejected'], true);
        }

        return $normalizedStatus === $requestedStatus;
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

    private function decodeRuntimeJsonValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * Normalize JSON field value to prevent double/triple encoding
     *
     * @param  mixed  $value
     */
    private function normalizeJsonField($value, string $fieldKey): string
    {
        // If null or empty, return empty string
        if ($value === null || $value === '') {
            return '';
        }

        // Handle malformed wrapped payloads like ["[{\"value\":...}]"]
        if (is_array($value) && count($value) === 1 && is_string($value[0])) {
            $wrappedDecoded = $this->decodeJsonDeep($value[0]);
            if ($wrappedDecoded['decoded']) {
                $value = $wrappedDecoded['value'];
                \Log::info("🔧 Unwrapped legacy JSON array wrapper for [{$fieldKey}]", [
                    'final_type' => gettype($value),
                ]);
            }
        }

        // Deep-decode string payloads to handle escaped JSON (e.g. "[{\"value\":...}]")
        $decoded = $this->decodeJsonDeep($value);
        if ($decoded['decoded']) {
            $value = $decoded['value'];
            \Log::info("🔄 Deep decoded JSON for [{$fieldKey}]", [
                'original_type' => gettype($decoded['original']),
                'final_type' => gettype($value),
            ]);
        }

        // Now we have either an array/object (original or decoded)
        // Encode it cleanly once
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            \Log::info("✅ Clean JSON encode for [{$fieldKey}]", [
                'input_type' => gettype($value),
                'output_length' => strlen((string) $encoded),
            ]);

            return $encoded;
        }

        // Scalar value (int, bool, etc.)
        return (string) $value;
    }

    /**
     * Denormalize JSON field value when reading from database
     * Decodes JSON strings into arrays/objects for frontend consumption
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function denormalizeJsonField($value, string $fieldKey)
    {
        // If null or empty, return as-is
        if ($value === null || $value === '') {
            return $value;
        }

        // If it's already an array or object, return as-is
        if (is_array($value) || is_object($value)) {
            return $value;
        }

        // If it's a string, deep decode to recover escaped JSON strings.
        if (is_string($value)) {
            $decoded = $this->decodeJsonDeep($value);
            if ($decoded['decoded']) {
                \Log::info("📖 Deep decoded JSON field for display [{$fieldKey}]", [
                    'original_type' => gettype($decoded['original']),
                    'final_type' => gettype($decoded['value']),
                ]);

                return $decoded['value'];
            }
        }

        // Return original value if not JSON or decoding failed
        return $value;
    }

    /**
     * @param  mixed  $value
     * @return array{value:mixed,decoded:bool,original:mixed}
     */
    private function decodeJsonDeep($value): array
    {
        $current = $value;
        $decoded = false;

        for ($i = 0; $i < 3; $i++) {
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
}
