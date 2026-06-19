export type SubmissionStatus = "pending" | "approved" | "rejected" | "completed" | "";
export type ReportFilterOperator =
  | "eq"
  | "neq"
  | "contains"
  | "starts_with"
  | "ends_with"
  | "gt"
  | "gte"
  | "lt"
  | "lte"
  | "is_null"
  | "is_not_null"
  | "in"
  | "between";

export interface ReportFilterClause {
  column: string;
  operator: ReportFilterOperator;
  // scalar string for most operators; string[] for 'in'/'between' handled at sanitize time
  value: string | string[] | null;
}

export interface ReportFilterGroup {
  logic: "and" | "or";
  filters: ReportFilterClause[];
}

/** A root-level item in `ReportFiltersState.filters` is either a leaf clause or a group. */
export type ReportFilterItem = ReportFilterClause | ReportFilterGroup;

/** Type guard: is this item a group node? */
export function isFilterGroup(item: ReportFilterItem): item is ReportFilterGroup {
  return "logic" in item;
}

export interface ReportSortState {
  column: string;
  direction: "asc" | "desc";
}

export interface ReportColumn {
  key: string;
  label: string;
  type: string;
}

export interface Attachment {
  id: number;
  original_name: string;
  file_path: string;
  mime_type: string;
  uploaded_by: number;
  is_image: boolean;
  is_pdf: boolean;
}

export interface Snapshot {
  id: number;
  public_id: string;
  status: string;
  approved_at: string | null;
  comment: string | null;
}

export interface ReportSubmission {
  id: number;
  canonical_submission_id: number;
  account_id: number;
  username: string | null;
  email: string | null;
  submitter_name: string;
  submission_status: string | null;
  workflow_status: string | null;
  workflow_action: string | null;
  created_at: string | null;
  attachments: Attachment[];
  attachment_count: number;
  snapshot: Snapshot | null;
  [key: string]: unknown;
}

export interface ReportForm {
  id: number;
  form_name: string;
  form_code: string;
  status: string;
}

export interface ReportFiltersState {
  form_id: number;
  date_from: string | null;
  date_to: string | null;
  submission_status: SubmissionStatus;
  account_id: number | null;
  submitter: string | null;
  select: string[];
  filters: ReportFilterItem[];
  sort: ReportSortState | null;
  per_page: number;
  page: number;
}

export interface ReportSummary {
  total_submissions: number;
  status_counts: {
    approved: number;
    rejected: number;
    pending: number;
    completed: number;
  };
  average_completion_seconds: number | null;
  average_completion_human: string | null;
}

export interface ReportPagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ReportBuilderCapabilities {
  filterable_columns: ReportColumn[];
  sortable_columns: ReportColumn[];
  operators_by_column: Record<string, ReportFilterOperator[]>;
}

export interface ReportData {
  form: ReportForm;
  columns: ReportColumn[];
  available_columns?: ReportColumn[];
  builder?: ReportBuilderCapabilities;
  filters: ReportFiltersState;
  summary: ReportSummary;
  submissions: ReportSubmission[];
  pagination: ReportPagination;
}

export interface AsyncExportStatus {
  export_id: string;
  status: "queued" | "processing" | "completed" | "failed";
  filename?: string | null;
  error?: string | null;
}

// ─── Tab architecture ────────────────────────────────────────────────────────

export type ReportTab = "overview" | "data" | "exports" | "compare";

export interface KpiData {
  total_submissions: number;
  approved: number;
  pending: number;
  avg_completion_human: string | null;
}

export interface TrendPoint {
  date: string;
  count: number;
}

export interface StatusBreakdownPoint {
  status: string;
  count: number;
}

export interface FieldDistributionPoint {
  value: string;
  count: number;
}

export interface ChartDataResponse {
  kpi: KpiData;
  trend: TrendPoint[];
  status_breakdown: StatusBreakdownPoint[];
  field_distribution: FieldDistributionPoint[];
  field_distribution_column: string | null;
  available_field_columns: { key: string; label: string }[];
}

export interface ScheduledExport {
  id: number;
  form_id: number;
  form: { id: number; form_name: string; form_code: string } | null;
  recipient_email: string;
  frequency: "daily" | "weekly" | "monthly";
  export_type: "csv" | "pdf";
  filter_state: Record<string, unknown> | null;
  is_active: boolean;
  last_sent_at: string | null;
  created_by: number;
}

export interface ReportView {
  id: number;
  form_id: number;
  name: string;
  filter_state: ReportFiltersState;
  created_by: number;
}

// FilterState snapshot suitable for storing in a ScheduledExport.filter_state
export type FilterStateSnapshot = Omit<ReportFiltersState, "form_id" | "page" | "per_page"> & {
  filters: ReportFilterItem[];
};
