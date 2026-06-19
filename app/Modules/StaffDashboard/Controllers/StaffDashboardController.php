<?php

namespace App\Modules\StaffDashboard\Controllers;

use App\Exceptions\WorkflowVersionNotFoundException;
use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormCategory;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\StaffDashboard\Requests\ProgressActionRequest;
use App\Modules\StaffDashboard\Services\StaffDashboardQueryService;
use App\Modules\StaffDashboard\Services\StaffSubmissionDetailsService;
use App\Modules\StaffDashboard\Services\StaffSubmissionService;
use App\Modules\StudentDashboard\Controllers\StudentDashboardController;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgressCommentAttachment as CommentAtt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class StaffDashboardController extends Controller
{
    public function __construct(
        protected StaffSubmissionService $service,
        protected StaffDashboardQueryService $queryService,
        protected StaffSubmissionDetailsService $detailsService,
        protected StudentSubmissionService $studentSubmissionService,
        protected StudentDashboardController $studentDashboardController
    ) {}

    /** Dashboard index: metrics + pending requests + active forms */
    public function index(Request $request)
    {
        $staffId = (int) auth()->user()?->account_id;
        $search = $request->string('q')->toString() ?: null;

        return Inertia::render('staff-dashboard/StaffDashboardPage', [
            'metrics' => $this->queryService->getMetricsForStaff($staffId),
            'requests' => $this->queryService->getPendingRequestsForStaff($staffId, $search),
            'pendingContext' => $this->queryService->getPendingContextForStaff($staffId),
            'forms' => $this->getVisibleForms($staffId, true, ['id', 'form_name', 'description'])->get(),
        ]);
    }

    /** Approve a workflow step */
    public function approve(ProgressActionRequest $request, int $id)
    {
        $staffId = (int) auth()->user()?->account_id;
        $comment = $request->validated()['comment'] ?? null;
        $files = $request->file('attachments', []);

        try {
            $result = $this->service->approveStep($id, $staffId, $comment, $files);
            $message = $result['final_approved']
                ? 'This request has been fully approved and the submitter has been notified.'
                : 'Step approved successfully.';

            if ($request->header('X-Inertia')) {
                return redirect()->back(status: 303)->with('success', $message);
            }
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'message' => $message,
                    'final_approved' => (bool) ($result['final_approved'] ?? false),
                    'submission_id' => $result['submission_id'] ?? null,
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::warning('[StaffDashboard] Approve action failed', [
                'progress_id' => $id,
                'staff_id' => $staffId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $safeMessage = 'Unable to process the approval right now. Please try again.';

            if ($request->header('X-Inertia')) {
                return redirect()->back(status: 303)->with('error', $safeMessage);
            }
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'error' => $safeMessage], 422);
            }

            return redirect()->back()->with('error', $safeMessage);
        }
    }

    public function reject(ProgressActionRequest $request, int $id)
    {
        $staffId = (int) auth()->user()?->account_id;
        $comment = $request->validated()['comment'] ?? null;
        $files = $request->file('attachments', []);

        try {
            $result = $this->service->rejectStep($id, $staffId, $comment, $files);

            if ($request->header('X-Inertia')) {
                return redirect()->back(status: 303)->with('success', 'Step rejected successfully.');
            }
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Step rejected successfully.',
                    'final_approved' => (bool) ($result['final_approved'] ?? false),
                    'submission_id' => $result['submission_id'] ?? null,
                ]);
            }

            return redirect()->back()->with('success', 'Step rejected successfully.');
        } catch (\Throwable $e) {
            Log::warning('[StaffDashboard] Reject action failed', [
                'progress_id' => $id,
                'staff_id' => $staffId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $safeMessage = 'Unable to process the rejection right now. Please try again.';

            if ($request->header('X-Inertia')) {
                return redirect()->back(status: 303)->with('error', $safeMessage);
            }
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['ok' => false, 'error' => $safeMessage], 422);
            }

            return redirect()->back()->with('error', $safeMessage);
        }
    }

    /** View submission details for review */
    public function viewSubmission(int $id)
    {
        $staffId = (int) auth()->user()?->account_id;

        $submissionDetails = $this->detailsService->getSubmissionDetailsForStaff($id, $staffId);

        return Inertia::render('staff-dashboard/ReviewSubmissionPage', [
            'submission' => $submissionDetails,
        ]);
    }

    /** Staff requester dashboard entrypoint */
    public function mySubmissionsIndex()
    {
        return Inertia::render('student-dashboard/student-dashboard', [
            'routeNamespace' => 'staff-dashboard',
            'submissionViewRouteName' => 'staff-dashboard.my-submissions.view',
            'submissionEditRouteName' => 'staff-dashboard.my-submissions.edit',
        ]);
    }

    /** Staff requester submissions JSON (delegated from student flow) */
    public function mySubmissions(Request $request)
    {
        return $this->studentDashboardController->submissions($request);
    }

    /** Staff requester metrics JSON (delegated from student flow) */
    public function myMetrics()
    {
        return $this->studentDashboardController->metrics();
    }

    /** Staff approval metrics JSON (pending approvals, approved, rejected as approver) */
    public function approvalMetrics()
    {
        $staffId = (int) auth()->user()?->account_id;

        return response()->json($this->queryService->getMetricsForStaff($staffId));
    }

    /** View own submission in staff namespace */
    public function viewOwnSubmission(int $formId, int $submissionId)
    {
        $accountId = (int) auth()->user()?->account_id;
        $submissionDetails = $this->studentSubmissionService->getSubmissionDetails(
            $formId,
            $submissionId,
            $accountId,
            'staff-dashboard.my-progress-attachments.download'
        );

        if (! $submissionDetails) {
            abort(404, 'Submission not found');
        }

        return Inertia::render('student-dashboard/components/submission-viewer', [
            'submission' => $submissionDetails,
            'routeNamespace' => 'staff-dashboard',
            'submissionViewRouteName' => 'staff-dashboard.my-submissions.view',
            'submissionEditRouteName' => 'staff-dashboard.my-submissions.edit',
            'backHref' => '/staff-dashboard/my-submissions',
        ]);
    }

    /** Edit own rejected submission in staff namespace */
    public function editOwnSubmission(int $formId, int $submissionId)
    {
        $accountId = (int) auth()->user()->account_id;

        $this->studentSubmissionService->assertSubmissionEditable($formId, $submissionId, $accountId);

        $submission = $this->studentSubmissionService->getSubmissionEditPayload($formId, $submissionId, $accountId);
        if (! $submission) {
            abort(404, 'Submission not found or unauthorized.');
        }

        return Inertia::render('student-dashboard/EditSubmissionPage', [
            'submission' => [
                ...$submission,
                'update_route_name' => 'staff-dashboard.my-submissions.update',
                'back_href' => '/staff-dashboard/my-submissions',
            ],
        ]);
    }

    public function updateOwnSubmission(Request $request, int $formId, int $submissionId)
    {
        return $this->studentSubmissionService->updateSubmission(
            $request,
            $formId,
            $submissionId,
            'staff-dashboard.my-submissions.view'
        );
    }

    public function downloadOwnProgressAttachment(int $id)
    {
        $accountId = (int) auth()->user()->account_id;

        $att = CommentAtt::with(['progress'])
            ->findOrFail($id);

        $formId = $att->progress->form_id;
        $submissionId = $att->progress->submission_id;

        $ownsSubmission = $this->studentSubmissionService->studentOwnsSubmission(
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

    public function download($id)
    {
        $attachment = SubmissionAttachment::findOrFail($id);

        abort_unless(Storage::disk('private')->exists($attachment->file_path), 404);

        return Storage::disk('private')->download($attachment->file_path, $attachment->original_name);
    }

    /** Fetch active forms for staff form submission (JSON) */
    public function activeForms()
    {
        $accountId = (int) auth()->user()->account_id;

        $forms = $this->getVisibleForms($accountId, true)->get();

        $submissionCounts = $this->studentSubmissionService->getSubmissionCountsForForms(
            $forms->pluck('id')->all(),
            $accountId
        );

        $forms = $forms->map(function ($form) use ($submissionCounts) {
            $form->submission_limit_reached = $this->studentSubmissionService->hasReachedSubmissionLimitWithCount(
                $form,
                (int) ($submissionCounts[$form->id] ?? 0)
            );

            return $form;
        });

        \Log::info("[StaffDashboard] Forms visible to account_id={$accountId}: ".$forms->pluck('id')->implode(', '));

        return response()->json($forms);
    }

    /** Submit a form as staff */
    public function submit(Request $request, int $id)
    {
        try {
            return $this->service->handleSubmission($request, $id);
        } catch (WorkflowVersionNotFoundException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /** View a form for filling */
    public function viewForm(int $id)
    {
        $accountId = (int) auth()->user()->account_id;
        $form = Form::with(['fields' => fn ($q) => $q->orderBy('field_order')])
            ->findOrFail($id);

        $this->authorize('viewAsSubmitter', $form);

        $availability = $this->studentSubmissionService->getFormSubmissionAvailability($form, $accountId);
        $form->setAttribute('submission_limit_reached', $availability['submission_limit_reached']);
        $form->setAttribute('submission_availability', $availability);

        $userFullName = null;
        if (auth()->check()) {
            $fullName = trim((string) (auth()->user()->full_name ?? ''));
            $userFullName = $fullName !== '' ? $fullName : null;
        }

        return Inertia::render('form-submission/FormSubmissionPage', [
            'form' => $form,
            'submitRouteName' => 'staff-dashboard.forms.submit',
            'backRouteName' => 'staff-dashboard.forms.index',
            'userFullName' => $userFullName,
        ]);
    }

    /** All requests table */
    public function viewAll(Request $request)
    {
        $staffId = (int) auth()->user()?->account_id;
        $status = $request->query('status');
        $search = $request->query('q');
        $perPage = (int) $request->query('perPage', 15);

        return Inertia::render('staff-dashboard/AllRequestsPage', [
            'requests' => $this->queryService->getAllRequestsForStaff($staffId, $status, $search, $perPage),
            'filters' => [
                'status' => $status,
                'q' => $search,
                'perPage' => $perPage,
            ],
        ]);
    }

    /** List of active forms page */
    public function listForms(Request $request)
    {
        $accountId = (int) auth()->user()->account_id;
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');

        $forms = $this->getVisibleForms($accountId, true, [
            'id', 'form_name', 'description', 'version', 'submission_limit',
        ])
            ->when(! empty($categoryId) && $categoryId !== 'all', function ($query) use ($categoryId) {
                $query->where('form_category_id', (int) $categoryId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $like = "%{$search}%";
                    $q->where('form_name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('form_code', 'like', $like);
                });
            })
            ->get();

        $submissionCounts = $this->studentSubmissionService->getSubmissionCountsForForms(
            $forms->pluck('id')->all(),
            $accountId
        );

        $forms = $forms->map(function ($form) use ($submissionCounts) {
            $form->submission_limit_reached = $this->studentSubmissionService->hasReachedSubmissionLimitWithCount(
                $form,
                (int) ($submissionCounts[$form->id] ?? 0)
            );

            return $form;
        });

        $categories = FormCategory::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Forms/FormCatalogPage', [
            'forms' => $forms,
            'categories' => $categories,
            'indexRouteName' => 'staff-dashboard.forms.index',
            'viewRouteName' => 'staff-dashboard.forms.show',
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId ?: 'all',
            ],
        ]);
    }

    /**
     * Shared form visibility query
     */
    private function getVisibleForms(int $accountId, bool $lockedOnly = false, array $columns = ['*'])
    {
        // Always merge in form_category_id so CategoryBadge can work
        if ($columns !== ['*'] && ! in_array('form_category_id', $columns, true)) {
            $columns[] = 'form_category_id';
        }

        return Form::query()
            ->select($columns)
            ->where('status', 'Active')
            ->when($lockedOnly, fn ($q) => $q->where('is_locked', true))
            ->whereHas('permissions', function ($q) use ($accountId) {
                $q->whereIn('tbl_permission.id', function ($sub) use ($accountId) {
                    $sub->select('tbl_role_permission.permission_id')
                        ->from('tbl_user_role')
                        ->join('tbl_role_permission', 'tbl_user_role.role_id', '=', 'tbl_role_permission.role_id')
                        ->where('tbl_user_role.account_id', $accountId);
                });
            })
            ->orderByDesc('created_at');
    }
}
