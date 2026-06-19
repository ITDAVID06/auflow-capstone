import { useEffect, useMemo, useState } from "react";
import { Link } from "@inertiajs/react";
import {
    Eye,
    CheckCircle2,
    Clock,
    XCircle,
    ChevronUp,
    ChevronDown,
    ChevronsUpDown,
} from "lucide-react";
import Pagination from "@/components/shared/Pagination";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import TruncateTooltip from "@/components/TruncateTooltip";
import SubmissionStatusBadge from "./SubmissionStatusBadge";
import { formatDate } from "@/utils/dateTime";

export type WorkflowStep = {
  id: number;
  name: string;
  status: string;
  actor?: string | null;
  acted_at?: string | null;
  step_group?: number | null;
  step_order?: number | null;
};

export type WorkflowPreview = {
  steps: WorkflowStep[];
  current: string;
  count: number;
  completed: number;
};

export type Row = {
  id: number;
  form_id: number;
  submission_id: number;
  form_name: string;
  status: string;
  progress: number;
  submitted_at: string | null;
  submitter: string;
  started_at?: string | null;
  elapsed_seconds?: number | null;
  elapsed_human?: string | null;
  workflow_preview: WorkflowPreview;
};

type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type SortColumn = "form" | "submitter" | "status" | "submitted";

export type Props = {
  requests: Row[];
  pagination?: PaginationMeta;
  onPageChange?: (page: number) => void;
  onSort?: (column: SortColumn) => void;
  sortColumn?: string | null;
  sortDirection?: "asc" | "desc" | null;
  viewMode?: "list" | "grid";
};

function formatElapsedShort(input?: string | null): string {
  if (!input) return "—";
  const out: string[] = [];
  const re = /(\d+)\s*(days?|d|hours?|h|minutes?|m|seconds?|s)\b/gi;
  const unitMap: Record<string, "d" | "h" | "m" | "s"> = {
    day: "d", days: "d", d: "d",
    hour: "h", hours: "h", h: "h",
    minute: "m", minutes: "m", m: "m",
    second: "s", seconds: "s", s: "s",
  };

  let m: RegExpExecArray | null;
  while ((m = re.exec(input)) !== null) {
    const num = m[1];
    const unit = unitMap[m[2].toLowerCase()];
    if (!unit || unit === "s") { continue; }
    out.push(`${num}${unit}`);
  }
  return out.length ? out.join(" ") : "—";
}



/** Submitter initials avatar */
function Initials({ name }: { name: string }) {
  const parts = (name || "?").trim().split(/\s+/);
  const initials =
    parts.length >= 2
      ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
      : (name || "?").slice(0, 2).toUpperCase();
  return (
    <span
      className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground"
      aria-hidden="true"
    >
      {initials}
    </span>
  );
}

/** Sortable column header button */
function SortButton({
    label,
    column,
    currentSort,
    currentDirection,
    onSort,
}: {
    label: string;
    column: SortColumn;
    currentSort?: string | null;
    currentDirection?: "asc" | "desc" | null;
    onSort?: (col: SortColumn) => void;
}) {
    const isActive = currentSort === column;
    const Icon = isActive
        ? currentDirection === "asc"
            ? ChevronUp
            : ChevronDown
        : ChevronsUpDown;
    return (
        <button
            type="button"
            onClick={() => onSort?.(column)}
            className="inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground hover:text-foreground motion-safe:transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded touch-manipulation"
        >
            {label}
            <Icon
                className={`h-3 w-3 ${isActive ? "text-foreground" : "text-muted-foreground/30"}`}
                aria-hidden="true"
            />
        </button>
    );
}



export default function AdminRequestsCards({
    requests,
    pagination,
    onPageChange,
    onSort,
    sortColumn,
    sortDirection,
    viewMode: viewModeProp,
}: Props) {
    const rows: Row[] = useMemo(() => (Array.isArray(requests) ? requests : []), [requests]);
    const serverPagination = Boolean(pagination);

    const [page, setPage] = useState(pagination?.current_page ?? 1);

    useEffect(() => {
        if (serverPagination && pagination) {
            setPage(pagination.current_page);
        }
    }, [serverPagination, pagination]);

    const total = serverPagination ? (pagination?.total ?? 0) : rows.length;
    const lastPage = serverPagination ? Math.max(1, pagination?.last_page ?? 1) : 1;

    useEffect(() => {
        if (page > lastPage) {
            setPage(lastPage);
        }
    }, [lastPage, page]);

    const current = useMemo(() => rows, [rows]);

    const viewMode = viewModeProp ?? "list";

    const EmptyState = () => (
        <div className="py-20 text-center">
            <p className="text-sm font-semibold text-foreground">No submissions found</p>
            <p className="mt-1 text-xs text-muted-foreground">
                Try adjusting your search or filter criteria.
            </p>
        </div>
    );

    return (
        <div className="space-y-4">
            {/* ── LIST VIEW ─────────────────────────────────────────────────────── */}
            {viewMode === "list" && (
                <div className="overflow-hidden rounded-lg border border-border/60 bg-card">
                    {/* Sortable column headers */}
                    <div className="hidden sm:grid grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.8fr)_44px] gap-4 border-b border-border/60 bg-muted/40 px-5 py-2.5">
                        <SortButton label="Form" column="form" currentSort={sortColumn} currentDirection={sortDirection} onSort={onSort} />
                        <SortButton label="Submitter" column="submitter" currentSort={sortColumn} currentDirection={sortDirection} onSort={onSort} />
                        <SortButton label="Status" column="status" currentSort={sortColumn} currentDirection={sortDirection} onSort={onSort} />
                        <SortButton label="Submitted" column="submitted" currentSort={sortColumn} currentDirection={sortDirection} onSort={onSort} />
                        <span className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Current Approver
                        </span>
                        <span className="sr-only">Actions</span>
                    </div>

                    {current.length === 0 ? (
                        <EmptyState />
                    ) : (
                        <div className="divide-y divide-border/50">
                            {current.map((r) => {
                                const progressPct =
                                    r.workflow_preview.count > 0
                                        ? (r.workflow_preview.completed / r.workflow_preview.count) * 100
                                        : 0;
                                return (
                                    <div
                                        key={r.id}
                                        className="group grid grid-cols-1 gap-y-2 py-3.5 pr-5 pl-4 motion-safe:transition-colors hover:bg-accent/30 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.8fr)_44px] sm:items-center sm:gap-4 sm:gap-y-0"
                                    >
                                        {/* Form name */}
                                        <div className="min-w-0">
                                            <TruncateTooltip
                                                text={r.form_name}
                                                className="line-clamp-1 text-sm font-semibold text-foreground [text-wrap:balance]"
                                            />
                                            <span className="mt-0.5 text-xs text-muted-foreground sm:hidden">
                                                {r.submitter || "Unknown"}&nbsp;·&nbsp;
                                                {formatDate(r.submitted_at, { month: "short", day: "numeric", year: "numeric" })}
                                            </span>
                                        </div>

                                        {/* Submitter */}
                                        <div className="hidden sm:flex items-center gap-2 min-w-0">
                                            <Initials name={r.submitter || "?"} />
                                            <span className="min-w-0 truncate text-sm text-muted-foreground">
                                                {r.submitter || "Unknown"}
                                            </span>
                                        </div>

                                        {/* Status */}
                                        <div>
                                            <SubmissionStatusBadge status={r.status} />
                                        </div>

                                        {/* Submitted date + elapsed */}
                                        <div className="hidden sm:block">
                                            <span className="font-mono text-xs tabular-nums text-muted-foreground">
                                                {formatDate(r.submitted_at, { month: "short", day: "numeric", year: "numeric" })}
                                            </span>
                                            {r.elapsed_human && (
                                                <span className="mt-0.5 block font-mono text-[11px] tabular-nums text-muted-foreground/70">
                                                    {formatElapsedShort(r.elapsed_human)} elapsed
                                                </span>
                                            )}
                                        </div>

                                        {/* Current Approver + progress bar */}
                                        <div className="min-w-0">
                                            <div className="flex items-center justify-between gap-2 mb-1">
                                                <span className="min-w-0 truncate text-xs text-foreground/80">
                                                    {r.workflow_preview.current || "—"}
                                                </span>
                                                <span className="shrink-0 font-mono text-[11px] tabular-nums text-muted-foreground">
                                                    {r.workflow_preview.completed}/{r.workflow_preview.count}
                                                </span>
                                            </div>
                                            <div
                                                role="progressbar"
                                                aria-valuenow={Math.round(progressPct)}
                                                aria-valuemin={0}
                                                aria-valuemax={100}
                                                aria-label="Workflow progress"
                                                className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                            >
                                                <div
                                                    className="h-full rounded-full bg-primary motion-safe:transition-[width] duration-500"
                                                    style={{ width: `${progressPct}%` }}
                                                />
                                            </div>
                                        </div>

                                        {/* Action */}
                                        <div className="flex items-center justify-end sm:justify-center">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Link
                                                        href={route("admin-submissions.show", {
                                                            formId: r.form_id,
                                                            submissionId: r.submission_id,
                                                        })}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground motion-safe:transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring touch-manipulation"
                                                        aria-label={`View submission from ${r.submitter}`}
                                                    >
                                                        <Eye className="h-4 w-4" aria-hidden="true" />
                                                    </Link>
                                                </TooltipTrigger>
                                                <TooltipContent>View</TooltipContent>
                                            </Tooltip>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {/* ── GRID VIEW ─────────────────────────────────────────────────────── */}
            {viewMode === "grid" && (
                <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                    {current.length === 0 ? (
                        <div className="col-span-full">
                            <EmptyState />
                        </div>
                    ) : (
                        current.map((r) => {
                            const orderedSteps = [...r.workflow_preview.steps].sort(
                                (a, b) =>
                                    (a.step_group ?? 0) - (b.step_group ?? 0) ||
                                    (a.step_order ?? 0) - (b.step_order ?? 0),
                            );
                            const progressPct =
                                r.workflow_preview.count > 0
                                    ? (r.workflow_preview.completed / r.workflow_preview.count) * 100
                                    : 0;

                            return (
                                <div
                                    key={r.id}
                                    className="group flex flex-col rounded-lg border border-border/60 bg-card motion-safe:transition-colors hover:border-border hover:bg-accent/10"
                                >
                                    {/* Card header: form name + right column (eye + badge) */}
                                    <div className="flex items-start gap-3 px-4 pt-4 pb-3 border-b border-border/50">
                                        <div className="min-w-0 flex-1">
                                            <TruncateTooltip
                                                text={r.form_name}
                                                className="line-clamp-2 text-sm font-semibold leading-snug text-foreground"
                                            />
                                            <div className="mt-2.5 flex items-center gap-2">
                                                <Initials name={r.submitter || "?"} />
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-foreground/80 leading-none">
                                                        {r.submitter || "Unknown"}
                                                    </p>
                                                    <p className="mt-0.5 font-mono text-[11px] tabular-nums text-muted-foreground">
                                                        {formatDate(r.submitted_at, {
                                                            month: "short",
                                                            day: "numeric",
                                                            year: "numeric",
                                                        })}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Right column: eye icon on top, status badge below */}
                                        <div className="flex shrink-0 flex-col items-center gap-2">
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Link
                                                        href={route("admin-submissions.show", {
                                                            formId: r.form_id,
                                                            submissionId: r.submission_id,
                                                        })}
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground motion-safe:transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring touch-manipulation"
                                                        aria-label={`View submission from ${r.submitter}`}
                                                    >
                                                        <Eye className="h-[17px] w-[17px]" aria-hidden="true" />
                                                    </Link>
                                                </TooltipTrigger>
                                                <TooltipContent>View</TooltipContent>
                                            </Tooltip>
                                            <SubmissionStatusBadge status={r.status} />
                                        </div>
                                    </div>

                                    {/* Approvers section */}
                                    <div className="flex flex-1 flex-col px-4 pt-3 pb-4">
                                        <div className="mb-2 flex items-center justify-between">
                                            <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                Approvers
                                            </span>
                                            <span className="font-mono text-[11px] tabular-nums text-muted-foreground">
                                                {r.workflow_preview.completed}/{r.workflow_preview.count}
                                            </span>
                                        </div>

                                        {/* Scrollable approver sequence — no expand button needed */}
                                        <div
                                            className="max-h-[120px] overflow-y-auto space-y-1.5 pr-0.5"
                                            aria-label="Approver sequence"
                                        >
                                            {orderedSteps.length === 0 ? (
                                                <p className="text-xs text-gray-400 dark:text-gray-500">No approvers assigned.</p>
                                            ) : (
                                                orderedSteps.map((step) => {
                                                    const s = step.status.toLowerCase();
                                                    const isDone = s === "approved";
                                                    const isRejected = s === "rejected";
                                                    return (
                                                        <div
                                                            key={step.id}
                                                            className="flex items-center justify-between gap-2 min-w-0 text-xs"
                                                        >
                                                            <div className="flex items-center gap-1.5 min-w-0">
                                                                {isDone ? (
                                                                    <CheckCircle2
                                                                        className="h-3.5 w-3.5 shrink-0 text-green-600 dark:text-green-500"
                                                                        aria-hidden="true"
                                                                    />
                                                                ) : isRejected ? (
                                                                    <XCircle
                                                                        className="h-3.5 w-3.5 shrink-0 text-red-500 dark:text-red-400"
                                                                        aria-hidden="true"
                                                                    />
                                                                ) : (
                                                                    <Clock
                                                                        className="h-3.5 w-3.5 shrink-0 text-amber-500 dark:text-amber-400"
                                                                        aria-hidden="true"
                                                                    />
                                                                )}
                                                                <span className="truncate text-foreground/80">
                                                                    {step.name}
                                                                </span>
                                                            </div>
                                                            <span className="max-w-[100px] shrink-0 truncate text-right text-muted-foreground">
                                                                {step.actor ?? "—"}
                                                            </span>
                                                        </div>
                                                    );
                                                })
                                            )}
                                        </div>

                                        {/* Progress bar */}
                                        <div
                                            role="progressbar"
                                            aria-valuenow={Math.round(progressPct)}
                                            aria-valuemin={0}
                                            aria-valuemax={100}
                                            aria-label="Workflow progress"
                                            className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                        >
                                            <div
                                                className="h-full rounded-full bg-primary motion-safe:transition-[width] duration-500"
                                                style={{ width: `${progressPct}%` }}
                                            />
                                        </div>

                                        {/* Footer: elapsed */}
                                        <div className="mt-3 flex items-center justify-between border-t border-border/50 pt-3">
                                            <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                                Elapsed
                                            </span>
                                            <span className="font-mono text-xs tabular-nums text-muted-foreground">
                                                {r.elapsed_human ? formatElapsedShort(r.elapsed_human) : "—"}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
            )}

            {/* Pagination */}
            <Pagination
                currentPage={page}
                lastPage={lastPage}
                currentCount={current.length}
                total={total}
                itemLabel="submissions"
                alwaysShow
                onPageChange={(nextPage) => {
                    if (serverPagination) {
                        onPageChange?.(nextPage);
                        return;
                    }
                    setPage(nextPage);
                }}
            />
        </div>
    );
}
