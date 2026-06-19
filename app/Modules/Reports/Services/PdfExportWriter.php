<?php

namespace App\Modules\Reports\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfExportWriter
{
    public function __construct(
        private readonly CsvExportWriter $csvExportWriter,
    ) {}

    /**
     * Build a print-ready HTML response for browser PDF save.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildPdfResponse(array $filters, bool $autoprint = true): Response
    {
        $tableData = $this->csvExportWriter->buildTabularExportData($filters);
        $formId = (int) $tableData['form']['id'];
        $filename = sprintf('form_%d_report_%s.pdf', $formId, now()->format('Y-m-d_His'));

        return response()->view('reports.export-pdf', [
            'form' => $tableData['form'],
            'columns' => $tableData['columns'],
            'rows' => $tableData['rows'],
            'filters' => $filters,
            'generatedAt' => $tableData['generated_at'],
            'autoprint' => $autoprint,
        ], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Generate a true server-side PDF and return it as a streamed download.
     *
     * @param  array<string, mixed>  $filters
     */
    public function buildPdfDownloadResponse(array $filters): StreamedResponse
    {
        $tableData = $this->csvExportWriter->buildTabularExportData($filters);
        $formName = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $tableData['form']['form_name']);
        $filename = sprintf('Report_%s_%s.pdf', $formName, now()->format('Y-m-d'));

        $pdf = Pdf::loadView('reports.export-pdf-download', [
            'form' => $tableData['form'],
            'columns' => $tableData['columns'],
            'rows' => $tableData['rows'],
            'filters' => $filters,
            'generatedAt' => $tableData['generated_at'],
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
