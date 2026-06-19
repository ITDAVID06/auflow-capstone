import React, { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { RefreshCw } from "lucide-react";
import { buildReportQueryParams } from "../queryBuilder";
import { useReportFilters } from "../hooks/useReportFilters";
import {
  ReportBuilderCapabilities,
  ReportColumn,
  ReportData,
  ReportFiltersState,
  ReportPagination,
  ReportSubmission,
  ReportView,
} from "../types";
import { FilterBar } from "../components/FilterBar";
import { AdvancedFilters } from "../components/AdvancedFilters";
import { SavedViewsPills } from "../components/SavedViewsPills";
import { ColumnSelector } from "../components/ColumnSelector";
import { SubmissionTable } from "../components/SubmissionTable";
import { ExportButton } from "../components/ExportButton";

interface DataTabProps {
  formId: number;
  onAsyncExport: (exportId: string) => void;
  onFiltersChange?: (filters: ReportFiltersState) => void;
  filterOverride?: { date_from: string; date_to: string } | null;
}

const DEFAULT_FILTERS = (formId: number): ReportFiltersState => ({
  form_id: formId,
  date_from: null,
  date_to: null,
  submission_status: "",
  account_id: null,
  submitter: null,
  select: [],
  filters: [],
  sort: null,
  per_page: 25,
  page: 1,
});

export const DataTab: React.FC<DataTabProps> = ({ formId, onAsyncExport, onFiltersChange, filterOverride }) => {
  const [filters, setFilters] = useState<ReportFiltersState>(() => DEFAULT_FILTERS(formId));
  const [submissions, setSubmissions] = useState<ReportSubmission[]>([]);
  const [pagination, setPagination] = useState<ReportPagination>({
    current_page: 1, last_page: 1, per_page: 25, total: 0,
  });
  const [columns, setColumns] = useState<ReportColumn[]>([]);
  const [availableColumns, setAvailableColumns] = useState<ReportColumn[]>([]);
  const [capabilities, setCapabilities] = useState<ReportBuilderCapabilities | null>(null);
  const [views, setViews] = useState<ReportView[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const fetchData = useCallback((overrideFilters?: ReportFiltersState) => {
    const active = overrideFilters ?? filters;
    abortRef.current?.abort();
    abortRef.current = new AbortController();
    setLoading(true);
    setError(null);

    axios
      .get<ReportData>(route("reports.form-submissions"), {
        params: buildReportQueryParams(active),
        signal: abortRef.current.signal,
      })
      .then((r) => {
        setSubmissions(r.data.submissions);
        setPagination(r.data.pagination);
        setColumns(r.data.columns);
        if (r.data.available_columns) setAvailableColumns(r.data.available_columns);
        if (r.data.builder) setCapabilities(r.data.builder);
      })
      .catch((e) => {
        if (!axios.isCancel(e)) setError("Could not load submissions. Please try again.");
      })
      .finally(() => setLoading(false));
  }, [filters]);

  // Fetch saved views once on mount
  useEffect(() => {
    axios
      .get<ReportView[]>(route("reports.views.index"), { params: { form_id: formId } })
      .then((r) => setViews(r.data))
      .catch(() => {/* non-fatal */});
  }, [formId]);

  // Re-fetch whenever filters change (debounced via useEffect dependency)
  useEffect(() => {
    fetchData();
    onFiltersChange?.(filters);
    return () => abortRef.current?.abort();
  }, [filters]);  // eslint-disable-line react-hooks/exhaustive-deps

  const patchFilters = (patch: Partial<ReportFiltersState>) => {
    setFilters((prev) => ({ ...prev, ...patch, page: patch.page ?? 1 }));
  };

  // Apply external date override (e.g., from chart click in OverviewTab)
  const prevFilterOverrideRef = useRef(filterOverride);
  useEffect(() => {
    if (filterOverride && filterOverride !== prevFilterOverrideRef.current) {
      prevFilterOverrideRef.current = filterOverride;
      patchFilters({ date_from: filterOverride.date_from, date_to: filterOverride.date_to });
    }
  }, [filterOverride]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="space-y-4">
      {/* Saved views */}
      <SavedViewsPills
        views={views}
        currentFilters={filters}
        onLoad={(f) => setFilters({ ...f, form_id: formId })}
        onViewsChange={setViews}
      />

      {/* Filter bar */}
      <FilterBar filters={filters} onChange={patchFilters} />

      {/* Advanced filters (collapsed) */}
      {capabilities && (
        <AdvancedFilters
          filters={filters.filters}
          capabilities={capabilities}
          onChange={(f) => patchFilters({ filters: f, page: 1 })}
        />
      )}

      {/* Toolbar row: column selector + export */}
      <div className="flex items-center justify-between">
        <ColumnSelector
          available={availableColumns}
          selected={filters.select.length > 0 ? filters.select : columns.map((c) => c.key)}
          onChange={(sel) => patchFilters({ select: sel })}
        />
        <ExportButton filters={filters} onAsyncExport={onAsyncExport} />
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertDescription className="flex items-center gap-2">
            {error}
            <Button variant="ghost" size="sm" onClick={() => fetchData()}>
              <RefreshCw className="h-3 w-3 mr-1" /> Retry
            </Button>
          </AlertDescription>
        </Alert>
      )}

      <SubmissionTable
        columns={columns}
        submissions={submissions}
        pagination={pagination}
        onPageChange={(p) => patchFilters({ page: p })}
        loading={loading}
      />
    </div>
  );
};
