import { useCallback, useEffect, useMemo, useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import {
  ReportColumn,
  ReportData,
  ReportFilterClause,
  ReportFilterGroup,
  ReportFilterItem,
  ReportFilterOperator,
  ReportFiltersState,
  ReportSortState,
  SubmissionStatus,
  isFilterGroup,
} from "../types";
import { buildReportQueryParams } from "../queryBuilder";
import { computePresetDateRange } from "../datePresets";

const DEFAULT_BUILDER_OPERATORS: ReportFilterOperator[] = [
  "eq",
  "neq",
  "contains",
  "starts_with",
  "ends_with",
  "gt",
  "gte",
  "lt",
  "lte",
  "is_null",
  "is_not_null",
];

const DEFAULT_DATE_RANGE = computePresetDateRange("last30");

/** Normalise a raw filter item from the server into a typed `ReportFilterItem`. */
const normalizeFilterItem = (item: unknown): ReportFilterItem | null => {
  if (!item || typeof item !== "object" || Array.isArray(item)) return null;
  const obj = item as Record<string, unknown>;

  // Group node
  if ("logic" in obj) {
    const group: ReportFilterGroup = {
      logic: obj["logic"] === "or" ? "or" : "and",
      filters: Array.isArray(obj["filters"])
        ? (obj["filters"]
            .map((f) => normalizeFilterItem(f))
            .filter((f): f is ReportFilterClause => f !== null && !isFilterGroup(f)))
        : [],
    };
    return group;
  }

  // Leaf clause
  const column = obj["column"];
  const operator = obj["operator"];
  if (typeof column !== "string" || typeof operator !== "string") return null;

  const value = obj["value"];
  const clause: ReportFilterClause = {
    column,
    operator: operator as ReportFilterOperator,
    value:
      value === null || value === undefined
        ? ""
        : Array.isArray(value)
          ? value
          : String(value),
  };
  return clause;
};

const toFilterState = (data: ReportData): ReportFiltersState => ({
  form_id: data.form.id,
  date_from: data.filters.date_from ?? null,
  date_to: data.filters.date_to ?? null,
  submission_status: (data.filters.submission_status ?? "") as SubmissionStatus,
  account_id: data.filters.account_id ?? null,
  submitter: data.filters.submitter ?? null,
  select:
    Array.isArray(data.filters.select) && data.filters.select.length > 0
      ? data.filters.select
      : data.columns.map((column) => column.key),
  filters: Array.isArray(data.filters.filters)
    ? (data.filters.filters
        .map((item) => normalizeFilterItem(item))
        .filter((item): item is ReportFilterItem => item !== null))
    : [],
  sort:
    data.filters.sort &&
    typeof data.filters.sort.column === "string" &&
    typeof data.filters.sort.direction === "string"
      ? {
          column: data.filters.sort.column,
          direction: data.filters.sort.direction === "asc" ? "asc" : "desc",
        }
      : null,
  per_page: data.filters.per_page ?? 25,
  page: data.filters.page ?? 1,
});

export interface UseReportFiltersReturn {
  filters: ReportFiltersState | null;
  setFilters: React.Dispatch<React.SetStateAction<ReportFiltersState | null>>;
  submitterInput: string;
  setSubmitterInput: (value: string) => void;
  loading: boolean;
  normalizedSubmitter: string;
  availableColumns: ReportColumn[];
  builderCapabilities: {
    filterable_columns: ReportColumn[];
    sortable_columns: ReportColumn[];
    operators_by_column: Record<string, ReportFilterOperator[]>;
  };
  hasActiveFilters: boolean;
  sendFilterRequest: (nextFilters: ReportFiltersState) => void;
  handleApplyFilters: () => void;
  handleResetFilters: () => void;
  handlePageChange: (page: number) => void;
  handleStatusChange: (value: SubmissionStatus) => void;
  handleDateFromChange: (value: string | null) => void;
  handleDateToChange: (value: string | null) => void;
  handlePerPageChange: (value: number) => void;
  handleSelectedColumnsChange: (select: string[]) => void;
  handleBuilderFiltersChange: (builderFilters: ReportFilterItem[]) => void;
  handleSortChange: (sort: ReportSortState | null) => void;
}

export function useReportFilters(reportData: ReportData | null): UseReportFiltersReturn {
  const [filters, setFilters] = useState<ReportFiltersState | null>(
    reportData ? toFilterState(reportData) : null,
  );
  const [submitterInput, setSubmitterInput] = useState(reportData?.filters.submitter ?? "");
  const [loading, setLoading] = useState(false);

  const normalizedSubmitter = useMemo(() => submitterInput.trim(), [submitterInput]);

  const availableColumns = useMemo(
    () => reportData?.available_columns ?? reportData?.columns ?? [],
    [reportData],
  );

  const builderCapabilities = useMemo(() => {
    const fallbackOperatorsByColumn = availableColumns.reduce<Record<string, ReportFilterOperator[]>>(
      (carry, column) => {
        carry[column.key] = DEFAULT_BUILDER_OPERATORS;
        return carry;
      },
      {},
    );

    if (!reportData?.builder) {
      return {
        filterable_columns: availableColumns,
        sortable_columns: availableColumns,
        operators_by_column: fallbackOperatorsByColumn,
      };
    }

    return {
      filterable_columns:
        reportData.builder.filterable_columns?.length > 0
          ? reportData.builder.filterable_columns
          : availableColumns,
      sortable_columns:
        reportData.builder.sortable_columns?.length > 0
          ? reportData.builder.sortable_columns
          : availableColumns,
      operators_by_column:
        Object.keys(reportData.builder.operators_by_column ?? {}).length > 0
          ? reportData.builder.operators_by_column
          : fallbackOperatorsByColumn,
    };
  }, [availableColumns, reportData]);

  const hasActiveFilters = useMemo(() => {
    if (!filters) return false;

    return Boolean(
      filters.date_from ||
        filters.date_to ||
        filters.submission_status ||
        filters.account_id ||
        normalizedSubmitter ||
        filters.filters.length > 0 ||
        filters.sort ||
        (availableColumns.length > 0 && filters.select.length !== availableColumns.length),
    );
  }, [availableColumns.length, filters, normalizedSubmitter]);

  // Sync state when reportData changes (e.g. Inertia navigation to a different form)
  useEffect(() => {
    if (!reportData) {
      setFilters(null);
      setSubmitterInput("");
      return;
    }

    const nextFilters = toFilterState(reportData);
    setFilters(nextFilters);
    setSubmitterInput(nextFilters.submitter ?? "");
  }, [reportData]);

  // Debounce submitter input → filters.submitter (350ms)
  useEffect(() => {
    if (!filters) return;

    const timeout = setTimeout(() => {
      setFilters((previous) => {
        if (!previous) return previous;

        const normalized = submitterInput.trim();
        const nextSubmitter = normalized === "" ? null : normalized;

        if (previous.submitter === nextSubmitter) return previous;

        return { ...previous, submitter: nextSubmitter };
      });
    }, 350);

    return () => window.clearTimeout(timeout);
  }, [submitterInput, filters]);

  const sendFilterRequest = useCallback((nextFilters: ReportFiltersState) => {
    setLoading(true);

    router.get(route("reports.index"), buildReportQueryParams(nextFilters), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      queryStringArrayFormat: "indices",
      onError: () => {
        toast.error("Unable to load report data with the selected filters.");
      },
      onFinish: () => {
        setLoading(false);
      },
    });
  }, []);

  const handleApplyFilters = useCallback(() => {
    if (!filters) return;

    sendFilterRequest({ ...filters, submitter: normalizedSubmitter || null, page: 1 });
  }, [filters, normalizedSubmitter, sendFilterRequest]);

  const handleResetFilters = useCallback(() => {
    if (!filters) return;

    const cleared: ReportFiltersState = {
      ...filters,
      date_from: DEFAULT_DATE_RANGE.date_from,
      date_to: DEFAULT_DATE_RANGE.date_to,
      submission_status: "",
      account_id: null,
      submitter: null,
      select:
        availableColumns.length > 0
          ? availableColumns.map((column) => column.key)
          : filters.select,
      filters: [],
      sort: null,
      page: 1,
    };

    setFilters(cleared);
    setSubmitterInput("");
    sendFilterRequest(cleared);
  }, [availableColumns, filters, sendFilterRequest]);

  const handlePageChange = useCallback(
    (page: number) => {
      if (!filters) return;

      sendFilterRequest({ ...filters, submitter: normalizedSubmitter || null, page });
    },
    [filters, normalizedSubmitter, sendFilterRequest],
  );

  const handleStatusChange = useCallback(
    (value: SubmissionStatus) =>
      setFilters((previous) =>
        previous ? { ...previous, submission_status: value } : previous,
      ),
    [],
  );

  const handleDateFromChange = useCallback(
    (value: string | null) =>
      setFilters((previous) => (previous ? { ...previous, date_from: value } : previous)),
    [],
  );

  const handleDateToChange = useCallback(
    (value: string | null) =>
      setFilters((previous) => (previous ? { ...previous, date_to: value } : previous)),
    [],
  );

  const handlePerPageChange = useCallback(
    (value: number) =>
      setFilters((previous) =>
        previous ? { ...previous, per_page: value, page: 1 } : previous,
      ),
    [],
  );

  const handleSelectedColumnsChange = useCallback(
    (select: string[]) =>
      setFilters((previous) => (previous ? { ...previous, select } : previous)),
    [],
  );

  const handleBuilderFiltersChange = useCallback(
    (builderFilters: ReportFilterItem[]) =>
      setFilters((previous) =>
        previous ? { ...previous, filters: builderFilters } : previous,
      ),
    [],
  );

  const handleSortChange = useCallback(
    (sort: ReportSortState | null) =>
      setFilters((previous) => (previous ? { ...previous, sort } : previous)),
    [],
  );

  return {
    filters,
    setFilters,
    submitterInput,
    setSubmitterInput,
    loading,
    normalizedSubmitter,
    availableColumns,
    builderCapabilities,
    hasActiveFilters,
    sendFilterRequest,
    handleApplyFilters,
    handleResetFilters,
    handlePageChange,
    handleStatusChange,
    handleDateFromChange,
    handleDateToChange,
    handlePerPageChange,
    handleSelectedColumnsChange,
    handleBuilderFiltersChange,
    handleSortChange,
  };
}
