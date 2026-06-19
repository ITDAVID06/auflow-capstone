<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Models\ScheduledExport;
use App\Modules\Reports\Requests\IndexScheduledExportRequest;
use App\Modules\Reports\Requests\StoreScheduledExportRequest;
use App\Modules\Reports\Requests\UpdateScheduledExportRequest;
use App\Modules\Reports\Services\ScheduledExportService;
use Illuminate\Http\Request;

class ScheduledExportController extends Controller
{
    public function __construct(
        private readonly ScheduledExportService $exportService,
    ) {}

    /**
     * List the authenticated user's scheduled exports.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(IndexScheduledExportRequest $request)
    {
        $exports = $this->exportService->listForUser(
            userId: (int) ($request->user()?->account_id ?? 0),
            formId: $request->filled('form_id') ? (int) $request->input('form_id') : null,
        );

        return response()->json($exports);
    }

    /**
     * Create a new scheduled export.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreScheduledExportRequest $request)
    {
        $validated = $request->validated();

        $export = $this->exportService->create(
            validated: $validated,
            userId: (int) ($request->user()?->account_id ?? 0),
        );

        return response()->json($export, 201);
    }

    /**
     * Update a scheduled export (owner-only).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateScheduledExportRequest $request, int $id)
    {
        $export = ScheduledExport::findOrFail($id);

        if ($export->created_by !== (int) ($request->user()?->account_id ?? -1)) {
            return response()->json(['error' => 'You are not authorized to update this scheduled export.'], 403);
        }

        $validated = $request->validated();

        $export = $this->exportService->update($export, $validated);

        return response()->json($export);
    }

    /**
     * Delete a scheduled export (owner-only).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, int $id)
    {
        $export = ScheduledExport::findOrFail($id);

        if ($export->created_by !== (int) ($request->user()?->account_id ?? -1)) {
            return response()->json(['error' => 'You are not authorized to delete this scheduled export.'], 403);
        }

        $this->exportService->delete($export);

        return response()->json(null, 204);
    }
}
