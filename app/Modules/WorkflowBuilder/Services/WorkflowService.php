<?php

namespace App\Modules\WorkflowBuilder\Services;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepApprover;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class WorkflowService
{
    private const CACHE_TTL_SECONDS = 900;

    public static function workflowDetailsCacheKey(int $workflowId): string
    {
        return 'auflow:workflow:def:'.$workflowId;
    }

    public static function availableFormsCacheKey(): string
    {
        return 'auflow:workflow:available_forms';
    }

    public static function assignableUsersCacheKey(): string
    {
        return 'auflow:workflow:assignable_users';
    }

    public function listWorkflows(?string $status = null, ?string $search = null, int $perPage = 9)
    {
        $query = Workflow::query()
            ->select([
                'id',
                'workflow_name',
                'workflow_type',
                'description',
                'form_id',
                'status',
                'created_by',
                'workflow_settings',
                'created_at',
                'updated_at',
            ])
            ->with([
                'form:id,form_name',
                'steps:id,workflow_id,step_name,step_order,assigned_account_id',
                'steps.assignedUser:account_id,username',
                'steps.assignedUser.profile:account_id,first_name,last_name',
            ])
            ->withCount('steps')
            ->orderByDesc('created_at');

        // Apply search filter
        if ($search) {
            $query->where('workflow_name', 'LIKE', "%{$search}%");
        }

        // Apply status filter
        if ($status && strtolower($status) !== 'all') {
            $query->where('status', ucfirst($status));
        }

        // Paginate for Inertia table use
        return $query->paginate($perPage)->withQueryString();
    }

    public function getAvailableForms()
    {
        return Cache::remember(
            self::availableFormsCacheKey(),
            self::CACHE_TTL_SECONDS,
            fn () => DB::table('tbl_form as f')
                ->select('f.id', 'f.form_name')
                ->whereIn('f.status', ['Active', 'Inactive'])
                ->whereNull('f.deleted_at')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('tbl_workflow as w')
                        ->whereColumn('w.form_id', 'f.id')
                        ->where('w.status', 'Active');
                })
                ->orderBy('f.form_name')
                ->get()
        );
    }

    public function getAssignableUsers()
    {
        return Cache::remember(
            self::assignableUsersCacheKey(),
            self::CACHE_TTL_SECONDS,
            fn () => DB::table('tbl_user as u')
                ->join('tbl_userprofile as p', 'u.account_id', '=', 'p.account_id')
                ->join('tbl_user_role as ur', function ($j) {
                    $j->on('ur.account_id', '=', 'u.account_id')
                        ->where('ur.is_active', 1);
                })
                ->join('tbl_role_permission as rp', 'rp.role_id', '=', 'ur.role_id')
                ->join('tbl_permission as perm', 'perm.id', '=', 'rp.permission_id')
                ->where('u.user_status_id', 1)
                ->where(function ($q) {
                    $q->whereNull('ur.expiry_date')
                        ->orWhere('ur.expiry_date', '>', now());
                })
                ->whereIn('perm.slug', ['requests.approve', 'submissions.override'])
                ->select(
                    'u.account_id as id',
                    DB::raw("CONCAT(p.first_name, ' ', p.last_name) as name"),
                    'p.first_name',
                    'p.last_name'
                )
                ->distinct()
                ->orderBy('p.first_name')
                ->orderBy('p.last_name')
                ->get()
        );
    }

    public function getWorkflowDetails(int $id)
    {
        return Cache::remember(
            self::workflowDetailsCacheKey($id),
            self::CACHE_TTL_SECONDS,
            fn () => Workflow::with([
                'form:id,form_name',
                'steps',
                'steps.assignedUser:account_id,username',
                'steps.assignedUser.profile:account_id,first_name,last_name',
                'steps.approvers',
                'steps.approvers.user:account_id,username',
                'steps.approvers.user.profile:account_id,first_name,last_name',
            ])->findOrFail($id)
        );
    }

    public function createWorkflow(array $data)
    {
        if (is_array($data['workflow_settings'] ?? null)) {
            $data['workflow_settings'] = app(WorkflowPersistenceService::class)
                ->normalizeWorkflowSettings($data['workflow_settings']);
        }

        if (! empty($data['workflow_settings']['nodes'])) {
            try {
                app(WorkflowValidationService::class)->validate(
                    $data['workflow_settings']['nodes'],
                    $data['workflow_settings']['edges'] ?? [],
                    $data['workflow_type'] ?? 'Sequential'
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'workflow_settings' => [$exception->getMessage()],
                ]);
            }
        }

        $workflow = DB::transaction(function () use ($data) {
            // FIX: Unbind existing Draft workflows from the form to prevent unique constraint violation
            // This allows workflow swapping without data loss (old workflow becomes unbound Draft)
            $formId = $data['form_id'] ?? null;
            $status = ucfirst($data['status'] ?? 'Draft');

            if ($formId && $status === 'Draft') {
                Workflow::where('form_id', $formId)
                    ->where('status', 'Draft')
                    ->update(['form_id' => null]);

                \Log::info('Unbound existing Draft workflow(s) from form to allow new assignment', [
                    'form_id' => $formId,
                ]);
            }

            $workflow = Workflow::create([
                'workflow_name' => $data['workflow_name'],
                'workflow_type' => $data['workflow_type'] ?? 'Sequential',
                'description' => $data['description'] ?? null,
                'form_id' => $formId,
                'status' => $status,
                'workflow_settings' => $data['workflow_settings'] ?? ['nodes' => [], 'edges' => []],
                'created_by' => $data['created_by'] ?? auth()->user()->account_id,
            ]);

            if (! empty($data['workflow_settings']['nodes'])) {
                app(WorkflowStepService::class)->buildStepsFromCanvas($workflow, $data['workflow_settings']);
            }

            return $workflow;
        });

        $this->forgetWorkflowCaches((int) $workflow->id);

        return $workflow;
    }

    public function updateWorkflow(int $id, array $data)
    {
        if (is_array($data['workflow_settings'] ?? null)) {
            $data['workflow_settings'] = app(WorkflowPersistenceService::class)
                ->normalizeWorkflowSettings($data['workflow_settings']);
        }

        if (! empty($data['workflow_settings']['nodes'])) {
            try {
                app(WorkflowValidationService::class)->validate(
                    $data['workflow_settings']['nodes'],
                    $data['workflow_settings']['edges'] ?? [],
                    $data['workflow_type'] ?? 'Sequential'
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'workflow_settings' => [$exception->getMessage()],
                ]);
            }
        }

        $workflow = DB::transaction(function () use ($id, $data) {
            $workflow = Workflow::findOrFail($id);
            if (strcasecmp($workflow->status, 'Draft') !== 0) {
                throw ValidationException::withMessages([
                    'status' => "Cannot edit {$workflow->status} workflows. Move to Draft first (Admin → Workflows → {$workflow->workflow_name} → Move to Draft).",
                ]);
            }

            // FIX: Unbind existing Draft workflows from the target form to prevent unique constraint violation
            $newFormId = $data['form_id'] ?? null;
            if ($newFormId && $newFormId != $workflow->form_id) {
                Workflow::where('form_id', $newFormId)
                    ->where('status', 'Draft')
                    ->where('id', '!=', $id)
                    ->update(['form_id' => null]);

                \Log::info('Unbound existing Draft workflow(s) from form to allow reassignment', [
                    'form_id' => $newFormId,
                    'workflow_id' => $id,
                ]);
            }

            $workflow->update([
                'workflow_name' => $data['workflow_name'],
                'workflow_type' => $data['workflow_type'] ?? 'Sequential',
                'description' => $data['description'] ?? null,
                'form_id' => $newFormId,
                'status' => 'Draft', // keep Draft
                'workflow_settings' => $data['workflow_settings'] ?? $workflow->workflow_settings,
            ]);

            if (! empty($data['workflow_settings']['nodes'])) {
                app(WorkflowStepService::class)->updateStepsFromCanvas($workflow, $data['workflow_settings']);
            }

            return $workflow;
        });

        $this->forgetWorkflowCaches((int) $workflow->id);

        return $workflow;
    }

    public function publishWorkflow(int $id): int
    {
        $versionId = DB::transaction(function () use ($id) {
            $wf = Workflow::lockForUpdate()->findOrFail($id);

            if (empty($wf->form_id)) {
                throw ValidationException::withMessages(['form_id' => 'Assign a form before publishing.']);
            }
            if (strcasecmp($wf->status, 'Draft') !== 0) {
                throw ValidationException::withMessages(['status' => 'Only Draft workflows can be published.']);
            }

            // Code-level uniqueness: prevent two Active for same form_id
            $exists = Workflow::where('form_id', $wf->form_id)
                ->where('status', 'Active')
                ->where('id', '!=', $wf->id)
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                $existingWorkflow = Workflow::where('form_id', $wf->form_id)
                    ->where('status', 'Active')
                    ->where('id', '!=', $wf->id)
                    ->first();

                throw ValidationException::withMessages([
                    'form_id' => "Cannot publish: Form already has an Active workflow ('{$existingWorkflow->workflow_name}'). Archive it first.",
                ]);
            }

            // Enforce single-active-revision: archive sibling form revisions + their workflows
            $this->archiveSiblingRevisions($wf);

            // Snapshot current step definitions (including approvers) into tbl_workflow_version.
            $nextVersion = ($wf->version ?? 1) + 1;
            $stepsSnapshot = WorkflowStep::with('approvers')
                ->where('workflow_id', $wf->id)
                ->orderBy('step_order')
                ->get()
                ->map(fn (WorkflowStep $step): array => [
                    'id' => $step->id,
                    'step_name' => $step->step_name,
                    'step_description' => $step->step_description,
                    'step_order' => $step->step_order,
                    'step_group' => $step->step_group,
                    'action_type' => $step->action_type,
                    'assigned_account_id' => $step->assigned_account_id,
                    'max_duration_hours' => $step->max_duration_hours,
                    'step_conditions' => $step->step_conditions,
                    'if_rejected_id' => $step->if_rejected_id,
                    'approvers' => $step->approvers
                        ->map(fn (WorkflowStepApprover $a): array => [
                            'account_id' => $a->account_id,
                            'condition' => $a->condition,
                            'order' => $a->order,
                        ])
                        ->all(),
                ])
                ->all();

            if (empty($stepsSnapshot)) {
                throw ValidationException::withMessages([
                    'steps' => 'Cannot publish a workflow with no steps. Add at least one step before publishing.',
                ]);
            }

            // Retire the current version flag before inserting the new one.
            WorkflowVersion::where('workflow_id', $wf->id)->update(['is_current' => false]);

            $workflowVersion = WorkflowVersion::create([
                'workflow_id' => $wf->id,
                'version_number' => $nextVersion,
                'steps_snapshot' => $stepsSnapshot,
                'published_at' => now(),
                'is_current' => true,
            ]);

            $wf->increment('version');
            $wf->update(['status' => 'Active']);
            $this->syncBoundFormStatus($wf, 'Active');

            return (int) $workflowVersion->id;
        });

        $this->forgetWorkflowCaches($id);

        return $versionId;
    }

    public function duplicateWorkflow(int $id)
    {
        $original = Workflow::with('steps')->findOrFail($id);

        /** @var \App\Modules\WorkflowBuilder\Services\WorkflowDuplicateService $duper */
        $duper = app(\App\Modules\WorkflowBuilder\Services\WorkflowDuplicateService::class);

        // Duplicate with version-aware naming
        $copy = $duper->duplicate($original);

        // Safety: ensure duplicates are Draft and unbound
        if ($copy->status !== 'Draft' || $copy->form_id !== null) {
            $copy->update([
                'status' => 'Draft',
                'form_id' => null,
            ]);
        }

        $copy = $copy->fresh();

        $this->forgetWorkflowCaches((int) $copy->id);

        return $copy;
    }

    public function archiveWorkflow(int $id)
    {
        $wf = DB::transaction(function () use ($id) {
            $wf = Workflow::lockForUpdate()->findOrFail($id);
            if (! in_array($wf->status, ['Active', 'Draft'], true)) {
                throw ValidationException::withMessages(['status' => 'Only Active or Draft workflows can be archived.']);
            }
            $wf->update(['status' => 'Archived']);

            // Sync form status: If workflow is archived and has a form, set form to Inactive
            if ($wf->form_id) {
                $form = \App\Modules\FormBuilder\Models\Form::find($wf->form_id);
                if ($form && $form->status === 'Active') {
                    $form->update(['status' => 'Inactive', 'is_locked' => true]);
                    \Log::info('Workflow archived - Associated form set to Inactive', [
                        'workflow_id' => $wf->id,
                        'form_id' => $wf->form_id,
                    ]);
                }
            }

            return $wf;
        });

        $this->forgetWorkflowCaches((int) $wf->id);

        return $wf;
    }

    public function enableWorkflow(int $id)
    {
        $workflow = DB::transaction(function () use ($id) {
            $wf = Workflow::lockForUpdate()->findOrFail($id); // ADD: Lock

            if (strcasecmp($wf->status, 'Archived') !== 0) {
                throw ValidationException::withMessages(['status' => 'Only Archived workflows can be enabled.']);
            }
            if (empty($wf->form_id)) {
                throw ValidationException::withMessages(['form_id' => 'Assign a form before enabling.']);
            }

            $exists = Workflow::where('form_id', $wf->form_id)
                ->where('status', 'Active')
                ->where('id', '!=', $wf->id)
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'form_id' => 'Another Active workflow already exists for this form.',
                ]);
            }

            // Enforce single-active-revision: archive sibling form revisions + their workflows
            $this->archiveSiblingRevisions($wf);

            $wf->update(['status' => 'Active']);
            $this->syncBoundFormStatus($wf, 'Active');

            return $wf;
        });

        $this->forgetWorkflowCaches((int) $workflow->id);

        return $workflow;
    }

    public function draftWorkflow(int $id, bool $force = false): Workflow
    {
        // No-op check without locking to short-circuit without transaction overhead
        $peek = Workflow::findOrFail($id);
        if (strcasecmp($peek->status, 'Draft') === 0) {
            return $peek;
        }

        $wf = DB::transaction(function () use ($id, $force) {
            $wf = Workflow::lockForUpdate()->findOrFail($id);

            // Re-check under lock in case of concurrent status change
            if (strcasecmp($wf->status, 'Draft') === 0) {
                return $wf;
            }

            // From Archived -> Draft (existing rule)
            if (strcasecmp($wf->status, 'Archived') === 0) {
                // FIX: Unbind any existing Draft workflows on the same form before changing to Draft
                if (! empty($wf->form_id)) {
                    Workflow::where('form_id', $wf->form_id)
                        ->where('status', 'Draft')
                        ->where('id', '!=', $wf->id)
                        ->update(['form_id' => null]);

                    \Log::info('Unbound existing Draft workflow(s) from form when setting Archived workflow to Draft', [
                        'workflow_id' => $wf->id,
                        'form_id' => $wf->form_id,
                    ]);
                }

                $wf->update(['status' => 'Draft']);

                return $wf;
            }

            // NEW: From Active -> Draft with guard
            if (strcasecmp($wf->status, 'Active') === 0) {
                // If bound to a form that is still Active, require either Inactive status or force=true
                if (! empty($wf->form_id)) {
                    $form = Form::find($wf->form_id);
                    if ($form && strcasecmp($form->status ?? '', 'Active') === 0 && ! $force) {
                        throw ValidationException::withMessages([
                            'status' => 'This workflow is bound to an Active form. Set the form to Inactive or confirm the override.',
                        ]);
                    }
                }

                // FIX: Unbind any existing Draft workflows on the same form before changing to Draft
                if (! empty($wf->form_id)) {
                    Workflow::where('form_id', $wf->form_id)
                        ->where('status', 'Draft')
                        ->where('id', '!=', $wf->id)
                        ->update(['form_id' => null]);

                    \Log::info('Unbound existing Draft workflow(s) from form when setting Active workflow to Draft', [
                        'workflow_id' => $wf->id,
                        'form_id' => $wf->form_id,
                    ]);
                }

                $wf->update(['status' => 'Draft']);

                // Sync form status: If workflow is set to Draft and has a form, set form to Inactive
                if ($wf->form_id) {
                    $form = Form::find($wf->form_id);
                    if ($form && $form->status === 'Active') {
                        $form->update(['status' => 'Inactive', 'is_locked' => true]);
                        \Log::info('Workflow set to Draft - Associated form set to Inactive', [
                            'workflow_id' => $wf->id,
                            'form_id' => $wf->form_id,
                        ]);
                    }
                }

                return $wf;
            }

            // Any other statuses (unknown): keep strict
            throw ValidationException::withMessages([
                'status' => 'Unsupported status transition.',
            ]);
        });

        $this->forgetWorkflowCaches((int) $wf->id);

        return $wf;
    }

    private function forgetWorkflowCaches(?int $workflowId = null): void
    {
        Cache::forget(self::availableFormsCacheKey());
        Cache::forget(self::assignableUsersCacheKey());

        if ($workflowId !== null) {
            Cache::forget(self::workflowDetailsCacheKey($workflowId));
        }
    }

    private function syncBoundFormStatus(Workflow $workflow, string $status): void
    {
        if (empty($workflow->form_id)) {
            return;
        }

        $form = Form::find($workflow->form_id);
        if (! $form) {
            return;
        }

        if ($status === 'Active') {
            $form->status = 'Active';
            $form->is_locked = true;

            if ($form->revision_effective_at === null) {
                $form->revision_effective_at = Carbon::now()->toDateString();
            }

            $form->save();
        }
    }

    /**
     * When activating a workflow, archive any sibling form revisions (same family)
     * and their bound workflows so only one revision is active at a time.
     */
    private function archiveSiblingRevisions(Workflow $workflow): void
    {
        if (empty($workflow->form_id)) {
            return;
        }

        $form = Form::find($workflow->form_id);
        if (! $form || empty($form->form_family_code)) {
            return;
        }

        $siblingForms = Form::where('form_family_code', $form->form_family_code)
            ->where('id', '!=', $form->id)
            ->whereIn('status', ['Active', 'Inactive'])
            ->get();

        foreach ($siblingForms as $sibling) {
            Workflow::where('form_id', $sibling->id)
                ->whereIn('status', ['Active', 'Draft'])
                ->update(['status' => 'Archived']);

            $sibling->forceFill(['status' => 'Inactive', 'is_locked' => true])->save();
            $sibling->delete();

            \Log::info('Archived sibling form revision when activating latest revision', [
                'archived_form_id' => $sibling->id,
                'archived_form_version' => $sibling->version,
                'active_form_id' => $form->id,
                'active_form_version' => $form->version,
                'family_code' => $form->form_family_code,
            ]);
        }
    }

    public function getWorkflowReadiness(int $id)
    {
        $workflow = Workflow::with('steps')->findOrFail($id);
        $stepCount = $workflow->steps->count();
        $hasStart = $workflow->steps->contains(fn ($s) => $s->step_order === 1);
        $ready = $stepCount > 0 && $hasStart;

        $reason = null;
        if ($stepCount === 0) {
            $reason = 'Workflow has no steps. Add at least one step before publishing.';
        } elseif (! $hasStart) {
            $reason = 'Workflow is missing a step with order 1.';
        }

        return [
            'ready' => $ready,
            'total_steps' => $stepCount,
            'reason' => $reason,
        ];
    }

    public function getWorkflowForEdit(int $id)
    {
        $workflow = Workflow::with('steps')->findOrFail($id);

        return [
            'workflow' => $workflow,
            'forms' => $this->getAvailableForms(),
            'users' => $this->getAssignableUsers(),
        ];
    }
}
