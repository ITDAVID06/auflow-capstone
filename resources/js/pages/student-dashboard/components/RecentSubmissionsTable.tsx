import React, { useEffect, useState } from "react";
import axios from "axios";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import EmptyState from "@/components/EmptyState";
import Pagination from "@/components/shared/Pagination";
import { Link } from "@inertiajs/react";
import { 
  ChevronLeft,
  ChevronRight,
  Eye, 
  Edit3, 
  Calendar,
  FileText,
} from "lucide-react";

type NormalizedStatus =
  | "pending"
  | "approved"
  | "rejected"
  | "auto-rejected"
  | "revision"
  | "other";

interface Submission {
  id: number;
  form_id: number;
  form_name: string;
  form_code?: string; // <-- searchable
  status: string;
  priority?: string | null;
  requester?: string | null;
  progress?: number;
  submitted_at?: string | null;
  updated_at?: string | null;
  next_step_name?: string | null;
}

interface Props {
  search: string;
  status: "all" | "pending" | "approved" | "rejected" | "revision";
  routeNamespace?: "student-dashboard" | "staff-dashboard";
  viewRouteName?: string;
  editRouteName?: string;
  onClearFilters?: () => void;
}

function SubmissionRowSkeleton() {
  return (
    <div className="py-4 space-y-2.5 animate-pulse">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2.5">
          <div className="h-4 w-44 rounded bg-gray-200 dark:bg-gray-800" />
          <div className="h-3.5 w-14 rounded bg-gray-200 dark:bg-gray-800" />
        </div>
        <div className="hidden md:flex items-center gap-2">
          <div className="h-7 w-14 rounded bg-gray-200 dark:bg-gray-800" />
        </div>
      </div>
      <div className="h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-800" />
      <div className="flex gap-3 pt-1 border-t border-gray-100 dark:border-gray-700/60">
        <div className="h-2.5 w-24 rounded bg-gray-200 dark:bg-gray-800" />
        <div className="h-2.5 w-16 rounded bg-gray-200 dark:bg-gray-800" />
      </div>
    </div>
  );
}

function normalizeStatus(raw?: string): NormalizedStatus {
  const v = (raw || "").toLowerCase().trim();
  if (!v) return "other";
  if (v.startsWith("pend")) return "pending";
  if (v.startsWith("appr")) return "approved";
  if (v.includes("auto") && v.includes("reject")) return "auto-rejected";
  if (v.startsWith("rejec")) return "rejected";
  if (v.includes("revision")) return "revision"; // matches “Needs Revision”, etc.
  return "other";
}

const StatusBadge: React.FC<{ value: string }> = ({ value }) => {
  const norm = normalizeStatus(value);
  
  const statusConfig: Record<NormalizedStatus, { dot: string; text: string; label: string }> = {
    approved: { dot: "bg-emerald-500", text: "text-emerald-600 dark:text-emerald-400", label: "Approved" },
    pending: { dot: "bg-amber-500", text: "text-amber-600 dark:text-amber-400", label: "Pending" },
    rejected: { dot: "bg-red-500", text: "text-red-600 dark:text-red-400", label: "Rejected" },
    "auto-rejected": { dot: "bg-red-500", text: "text-red-600 dark:text-red-400", label: "Rejected" },
    revision: { dot: "bg-orange-500", text: "text-orange-600 dark:text-orange-400", label: "Needs Revision" },
    other: { dot: "bg-gray-400 dark:bg-gray-500", text: "text-gray-500 dark:text-gray-400", label: value },
  };

  const config = statusConfig[norm];
  return (
    <span className={`inline-flex items-center gap-1.5 text-xs font-medium ${config.text}`}>
      <span className={`h-1.5 w-1.5 rounded-full flex-shrink-0 ${config.dot}`} aria-hidden="true" />
      {config.label}
    </span>
  );
};

const PriorityBadge = ({ value }: { value?: string | null }) => {
  if (!value) return null;
  const v = value.toLowerCase();
  
  if (v === "urgent") {
    return <span className="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20">{value}</span>;
  }
  if (v === "high") {
    return <span className="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20">{value}</span>;
  }
  if (v === "medium") {
    return <span className="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-500/10 dark:text-blue-400 dark:ring-blue-500/20">{value}</span>;
  }
  // low or default
  return <span className="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">{value}</span>;
};

export default function RecentSubmissionsTable({
  search,
  status,
  routeNamespace = "student-dashboard",
  viewRouteName = "student-dashboard.submission.view",
  editRouteName = "student-dashboard.submission.edit",
  onClearFilters,
}: Props) {
  const [submissions, setSubmissions] = useState<Submission[]>([]);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0, per_page: 10 });
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [fetchError, setFetchError] = useState<string | null>(null);
  const [retryKey, setRetryKey] = useState(0);

  useEffect(() => {
    setPage(1);
  }, [search, status]);

  useEffect(() => {
    const params = new URLSearchParams();
    params.set("status", status);
    params.set("page", String(page));
    params.set("per_page", "10");
    if (search.trim()) {
      params.set("search", search.trim());
    }

    setLoading(true);
    setFetchError(null);
    axios
      .get(`/${routeNamespace}/submissions?${params.toString()}`)
      .then((res) => {
        const payload = res.data ?? {};
        setSubmissions(Array.isArray(payload.data) ? payload.data : []);
        setMeta({
          current_page: payload.meta?.current_page ?? 1,
          last_page: payload.meta?.last_page ?? 1,
          total: payload.meta?.total ?? 0,
          per_page: payload.meta?.per_page ?? 10,
        });
      })
      .catch(() => {
        setFetchError("Failed to load submissions. Please check your connection and try again.");
        setSubmissions([]);
      })
      .finally(() => {
        setLoading(false);
      });
  }, [status, search, page, routeNamespace, retryKey]);

  if (loading) {
    return (
      <div className="divide-y divide-gray-100 dark:divide-gray-700/60" aria-busy="true" aria-label="Loading submissions">
        <SubmissionRowSkeleton />
        <SubmissionRowSkeleton />
        <SubmissionRowSkeleton />
      </div>
    );
  }

  if (fetchError) {
    return (
      <div className="py-8 flex flex-col items-center gap-3 text-center">
        <p className="text-sm text-destructive">{fetchError}</p>
        <Button variant="outline" size="sm" onClick={() => setRetryKey((k) => k + 1)}>
          Retry
        </Button>
      </div>
    );
  }

  if (submissions.length === 0) {
    const hasFilters = Boolean(search.trim()) || status !== "all";

    return (
      <EmptyState
        icon={<FileText className="h-6 w-6" />}
        title={
          hasFilters
            ? "No results match your filters."
            : "You haven't submitted any requests yet."
        }
        message={
          hasFilters
            ? ""
            : "Start by choosing a form and submitting your first request."
        }
        action={
          hasFilters ? (
            <Button
              type="button"
              variant="link"
              onClick={() => {
                onClearFilters?.();
              }}
            >
              Clear filters
            </Button>
          ) : (
            <Button
              asChild
              variant="outline"
              size="sm"
            >
              <Link href={route(`${routeNamespace}.forms.index`)}>
                New Request
              </Link>
            </Button>
          )
        }
        className="py-14"
      />
    );
  }

  return (
    <div className="divide-y divide-gray-100 dark:divide-gray-700/60">
      {submissions.map((s, idx) => {
        const norm = normalizeStatus(s.status);

        return (
          <div
            key={s.id}
            className="group py-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 motion-safe:transition-colors"
            data-tour={idx === 0 ? "student-submissions" : undefined}
          >
            <div className="space-y-2.5">
              {/* Title row with badges */}
              <div className="flex items-start justify-between gap-2">
                {/* Title + badges */}
                <div className="flex items-center gap-2 flex-wrap flex-1 min-w-0 pr-2">
                  <h3 className="text-sm sm:text-base font-semibold text-gray-900 dark:text-gray-100 tracking-tight">
                    {s.form_name}
                  </h3>
                  <StatusBadge value={s.status} />
                  <PriorityBadge value={s.priority} />
                </div>
                
                {/* Action buttons */}
                <div className="flex items-center gap-2 flex-shrink-0">
                  <Button
                    asChild
                    variant="outline"
                    size="sm"
                    className="h-8 px-3.5 text-xs font-medium touch-manipulation"
                    data-tour="view-button"
                  >
                    <Link
                      href={route(viewRouteName, { formId: s.form_id, submissionId: s.id })}
                      aria-label={`View submission for ${s.form_name}`}
                    >
                      <Eye className="mr-1.5 h-3.5 w-3.5" aria-hidden="true" />
                      <span className="hidden sm:inline">View</span>
                    </Link>
                  </Button>

                  {(norm === "rejected" || norm === "auto-rejected" || norm === "revision") && (
                    <Button
                      asChild
                      size="sm"
                      className="h-8 px-3.5 text-xs font-medium touch-manipulation"
                      data-tour="edit-button"
                    >
                      <Link
                        href={route(editRouteName, { formId: s.form_id, submissionId: s.id })}
                        aria-label={`Edit submission for ${s.form_name}`}
                      >
                        <Edit3 className="mr-1.5 h-3.5 w-3.5" aria-hidden="true" />
                        <span className="hidden sm:inline">Edit</span>
                      </Link>
                    </Button>
                  )}
                </div>
              </div>

              {/* Progress bar */}
              {typeof s.progress === "number" && (
                <div className="mt-1">
                  <div className="flex items-center justify-between text-xs mb-1.5">
                    <span className="text-gray-500 dark:text-gray-400 font-medium">Progress</span>
                    <span className="text-gray-900 dark:text-gray-100 font-semibold tabular-nums">{s.progress}%</span>
                  </div>
                  <div className="h-1.5 w-full rounded-full bg-blue-100 dark:bg-blue-900/30 overflow-hidden">
                    <div 
                      className="h-full rounded-full bg-blue-600 dark:bg-blue-500 motion-safe:transition-[width] motion-safe:duration-500 motion-safe:ease-out"
                      style={{ width: `${s.progress}%` }}
                    />
                  </div>
                  {s.next_step_name && (
                    <p className="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mt-1.5 line-clamp-1">
                      Next: <span className="font-medium text-gray-700 dark:text-gray-300">{s.next_step_name}</span>
                    </p>
                  )}
                </div>
              )}

              {/* Metadata row */}
              <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                {s.submitted_at && (
                  <span className="flex items-center gap-1.5">
                    <Calendar className="h-3 w-3" />
                    {new Intl.DateTimeFormat(undefined, { month: "short", day: "numeric", year: "numeric" }).format(new Date(s.submitted_at))}
                  </span>
                )}
                {s.form_code && (
                  <span className="flex items-center gap-1.5">
                    <FileText className="h-3 w-3" />
                    <code className="font-mono text-[11px] bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">{s.form_code}</code>
                  </span>
                )}
              </div>
            </div>
          </div>
        );
      })}

      {meta.last_page > 1 && (
        <div className="border-t border-gray-100 dark:border-gray-700/60 pb-1">
          <Pagination
            currentPage={meta.current_page}
            lastPage={meta.last_page}
            currentCount={submissions.length}
            total={meta.total}
            itemLabel="submissions"
            onPageChange={(p) => setPage(p)}
          />
        </div>
      )}
    </div>
  );
}
