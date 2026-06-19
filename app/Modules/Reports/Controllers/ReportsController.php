<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\SubmissionAttachment;
use App\Modules\Reports\Requests\AggregateRequest;
use App\Modules\Reports\Requests\CompareRequest;
use App\Modules\Reports\Requests\ReportsFilterRequest;
use App\Modules\Reports\Services\ChartDataService;
use App\Modules\Reports\Services\CrossFormComparisonService;
use App\Modules\Reports\Services\CsvExportWriter;
use App\Modules\Reports\Services\PdfExportWriter;
use App\Modules\Reports\Services\ReportAggregationService;
use App\Modules\Reports\Services\ReportColumnRegistry;
use App\Modules\Reports\Services\ReportExportOrchestrator;
use App\Modules\Reports\Services\ReportQueryBuilderService;
use App\Modules\Reports\Services\ReportSummaryService;
use App\Modules\Reports\Services\SubmissionQueryService;
use App\Modules\Reports\Services\SubmissionRowMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class ReportsController extends Controller
{
    private const SLOW_QUERY_THRESHOLD_MS = 750;

    private const PREVIEW_MIME_ALLOWLIST = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    public function __construct(
        protected SubmissionQueryService $submissionQueryService,
        protected SubmissionRowMapper $submissionRowMapper,
        protected ReportSummaryService $reportSummaryService,
        protected CsvExportWriter $csvExportWriter,
        protected PdfExportWriter $pdfExportWriter,
        protected ReportColumnRegistry $columnRegistry,
        protected ReportQueryBuilderService $queryBuilderService,
        protected ReportExportOrchestrator $reportExportOrchestrator,
        protected ReportAggregationService $aggregationService,
        protected ChartDataService $chartDataService,
        protected CrossFormComparisonService $crossFormComparisonService,
    ) {}

    /**
     * List reportable forms for the picker.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function forms()
    {
        $forms = Form::query()
            ->renderable()
            ->orderBy('form_name')
            ->get(['id', 'form_name', 'form_code', 'status']);

        return response()->json($forms);
    }

    /**
     * Display the reports page shell.
     * Each tab fetches its own data via client-side JSON calls.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        return Inertia::render('Reports/ReportsPage', [
            'error' => session('error'),
        ]);
    }

    /**
     * Get form submissions data via API.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFormSubmissions(ReportsFilterRequest $request)
    {
        $filters = $request->validated();

        try {
            $data = $this->assembleReportPayload($filters);

            return response()->json($data);
        } catch (Throwable $exception) {
            Log::error('Failed to load reports JSON data.', [
                'form_id' => $filters['form_id'] ?? null,
                'user_id' => $request->user()?->id,
                'filters' => $filters,
                'exception' => $exception,
            ]);

            return response()->json([
                'error' => 'Could not load report data.',
            ], 500);
        }
    }

    /**
     * Export form submissions to CSV.
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportCSV(ReportsFilterRequest $request)
    {
        $filters = $request->validated();

        try {
            $estimatedRows = $this->submissionQueryService->countFilteredSubmissions($filters);

            if ($this->reportExportOrchestrator->shouldQueueExport($estimatedRows)) {
                $requestedBy = (int) ($request->user()?->account_id ?? 0);
                $payload = $this->reportExportOrchestrator->queueExport($filters, $requestedBy, $estimatedRows);

                return response()->json([
                    'export_id' => $payload['export_id'],
                    'status' => $payload['status'],
                    'estimated_rows' => $payload['estimated_rows'],
                ], 202);
            }

            return $this->csvExportWriter->streamCsvExport($filters);
        } catch (Throwable $exception) {
            Log::error('Failed to export reports CSV.', [
                'form_id' => $filters['form_id'] ?? null,
                'user_id' => $request->user()?->id,
                'filters' => $filters,
                'exception' => $exception,
            ]);

            return response('Could not export report data. Please try again.', 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }

    /**
     * Export current filtered report as print-friendly HTML for PDF save.
     */
    public function exportPDF(ReportsFilterRequest $request)
    {
        $filters = $request->validated();

        try {
            return $this->pdfExportWriter->buildPdfResponse($filters, $request->boolean('autoprint', true));
        } catch (Throwable $exception) {
            Log::error('Failed to export reports PDF.', [
                'form_id' => $filters['form_id'] ?? null,
                'user_id' => $request->user()?->id,
                'filters' => $filters,
                'exception' => $exception,
            ]);

            return response('Could not export report data. Please try again.', 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }

    /**
     * Export current filtered report as a true server-side PDF download.
     */
    public function exportPdfDownload(ReportsFilterRequest $request)
    {
        $filters = $request->validated();

        try {
            return $this->pdfExportWriter->buildPdfDownloadResponse($filters);
        } catch (Throwable $exception) {
            Log::error('Failed to generate PDF download.', [
                'form_id' => $filters['form_id'] ?? null,
                'user_id' => $request->user()?->id,
                'filters' => $filters,
                'exception' => $exception,
            ]);

            return response('Could not generate PDF. Please try again.', 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }

    public function exportStatus(Request $request, string $exportId)
    {
        $payload = $this->reportExportOrchestrator->getExport($exportId);

        if (! is_array($payload)) {
            return response()->json([
                'error' => 'Export not found.',
            ], 404);
        }

        if (! $this->reportExportOrchestrator->canAccessExport($payload, $request->user())) {
            return response()->json([
                'error' => 'You are not authorized to access this export.',
            ], 403);
        }

        return response()->json([
            'export_id' => $exportId,
            'status' => $payload['status'] ?? 'queued',
            'filename' => $payload['filename'] ?? null,
            'error' => $payload['error'] ?? null,
        ]);
    }

    public function downloadExport(Request $request, string $exportId)
    {
        $payload = $this->reportExportOrchestrator->getExport($exportId);

        if (! is_array($payload)) {
            abort(404, 'Export not found.');
        }

        if (! $this->reportExportOrchestrator->canAccessExport($payload, $request->user())) {
            abort(403);
        }

        if (($payload['status'] ?? null) !== 'completed') {
            abort(409, 'Export is not ready yet.');
        }

        $filePath = $payload['file_path'] ?? null;

        if (! is_string($filePath) || $filePath === '' || ! Storage::disk('local')->exists($filePath)) {
            abort(404, 'Export file not found.');
        }

        $filename = is_string($payload['filename'] ?? null) && $payload['filename'] !== ''
            ? $payload['filename']
            : basename($filePath);

        return Response::download(Storage::disk('local')->path($filePath), $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Download submission attachment.
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadAttachment(int $id)
    {
        $attachment = SubmissionAttachment::findOrFail($id);

        $submission = $attachment->canonicalSubmission;
        $user = auth()->user();

        if (! $submission || ($submission->account_id !== $user->account_id && ! $user->hasPermission('submissions.override'))) {
            abort(403);
        }

        if (! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404, 'File not found');
        }

        $fullPath = Storage::disk('local')->path($attachment->file_path);

        return Response::download($fullPath, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Preview submission attachment (inline view).
     *
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\StreamedResponse
     */
    public function previewAttachment(int $id)
    {
        $attachment = SubmissionAttachment::findOrFail($id);

        $submission = $attachment->canonicalSubmission;
        $user = auth()->user();

        if (! $submission || ($submission->account_id !== $user->account_id && ! $user->hasPermission('submissions.override'))) {
            abort(403);
        }

        if (! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404, 'File not found');
        }

        $mimeType = $attachment->mime_type;
        $noSniff = ['X-Content-Type-Options' => 'nosniff'];

        // MIME not in allowlist → force download as safe octet-stream (prevents stored XSS)
        if (! in_array($mimeType, self::PREVIEW_MIME_ALLOWLIST, true)) {
            return Response::download(
                Storage::disk('local')->path($attachment->file_path),
                $attachment->original_name,
                array_merge(['Content-Type' => 'application/octet-stream'], $noSniff)
            );
        }

        // Stream allowed MIME types inline (avoids file_get_contents memory exhaustion)
        return response()->stream(function () use ($attachment): void {
            $stream = Storage::disk('local')->readStream($attachment->file_path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, array_merge($noSniff, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $attachment->original_name
            ),
        ]));
    }

    /**
     * Cross-form comparison: return metric values for a set of form IDs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function compare(CompareRequest $request)
    {
        $validated = $request->validated();
        $formIds = array_map('intval', (array) $validated['form_ids']);
        $metric = (string) ($validated['metric'] ?? 'submission_count');
        $dateRange = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ];

        try {
            $results = $this->crossFormComparisonService->compare($formIds, $metric, $dateRange);

            return response()->json([
                'data' => $results,
                'metric' => $metric,
            ]);
        } catch (Throwable $exception) {
            Log::error('Cross-form comparison failed.', [
                'form_ids' => $formIds,
                'metric' => $metric,
                'user_id' => $request->user()?->account_id,
                'exception' => $exception,
            ]);

            return response()->json(['error' => 'Could not run comparison.'], 500);
        }
    }

    /**
     * Return aggregated chart data (trend, status breakdown, field distribution)
     * for the given filter set as JSON.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function chartData(ReportsFilterRequest $request)
    {
        $filters = $request->validated();
        $fieldKey = is_string($request->query('field_key')) ? trim((string) $request->query('field_key')) : null;
        $formId = (int) ($filters['form_id'] ?? 0);

        try {
            $form = Form::with(['fields' => fn ($q) => $q->orderBy('field_order')])->findOrFail($formId);
            $data = $this->chartDataService->getChartData($form, $filters, $fieldKey ?: null);

            return response()->json($data);
        } catch (Throwable $exception) {
            Log::error('Failed to load chart data.', [
                'form_id' => $formId,
                'user_id' => $request->user()?->account_id,
                'filters' => $filters,
                'exception' => $exception,
            ]);

            return response()->json(['error' => 'Could not load chart data.'], 500);
        }
    }

    /**
     * Aggregate submission data: group by a column, apply an aggregate function.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function aggregate(AggregateRequest $request)
    {
        $validated = $request->validated();
        $formId = (int) $validated['form_id'];

        try {
            $form = Form::with(['fields' => fn ($q) => $q->orderBy('field_order')])->findOrFail($formId);

            $results = $this->aggregationService->aggregate(
                form: $form,
                filters: $validated,
                groupByColumn: $validated['group_by'],
                aggFunction: $validated['function'],
                aggColumn: $validated['column'] ?? null,
            );

            return response()->json(['data' => $results]);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            Log::error('Aggregation query failed.', [
                'form_id' => $formId,
                'user_id' => $request->user()?->id,
                'validated' => $validated,
                'exception' => $exception,
            ]);

            return response()->json(['error' => 'Could not compute aggregation.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Assemble the full report payload consumed by the Inertia page / JSON API.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function assembleReportPayload(array $filters): array
    {
        $startedAt = microtime(true);

        $formId = (int) $filters['form_id'];
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = (int) ($filters['page'] ?? 1);

        $form = Form::with(['fields' => fn ($query) => $query->orderBy('field_order')])->findOrFail($formId);
        $allColumns = $this->columnRegistry->resolveAllColumns($form);
        $selectedColumns = $this->columnRegistry->resolveSelectedColumns($allColumns, $filters['select'] ?? null);
        $selectedColumnKeys = $this->columnRegistry->keys($selectedColumns);
        $formFieldTypes = $this->columnRegistry->resolveFormFieldTypes($form);
        $builderCapabilities = $this->queryBuilderService->resolveBuilderCapabilities($allColumns, $formFieldTypes);

        $filteredQuery = $this->submissionQueryService->buildFilteredSubmissionQuery($formId, $filters, $formFieldTypes);

        $pagedQuery = (clone $filteredQuery)
            ->with([
                'submitter:account_id,username,email',
                'submitter.profile:account_id,first_name,last_name',
                'attachments:id,submission_id,original_name,file_path,mime_type,uploaded_by',
            ]);

        $hasCustomSort = $this->queryBuilderService->applyBuilderSort($pagedQuery, $filters['sort'] ?? null, $formFieldTypes);

        if (! $hasCustomSort) {
            $pagedQuery
                ->orderByDesc('submitted_at')
                ->orderByDesc('id');
        }

        $paginator = $pagedQuery
            ->paginate($perPage, [
                'id',
                'form_id',
                'account_id',
                'submission_status',
                'current_workflow_status',
                'payload_json',
                'submitted_at',
                'created_at',
            ], 'page', $page)
            ->withQueryString();

        $submissionIds = $paginator->getCollection()->pluck('id')->all();
        $latestProgresses = $this->submissionRowMapper->latestProgressBySubmission($submissionIds);
        $approvedSnapshots = $this->submissionRowMapper->latestApprovedSnapshotBySubmission($submissionIds);

        $rows = $paginator->getCollection()
            ->map(fn ($submission) => $this->submissionRowMapper->mapSubmissionRow(
                submission: $submission,
                form: $form,
                latestProgress: $latestProgresses[$submission->id] ?? null,
                snapshot: $approvedSnapshots[$submission->id] ?? null,
            ))
            ->values()
            ->all();

        $rows = $this->queryBuilderService->projectRows($rows, $selectedColumnKeys);

        $result = [
            'form' => [
                'id' => $form->id,
                'form_name' => $form->form_name,
                'form_code' => $form->form_code,
                'status' => $form->status,
            ],
            'columns' => $selectedColumns,
            'available_columns' => $allColumns,
            'builder' => $builderCapabilities,
            'filters' => $this->normalizeFilters($filters, $perPage, $page),
            'summary' => $this->reportSummaryService->buildSummary($filteredQuery, $filters),
            'submissions' => $rows,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        $this->warnIfSlow('reports.filtered_data', $startedAt, [
            'form_id' => $formId,
            'total' => $paginator->total(),
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters, int $perPage, int $page): array
    {
        return [
            'form_id' => isset($filters['form_id']) ? (int) $filters['form_id'] : null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'submission_status' => $filters['submission_status'] ?? null,
            'account_id' => isset($filters['account_id']) ? (int) $filters['account_id'] : null,
            'submitter' => $filters['submitter'] ?? null,
            'select' => isset($filters['select']) && is_array($filters['select']) ? array_values($filters['select']) : [],
            'filters' => isset($filters['filters']) && is_array($filters['filters']) ? array_values($filters['filters']) : [],
            'sort' => isset($filters['sort']) && is_array($filters['sort']) ? $filters['sort'] : null,
            'per_page' => $perPage,
            'page' => $page,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warnIfSlow(string $operation, float $startedAt, array $context = []): void
    {
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($elapsedMs < self::SLOW_QUERY_THRESHOLD_MS) {
            return;
        }

        Log::warning('Reports query exceeded slow-query threshold.', [
            'operation' => $operation,
            'elapsed_ms' => $elapsedMs,
            ...$context,
        ]);
    }
}
