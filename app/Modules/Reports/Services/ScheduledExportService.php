<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Models\ScheduledExport;
use App\Modules\UserManagement\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ScheduledExportService
{
    public function __construct(
        private readonly CsvExportWriter $csvExportWriter,
    ) {}

    /**
     * List scheduled exports belonging to a user, optionally filtered by form.
     */
    public function listForUser(int $userId, ?int $formId = null): Collection
    {
        return ScheduledExport::query()
            ->where('created_by', $userId)
            ->when($formId, fn ($q) => $q->where('form_id', $formId))
            ->with('form:id,form_name,form_code')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a new scheduled export owned by the given user.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, int $userId): ScheduledExport
    {
        /** @var ScheduledExport $export */
        $export = ScheduledExport::create([...$validated, 'created_by' => $userId]);

        return $export->load('form:id,form_name,form_code');
    }

    /**
     * Update an existing scheduled export (owner-scoped, enforced by controller).
     *
     * @param  array<string, mixed>  $validated
     */
    public function update(ScheduledExport $export, array $validated): ScheduledExport
    {
        $export->fill($validated)->save();

        return $export->load('form:id,form_name,form_code');
    }

    /**
     * Delete a scheduled export.
     */
    public function delete(ScheduledExport $export): void
    {
        $export->delete();
    }

    /**
     * Return all active exports whose last_sent_at is past the due threshold.
     */
    public function findDue(): Collection
    {
        $now = now();

        return ScheduledExport::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('last_sent_at')
                    ->orWhere(function ($q) use ($now) {
                        $q->where('frequency', 'daily')
                            ->where('last_sent_at', '<=', $now->copy()->subDay());
                    })
                    ->orWhere(function ($q) use ($now) {
                        $q->where('frequency', 'weekly')
                            ->where('last_sent_at', '<=', $now->copy()->subWeek());
                    })
                    ->orWhere(function ($q) use ($now) {
                        $q->where('frequency', 'monthly')
                            ->where('last_sent_at', '<=', $now->copy()->subMonth());
                    });
            })
            ->with('form:id,form_name,form_code')
            ->get();
    }

    /**
     * Build the export file (CSV or PDF) running as the export owner.
     *
     * Returns ['filename' => string, 'content' => string].
     *
     * @return array{filename: string, content: string}
     */
    public function buildExportAttachment(ScheduledExport $export): array
    {
        /** @var array<string, mixed> $filterState */
        $filterState = is_array($export->filter_state) ? $export->filter_state : [];

        // Always enforce the export's own form_id (overrides any saved filter_state value).
        $filterState['form_id'] = $export->form_id;

        // Run the export as the owning user so row-access scoping uses their permissions.
        $previousUser = Auth::user();
        $owner = User::find($export->created_by);

        if ($owner) {
            Auth::setUser($owner);
        }

        try {
            if ($export->export_type === 'csv') {
                return $this->csvExportWriter->buildCsvExportData($filterState);
            }

            // PDF path: reuse tabular data builder + DomPDF.
            $tableData = $this->csvExportWriter->buildTabularExportData($filterState);
            $formName = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($tableData['form']['form_name'] ?? 'Report'));
            $filename = sprintf('Report_%s_%s.pdf', $formName, now()->format('Y-m-d'));

            $pdf = Pdf::loadView('reports.export-pdf-download', [
                'form' => $tableData['form'],
                'columns' => $tableData['columns'],
                'rows' => $tableData['rows'],
                'filters' => $filterState,
                'generatedAt' => $tableData['generated_at'],
            ])->setPaper('a4', 'landscape');

            return [
                'filename' => $filename,
                'content' => $pdf->output(),
            ];
        } finally {
            if ($previousUser !== null) {
                Auth::setUser($previousUser);
            } else {
                Auth::logout();
            }
        }
    }
}
