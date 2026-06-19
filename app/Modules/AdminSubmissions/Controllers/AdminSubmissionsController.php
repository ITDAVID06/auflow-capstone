<?php

namespace App\Modules\AdminSubmissions\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AdminSubmissions\Services\AdminSubmissionsService;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSubmissionsController extends Controller
{
    public function __construct(protected AdminSubmissionsService $service) {}

    /** GET /admin/submissions */
    public function index(Request $request)
    {
        $q = $request->string('q')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $perPage = max(6, min((int) $request->integer('per_page', 9), 60));
        $sort = $request->string('sort')->toString() ?: null;
        $direction = in_array($request->string('direction')->toString(), ['asc', 'desc'], true)
            ? $request->string('direction')->toString()
            : null;

        $paginated = $this->service->getSystemSubmissionsPaginated($status, $q, $perPage, $sort, $direction);

        return Inertia::render('admin-submissions/AdminSubmissionsPage', [
            'metrics' => $this->service->getSystemMetrics(),
            'requests' => $paginated['data'],
            'pagination' => $paginated['pagination'],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /** GET /admin/submissions/{id} */
    public function show(int $formId, int $submissionId)
    {
        $submission = $this->service->getAdminSubmissionDetails($formId, $submissionId);

        if (! $submission) {
            abort(404, 'Submission not found.');
        }

        // Check if the logged-in user can act (approve/reject)
        $currentUser = auth()->user();
        $workflowSteps = WorkflowStepProgress::where('submission_id', $submissionId)
            ->where('form_id', $formId)
            ->with('step.approvers')
            ->get();

        $canOverride = $currentUser->hasPermission('submissions.override');

        $canAct = $workflowSteps->contains(function ($step) use ($currentUser) {
            if (! in_array($step->status, ['Pending', 'In Review', 'Waiting'], true)) {
                return false;
            }

            $isLegacyAssigned = (int) $step->actor_id === (int) $currentUser->account_id;
            $isExplicitApprover = $step->step?->approvers?->contains(
                fn ($approver) => (int) $approver->account_id === (int) $currentUser->account_id
            ) ?? false;

            return $isLegacyAssigned || $isExplicitApprover;
        });

        $canAct = $canOverride || $canAct;

        return Inertia::render('admin-submissions/AdminReviewPage', [
            'submission' => $submission,
            'canAct' => $canAct,
            'backUrl' => route('admin-submissions.index'),
        ]);
    }

    /** PUT /admin/submissions/{id}/approve — override */
    public function approve(Request $request, int $id)
    {
        $adminId = (int) auth()->user()->account_id;
        $comment = $request->string('comment')->toString() ?: null;

        $this->service->adminOverride($id, $adminId, 'Approved', $comment, true, true);

        return back()->with('success', 'Approved by override.');
    }

    /** PUT /admin/submissions/{id}/reject — override */
    public function reject(Request $request, int $id)
    {
        $adminId = (int) auth()->user()->account_id;
        $comment = $request->string('comment')->toString() ?: null;

        $this->service->adminOverride($id, $adminId, 'Rejected', $comment, true, true);

        return back()->with('success', 'Rejected by override.');
    }

    public function myPending(Request $request)
    {
        $accountId = (int) auth()->user()->account_id;
        $q = $request->string('q')->toString() ?: null;
        $status = $request->string('status')->toString() ?: 'pending';
        $perPage = max(6, min((int) $request->integer('per_page', 9), 60));
        $paginated = $this->service->getSystemSubmissionsForUserPaginated($accountId, $status, $q, $perPage);

        return Inertia::render('admin-submissions/components/MyPendingApprovalsPage', [
            'metrics' => $this->service->getUserMetrics($accountId),
            'requests' => $paginated['data'],
            'pagination' => $paginated['pagination'],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }
}
