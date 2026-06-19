<?php

namespace App\Modules\Reports\Services;

use App\Modules\FormBuilder\Models\Form;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportWriter
{
    public function __construct(
        private readonly SubmissionQueryService $submissionQueryService,
        private readonly SubmissionRowMapper $submissionRowMapper,
        private readonly ReportColumnRegistry $columnRegistry,
        private readonly ReportQueryBuilderService $queryBuilderService,
    ) {}

    /**
     * Stream CSV rows directly to the browser.
     *
     * @param  array<string, mixed>  $filters
     */
    public function streamCsvExport(array $filters): StreamedResponse
    {
        $formId = (int) ($filters['form_id'] ?? 0);
        $filename = sprintf('form_%d_report_%s.csv', $formId, now()->format('Y-m-d_His'));

        return response()->streamDownload(
            function () use ($filters): void {
                $this->writeCsvToOutput($filters);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * Build a full in-memory CSV data array (used by the async queue job).
     *
     * @param  array<string, mixed>  $filters
     * @return array{filename: string, content: string}
     */
    public function buildCsvExportData(array $filters): array
    {
        $tableData = $this->buildTabularExportData($filters);
        $formId = (int) $tableData['form']['id'];
        $columns = $tableData['columns'];
        $rows = $tableData['rows'];

        // Use a temporary stream so we get proper CSV escaping without
        // accumulating with string concatenation.
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_column($columns, 'label'));

        foreach ($rows as $row) {
            $csvRow = [];
            foreach ($columns as $column) {
                $csvRow[] = $row[$column['key']] ?? '';
            }

            fputcsv($handle, $csvRow);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return [
            'filename' => sprintf('form_%d_report_%s.csv', $formId, now()->format('Y-m-d_His')),
            'content' => $content !== false ? $content : '',
        ];
    }

    /**
     * Build full in-memory tabular data (used by PDF export and async CSV).
     *
     * @param  array<string, mixed>  $filters
     * @return array{form: array<string, mixed>, columns: array<int, array{key: string, label: string, type: string}>, rows: array<int, array<string, string>>, generated_at: string}
     */
    public function buildTabularExportData(array $filters): array
    {
        $formId = (int) $filters['form_id'];
        $form = Form::with(['fields' => fn ($query) => $query->orderBy('field_order')])->findOrFail($formId);
        $allColumns = $this->columnRegistry->resolveAllColumns($form);
        $columns = $this->columnRegistry->resolveSelectedColumns($allColumns, $filters['select'] ?? null);
        $selectedColumnKeys = $this->columnRegistry->keys($columns);
        $formFieldTypes = $this->columnRegistry->resolveFormFieldTypes($form);
        $exportLimit = $this->resolveExportLimit($filters['export_limit'] ?? null);

        $query = $this->submissionQueryService->buildFilteredSubmissionQuery($formId, $filters, $formFieldTypes)
            ->with([
                'submitter:account_id,username,email',
                'submitter.profile:account_id,first_name,last_name',
                'attachments:id,submission_id,original_name,file_path,mime_type,uploaded_by',
            ]);

        $hasCustomSort = $this->queryBuilderService->applyBuilderSort($query, $filters['sort'] ?? null, $formFieldTypes);

        if (! $hasCustomSort) {
            $query->orderBy('id');
        }

        $rows = [];
        $exportedCount = 0;

        $query->chunk(500, function ($chunk) use ($columns, $selectedColumnKeys, $form, $exportLimit, &$rows, &$exportedCount): bool {
            $submissionIds = $chunk->pluck('id')->all();
            $latestProgresses = $this->submissionRowMapper->latestProgressBySubmission($submissionIds);
            $approvedSnapshots = $this->submissionRowMapper->latestApprovedSnapshotBySubmission($submissionIds);

            foreach ($chunk as $submission) {
                if ($exportLimit !== null && $exportedCount >= $exportLimit) {
                    return false;
                }

                $row = $this->submissionRowMapper->mapSubmissionRow(
                    submission: $submission,
                    form: $form,
                    latestProgress: $latestProgresses[$submission->id] ?? null,
                    snapshot: $approvedSnapshots[$submission->id] ?? null,
                );

                $projectedRows = $this->queryBuilderService->projectRows([$row], $selectedColumnKeys);
                $projectedRow = $projectedRows[0] ?? [];

                $rows[] = $this->submissionRowMapper->normalizeExportRow($projectedRow, $columns);
                $exportedCount++;
            }

            return true;
        });

        return [
            'form' => [
                'id' => $form->id,
                'form_name' => $form->form_name,
                'form_code' => $form->form_code,
                'status' => $form->status,
            ],
            'columns' => $columns,
            'rows' => $rows,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Streams CSV rows directly to php://output, flushing after each chunk.
     * No in-memory accumulation — rows are written and flushed immediately.
     *
     * @param  array<string, mixed>  $filters
     */
    private function writeCsvToOutput(array $filters): void
    {
        $formId = (int) $filters['form_id'];
        $form = Form::with(['fields' => fn ($q) => $q->orderBy('field_order')])->findOrFail($formId);
        $allColumns = $this->columnRegistry->resolveAllColumns($form);
        $columns = $this->columnRegistry->resolveSelectedColumns($allColumns, $filters['select'] ?? null);
        $selectedColumnKeys = $this->columnRegistry->keys($columns);
        $formFieldTypes = $this->columnRegistry->resolveFormFieldTypes($form);
        $exportLimit = $this->resolveExportLimit($filters['export_limit'] ?? null);

        $query = $this->submissionQueryService->buildFilteredSubmissionQuery($formId, $filters, $formFieldTypes)
            ->with([
                'submitter:account_id,username,email',
                'submitter.profile:account_id,first_name,last_name',
                'attachments:id,submission_id,original_name,file_path,mime_type,uploaded_by',
            ]);

        $hasCustomSort = $this->queryBuilderService->applyBuilderSort($query, $filters['sort'] ?? null, $formFieldTypes);

        if (! $hasCustomSort) {
            $query->orderBy('id');
        }

        $handle = fopen('php://output', 'w');

        if ($handle === false) {
            throw new \RuntimeException('Could not open php://output for CSV streaming.');
        }

        // Write the header row immediately.
        fputcsv($handle, array_column($columns, 'label'));

        $exportedCount = 0;

        $query->chunk(500, function ($chunk) use ($handle, $columns, $selectedColumnKeys, $form, $exportLimit, &$exportedCount): bool {
            $submissionIds = $chunk->pluck('id')->all();
            $latestProgresses = $this->submissionRowMapper->latestProgressBySubmission($submissionIds);
            $approvedSnapshots = $this->submissionRowMapper->latestApprovedSnapshotBySubmission($submissionIds);

            foreach ($chunk as $submission) {
                if ($exportLimit !== null && $exportedCount >= $exportLimit) {
                    return false;
                }

                $row = $this->submissionRowMapper->mapSubmissionRow(
                    submission: $submission,
                    form: $form,
                    latestProgress: $latestProgresses[$submission->id] ?? null,
                    snapshot: $approvedSnapshots[$submission->id] ?? null,
                );

                $projectedRows = $this->queryBuilderService->projectRows([$row], $selectedColumnKeys);
                $projectedRow = $projectedRows[0] ?? [];
                $normalized = $this->submissionRowMapper->normalizeExportRow($projectedRow, $columns);

                $csvRow = [];
                foreach ($columns as $column) {
                    $csvRow[] = $normalized[$column['key']] ?? '';
                }

                fputcsv($handle, $csvRow);
                $exportedCount++;
            }

            // Flush after each chunk so data reaches the client incrementally.
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            return true;
        });

        fclose($handle);
    }

    /**
     * @param  mixed  $rawLimit
     */
    private function resolveExportLimit($rawLimit): ?int
    {
        if (! is_numeric($rawLimit)) {
            return null;
        }

        $limit = (int) $rawLimit;

        return $limit > 0 ? $limit : null;
    }
}
