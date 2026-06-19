<?php

namespace App\Modules\FormBuilder\Controllers;

use App\Exceptions\WorkflowVersionNotFoundException;
use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Requests\StoreFormSubmissionRequest;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestFormController extends Controller
{
    public function __construct(
        private StudentSubmissionService $submissionService
    ) {}

    public function index(Request $request)
    {
        $accountId = auth()->user()->account_id;
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');

        $userPermissionIds = DB::table('tbl_user_role')
            ->join('tbl_role_permission', 'tbl_user_role.role_id', '=', 'tbl_role_permission.role_id')
            ->where('tbl_user_role.account_id', $accountId)
            ->pluck('permission_id')
            ->toArray();

        $query = Form::query()
            ->with('permissions:id,permission_name')
            ->renderable()
            ->whereHas('permissions', function ($q) use ($userPermissionIds) {
                $q->whereIn('tbl_permission.id', $userPermissionIds);
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

        $forms = $query->get(['id', 'form_name', 'description', 'version', 'form_category_id']);

        $categories = \App\Modules\FormBuilder\Models\FormCategory::orderBy('name')
            ->get(['id', 'name']);

        return inertia('Forms/FormCatalogPage', [
            'forms' => $forms,
            'categories' => $categories,
            'indexRouteName' => 'user.forms',
            'viewRouteName' => 'user.form.view',
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId ?: 'all',
            ],
        ]);
    }

    public function show($id)
    {
        $form = Form::with(['fields' => function ($q) {
            $q->orderBy('field_order');
        }, 'permissions'])->renderable()->findOrFail($id);

        $this->authorize('viewAsSubmitter', $form);

        $accountId = (int) auth()->user()->account_id;
        $availability = $this->submissionService->getFormSubmissionAvailability($form, $accountId);

        // Get user's full name if logged in (format: "Last name, First name")
        $userFullName = null;
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->profile) {
                $userFullName = trim($user->profile->last_name.', '.$user->profile->first_name);
            }
        }

        return inertia('form-submission/FormSubmissionPage', [
            'form' => array_merge($form->toSchemaArray(), [
                'submission_availability' => $availability,
                'submission_limit_reached' => $availability['submission_limit_reached'],
            ]),
            'submitRouteName' => 'user.form.submit',
            'backRouteName' => 'user.forms',
            'userFullName' => $userFullName,
        ]);
    }

    public function submit(StoreFormSubmissionRequest $request, int $id)
    {
        try {
            return $this->submissionService->handleSubmission($request, $id);
        } catch (WorkflowVersionNotFoundException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}
