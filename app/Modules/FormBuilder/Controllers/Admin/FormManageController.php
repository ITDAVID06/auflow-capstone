<?php

namespace App\Modules\FormBuilder\Controllers\Admin;

use App\Actions\FormBuilder\DuplicateFormAction;
use App\Actions\FormBuilder\ReviseFormAction;
use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormCategory;
use App\Modules\FormBuilder\Requests\StoreFormCategoryRequest;
use App\Modules\FormBuilder\Requests\UpdateFormVisibilityRequest;
use App\Modules\UserManagement\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class FormManageController extends Controller
{
    private const CACHE_TTL_SECONDS = 900;

    public function listFormPermissions(): JsonResponse
    {
        $perms = Cache::remember('auflow:form:permissions:list', self::CACHE_TTL_SECONDS, fn () => Permission::query()
            ->where('resource', 'forms')
            ->whereIn('action', ['student-access', 'staff-access', 'public-access'])
            ->orderBy('action')
            ->get(['id', 'permission_name', 'resource', 'action'])
            ->all());

        return response()->json($perms);
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $perPage = (int) $request->integer('per_page', 9);

        $allowed = ['Active', 'Inactive', 'Archived'];
        if ($status === 'All' || $status === '') {
            $status = null;
        } elseif (! in_array($status, $allowed, true)) {
            $status = null;
        }

        $query = Form::query()
            ->select([
                'id',
                'form_name',
                'form_code',
                'form_family_code',
                'description',
                'form_category_id',
                'version',
                'status',
                'submission_limit',
                'is_locked',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

        // Include soft-deleted forms when filtering by Archived
        if ($status === 'Archived') {
            $query->onlyTrashed();
            $status = null; // Don't also filter by status column
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('form_name', 'like', $like)
                    ->orWhere('form_code', 'like', $like)
                    ->orWhere('description', 'like', $like);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $forms = $query
            ->withCount('fields')
            ->with('permissions:id,action', 'category:id,name')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        // Batch-load family revision counts to avoid N+1
        $familyCodes = $forms->pluck('form_family_code')->filter()->unique()->values()->toArray();
        $familyCounts = [];
        if (count($familyCodes) > 0) {
            $familyCounts = Form::withTrashed()
                ->whereIn('form_family_code', $familyCodes)
                ->selectRaw('form_family_code, COUNT(*) as cnt')
                ->groupBy('form_family_code')
                ->pluck('cnt', 'form_family_code')
                ->all();
        }

        foreach ($forms as $form) {
            $form->permission_id = $form->permissions->first()?->id;

            if ($form->trashed()) {
                $form->status = 'Archived';
            }

            $form->family_revision_count = (int) ($familyCounts[$form->form_family_code ?? ''] ?? 1);
        }

        $metricsRow = Form::query()->selectRaw(
            "COUNT(*) as total,
             SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
             SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive"
        )->first();

        $metrics = [
            'total' => (int) $metricsRow->total,
            'active' => (int) $metricsRow->active,
            'inactive' => (int) $metricsRow->inactive,
            'archived' => Form::onlyTrashed()->count(),
        ];

        return Inertia::render('Forms/FormManagementPage', [
            'forms' => $forms,
            'filters' => [
                'search' => $search,
                'status' => $status ?? 'All',
            ],
            'metrics' => $metrics,
        ]);
    }

    public function edit(Form $form): Response
    {
        $formData = Cache::remember('auflow:form:def:'.$form->id, self::CACHE_TTL_SECONDS, fn () => $form->load('fields')->toArray());

        return Inertia::render('form-builder/FormBuilderPage', [
            'form' => $formData,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('form-builder/FormBuilderPage');
    }

    public function archive(Form $form)
    {
        app(\App\Modules\FormBuilder\Services\FormAuthoringService::class)->archive($form);

        return redirect()->back()->with('success', 'Form archived.');
    }

    public function restore(Form $form)
    {
        app(\App\Modules\FormBuilder\Services\FormAuthoringService::class)->restore($form);

        return redirect()->back()->with('success', 'Form restored.');
    }

    public function duplicate(Form $form)
    {
        $form->loadMissing(['fields', 'permissions']);

        app(DuplicateFormAction::class)->execute($form);

        return redirect()
            ->route('admin.forms.index')
            ->with('success', 'Form copy created!');
    }

    public function revise(Form $form)
    {
        $form->loadMissing(['fields', 'permissions']);

        app(ReviseFormAction::class)->execute($form);

        return redirect()
            ->route('admin.forms.index')
            ->with('success', 'Form revision created! Previous revisions and workflows have been archived.');
    }

    public function viewLocked(int $id)
    {
        $form = Cache::remember('auflow:form:def:'.$id, self::CACHE_TTL_SECONDS, fn () => Form::with('fields')->findOrFail($id)->toArray());

        // Allow viewing all forms in admin panel (Active, Inactive, Archived, Locked)
        // No permission check - admins should be able to preview any form

        return response()->json([
            'form' => [
                'form_name' => $form['form_name'] ?? null,
                'form_code' => $form['form_code'] ?? null,
                'description' => $form['description'] ?? null,
                'version' => $form['version'] ?? null,
                'status' => ! empty($form['deleted_at']) ? 'Archived' : ($form['status'] ?? null),
            ],
            'fields' => $form['fields'] ?? [],
        ]);
    }

    public function listCategories(): JsonResponse
    {
        $cats = Cache::remember('auflow:form:categories:list', self::CACHE_TTL_SECONDS, fn () => FormCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->all());

        return response()->json($cats);
    }

    public function storeCategory(StoreFormCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $slug = $data['slug'] ?? \Str::slug($data['name']);

        $category = FormCategory::create([
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        Cache::forget('auflow:form:categories:list');

        return response()->json($category, 201);
    }

    public function updateVisibility(UpdateFormVisibilityRequest $request, int $id)
    {
        $form = Form::findOrFail($id);

        // Validate: only allow single permission id or empty (hidden)
        $data = $request->validated();

        // Even if form is locked, allow visibility updates
        if (isset($data['permission_id'])) {
            $form->permissions()->sync([$data['permission_id']]);
        } else {
            $form->permissions()->sync([]); // Hidden → clear perms
        }

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->back()->with('success', 'Visibility updated.');
    }
}
