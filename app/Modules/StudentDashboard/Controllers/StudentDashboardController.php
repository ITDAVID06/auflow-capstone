<?php

namespace App\Modules\StudentDashboard\Controllers;

use App\Exceptions\WorkflowVersionNotFoundException;
use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormCategory;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgressCommentAttachment as CommentAtt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class StudentDashboardController extends Controller
{
    public function __construct(
        protected StudentSubmissionService $submissionService
    ) {}

    public function activeForms()
    {
        $accountId = (int) auth()->user()->account_id;

        $forms = Form::renderable()
            ->orderByDesc('created_at')
            ->get();

        $submissionCounts = $this->submissionService->getSubmissionCountsForForms(
            $forms->pluck('id')->all(),
            $accountId
        );

        $forms = $forms->map(function (Form $form) use ($submissionCounts) {
            $form->submission_limit_reached = $this->submissionService->hasReachedSubmissionLimitWithCount(
                $form,
                (int) ($submissionCounts[$form->id] ?? 0)
            );

            return $form;
        });

        return response()->json($forms);
    }

    public function submit(Request $request, int $id)
    {
        Log::info('[Submit] Student form submission hit', ['id' => $id, 'user' => auth()->id()]);

        try {
            return $this->submissionService->handleSubmission($request, $id);
        } catch (WorkflowVersionNotFoundException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /** ---------------- Helpers ---------------- */
    private function normalizeStatus(?string $raw): string
    {
        $v = strtolower(trim($raw ?? ''));
        if ($v === '') {
            return 'other';
        }
        if (str_starts_with($v, 'pend')) {
            return 'pending';
        }
        if (str_starts_with($v, 'appr')) {
            return 'approved';
        }
        if (str_contains($v, 'auto') && str_contains($v, 'reject')) {
            return 'auto-rejected';
        }
        if (str_starts_with($v, 'rejec')) {
            return 'rejected';
        }
        if (str_contains($v, 'revision')) {
            return 'revision';
        } // “Needs Revision”, etc.

        return 'other';
    }

    private function passStatusFilter(string $norm, string $requested): bool
    {
        if ($requested === 'all') {
            return true;
        }
        if ($requested === 'rejected') {
            return in_array($norm, ['rejected', 'auto-rejected'], true);
        }

        return $norm === $requested; // pending | approved | revision
    }

    public function submissions(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:all,pending,approved,rejected,revision'],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $status = strtolower((string) ($validated['status'] ?? 'all'));
        $search = trim((string) ($validated['search'] ?? ''));
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);

        try {
            return response()->json(
                $this->submissionService->getPaginatedSubmissionSummaries(
                    accountId: (int) auth()->user()->account_id,
                    status: $status,
                    search: $search,
                    page: $page,
                    perPage: $perPage,
                )
            );
        } catch (\Throwable $e) {
            Log::error('[StudentDashboard] Failed to fetch submissions: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Server error while fetching submissions'], 500);
        }
    }

    public function metrics()
    {
        return response()->json(
            $this->submissionService->getSubmissionMetrics((int) auth()->user()->account_id)
        );
    }

    public function viewSubmission(int $formId, int $submissionId)
    {
        $accountId = (int) auth()->user()?->account_id;

        // Service gets all submission details
        $submissionDetails = $this->submissionService->getSubmissionDetails($formId, $submissionId, $accountId);
        if (! $submissionDetails) {
            abort(404, 'Submission not found');
        }

        return \Inertia\Inertia::render('student-dashboard/components/submission-viewer', [
            'submission' => $submissionDetails,
            'attachments' => $submissionDetails['attachments'],
            'routeNamespace' => 'student-dashboard',
            'submissionViewRouteName' => 'student-dashboard.submission.view',
            'submissionEditRouteName' => 'student-dashboard.submission.edit',
            'backHref' => '/student-dashboard',
        ]);
    }

    public function listForms(Request $request)
    {
        $accountId = (int) auth()->user()->account_id;
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');

        $query = Form::query()
            ->where('status', 'Active')
            ->where('is_locked', true)
            ->whereHas('permissions', function ($q) use ($accountId) {
                $q->whereIn('tbl_permission.id', function ($sub) use ($accountId) {
                    $sub->select('tbl_role_permission.permission_id')
                        ->from('tbl_user_role')
                        ->join('tbl_role_permission', 'tbl_user_role.role_id', '=', 'tbl_role_permission.role_id')
                        ->where('tbl_user_role.account_id', $accountId);
                });
            });

        if (! empty($categoryId) && $categoryId !== 'all') {
            $query->where('form_category_id', (int) $categoryId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('form_name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('form_code', 'like', $like);
            });
        }

        $forms = $query
            ->orderByDesc('created_at')
            ->get(['id', 'form_name', 'description', 'version', 'submission_limit', 'form_category_id']);

        $submissionCounts = $this->submissionService->getSubmissionCountsForForms(
            $forms->pluck('id')->all(),
            $accountId
        );

        $forms = $forms->map(function (Form $form) use ($submissionCounts) {
            $form->submission_limit_reached = $this->submissionService->hasReachedSubmissionLimitWithCount(
                $form,
                (int) ($submissionCounts[$form->id] ?? 0)
            );

            return $form;
        });

        $categories = FormCategory::query()->orderBy('name')->get(['id', 'name']);

        \Log::info("[StudentDashboard] Forms visible to account_id={$accountId}: ".$forms->pluck('id')->join(', '));

        return Inertia::render('Forms/FormCatalogPage', [
            'forms' => $forms,
            'categories' => $categories,
            'indexRouteName' => 'student-dashboard.forms.index',
            'viewRouteName' => 'student-dashboard.forms.show',
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId ?: 'all',
            ],
        ]);
    }

    public function viewForm(int $id)
    {
        $accountId = (int) auth()->user()->account_id;
        $form = Form::with(['fields' => fn ($q) => $q->orderBy('field_order')])
            ->findOrFail($id);

        $this->authorize('viewAsSubmitter', $form);

        $availability = $this->submissionService->getFormSubmissionAvailability($form, $accountId);
        $form->setAttribute('submission_limit_reached', $availability['submission_limit_reached']);
        $form->setAttribute('submission_availability', $availability);

        // Get user's full name if logged in (format: "Last name, First name")
        $userFullName = null;
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->profile) {
                $userFullName = trim($user->profile->last_name.', '.$user->profile->first_name);
            }
        }

        return Inertia::render('form-submission/FormSubmissionPage', [
            'form' => $form,
            'submitRouteName' => 'student-dashboard.forms.submit',
            'backRouteName' => 'student-dashboard.forms.index',
            'userFullName' => $userFullName,
        ]);
    }

    public function editSubmission(int $formId, int $submissionId)
    {
        $accountId = (int) auth()->user()->account_id;

        $this->submissionService->assertSubmissionEditable($formId, $submissionId, $accountId);

        $submission = $this->submissionService->getSubmissionEditPayload($formId, $submissionId, $accountId);
        if (! $submission) {
            abort(404, 'Submission not found or unauthorized.');
        }

        return Inertia::render('student-dashboard/EditSubmissionPage', [
            'submission' => $submission,
        ]);
    }

    /**
     * Update submission (fields, attachments, slots).
     */
    public function updateSubmission(Request $request, int $formId, int $submissionId)
    {
        // $this->submissionService->updateSubmission($request, $formId, $submissionId);
        return $this->submissionService->updateSubmission($request, $formId, $submissionId);
    }

    public function downloadProgressAttachment(int $id)
    {
        $accountId = (int) auth()->user()->account_id;

        $att = CommentAtt::with(['progress'])
            ->findOrFail($id);

        // Ensure the progress belongs to the same submission as the student and that
        // the submission record belongs to the student (by account_id).
        $formId = $att->progress->form_id;
        $submissionId = $att->progress->submission_id;

        $ownsSubmission = $this->submissionService->studentOwnsSubmission(
            formId: $formId,
            submissionId: $submissionId,
            accountId: $accountId,
        );

        abort_unless($ownsSubmission, 403, 'Unauthorized');

        return Storage::disk('private')->download(
            $att->file_path,
            $att->original_name
        );
    }
}
