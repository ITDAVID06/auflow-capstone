<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Models\ReportView;
use App\Modules\Reports\Requests\IndexSavedReportRequest;
use App\Modules\Reports\Requests\StoreSavedReportRequest;
use App\Modules\Reports\Requests\UpdateSavedReportRequest;
use Illuminate\Http\Request;

class SavedReportController extends Controller
{
    /**
     * List the authenticated user's saved views for a given form.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(IndexSavedReportRequest $request)
    {
        $views = ReportView::query()
            ->where('form_id', (int) $request->input('form_id'))
            ->where('created_by', (int) ($request->user()?->account_id ?? 0))
            ->orderBy('name')
            ->get(['id', 'name', 'filter_state', 'created_at']);

        return response()->json($views);
    }

    /**
     * Save a new named report view.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreSavedReportRequest $request)
    {
        $validated = $request->validated();

        $view = ReportView::create([
            'form_id' => (int) $validated['form_id'],
            'name' => $validated['name'],
            'filter_state' => $validated['filter_state'],
            'created_by' => (int) ($request->user()?->account_id ?? 0),
        ]);

        return response()->json($view, 201);
    }

    /**
     * Update an existing saved view's name or filter state.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateSavedReportRequest $request, int $id)
    {
        $view = ReportView::findOrFail($id);

        if ($view->created_by !== (int) ($request->user()?->account_id ?? -1)) {
            return response()->json(['error' => 'You are not authorized to update this view.'], 403);
        }

        $validated = $request->validated();

        $view->fill($validated)->save();

        return response()->json($view);
    }

    /**
     * Delete a saved view.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, int $id)
    {
        $view = ReportView::findOrFail($id);

        if ($view->created_by !== (int) ($request->user()?->account_id ?? -1)) {
            return response()->json(['error' => 'You are not authorized to delete this view.'], 403);
        }

        $view->delete();

        return response()->json(null, 204);
    }
}
