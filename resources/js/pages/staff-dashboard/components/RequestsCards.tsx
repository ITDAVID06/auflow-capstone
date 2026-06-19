import React, { useEffect, useMemo, useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import Pagination from "@/components/shared/Pagination";
import EmptyState from "@/components/EmptyState";
import {
  Eye,
  CheckCircle2,
  XCircle,
  Clock,
  Inbox,
  User,
  Calendar,
  Loader2,
} from "lucide-react";
import { useStaffActions } from "../hooks/useStaffActions";
import RejectModal from "./RejectModal";
import ApproveModal from "./ApproveModal";
import { type SharedData } from "@/types";
import { useInertiaLoading } from "@/hooks/useInertiaLoading";
import { formatDate } from "@/utils/dateTime";

type Row = {
  progress_id: number;
  submission_id: number;
  form_code: string;
  form_name: string;
  status: "Pending" | "Approved" | "Rejected" | string;
  submitted_at: string;
  submitter: string;
  version?: number;
  is_latest?: boolean;
};

type RequestsEnvelope = { data: Row[]; meta?: Record<string, unknown> };
type RequestsProp = Row[] | RequestsEnvelope;
type PendingContext = {
  assigned_count: number;
  pending_pool_count: number;
  has_unassigned_pending: boolean;
};

function StatusBadge({ value }: { value: string }) {
  const v = value.toLowerCase();

  if (v === "approved")
    return (
      <span className="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-full">
        <CheckCircle2 className="h-3 w-3" />
        Approved
      </span>
    );

  if (v === "pending")
    return (
      <span className="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded-full">
        <Clock className="h-3 w-3" />
        Pending
      </span>
    );

  if (v === "rejected")
    return (
      <span className="inline-flex items-center gap-1.5 text-xs font-medium text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 px-2 py-0.5 rounded-full">
        <XCircle className="h-3 w-3" />
        Rejected
      </span>
    );

  return (
    <span className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground bg-muted px-2 py-0.5 rounded-full">
      {value}
    </span>
  );
}

export default function RequestsCards({ requests, pendingContext, filtersActive }: { requests: RequestsProp; pendingContext?: PendingContext; filtersActive?: boolean }) {
  const { auth } = usePage<SharedData>().props;
  const permissions = auth?.user?.permissions ?? [];
  const inertiaLoading = useInertiaLoading();

  const canViewSubmission = permissions.some((p) =>
    ["submissions.view", "requests.approve", "submissions.override"].includes(p)
  );
  const canReviewSubmission = permissions.some((p) =>
    ["requests.approve", "submissions.override"].includes(p)
  );

  const rows: Row[] = useMemo(() => {
    let data: Row[] = [];
    if (Array.isArray(requests)) data = requests;
    else if (requests && typeof requests === "object" && "data" in requests && Array.isArray(requests.data)) {
      data = requests.data;
    }
    return data.filter((r) => r.is_latest ?? true);
  }, [requests]);

  const [displayRows, setDisplayRows] = useState<Row[]>(rows);
  const [page, setPage] = useState(1);
  const perPage = 8;
  const total = displayRows.length;
  const lastPage = Math.max(1, Math.ceil(total / perPage));

  useEffect(() => {
    setDisplayRows(rows);
    setPage(1);
  }, [rows]);

  useEffect(() => { if (page > lastPage) setPage(lastPage); }, [lastPage, page]);

  const start = (page - 1) * perPage;
  const end = Math.min(total, start + perPage);
  const currentRows = useMemo(() => displayRows.slice(start, end), [displayRows, start, end]);

  const { actOnSubmission } = useStaffActions();
  const [busy, setBusy] = useState(false);
  const [finalApprovalNotice, setFinalApprovalNotice] = useState<string | null>(null);
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [rejectId, setRejectId] = useState<number | null>(null);
  const [showApproveModal, setShowApproveModal] = useState(false);
  const [approveId, setApproveId] = useState<number | null>(null);
  const isMutating = busy || inertiaLoading;

  const updateRowStatus = (progressId: number, status: Row["status"]) => {
    setDisplayRows((previous) =>
      previous.map((row) =>
        row.progress_id === progressId
          ? {
              ...row,
              status,
            }
          : row,
      ),
    );
  };

  if (displayRows.length === 0) {
    if (filtersActive) {
      return (
        <EmptyState
          icon={<Inbox className="h-6 w-6" />}
          title="No results match your search"
          message="Try adjusting your search term or status filter."
        />
      );
    }

    if (pendingContext?.has_unassigned_pending) {
      return (
        <EmptyState
          icon={<Inbox className="h-6 w-6" />}
          title="No requests are currently assigned to you."
          message="There are unassigned requests in the pool that you can claim."
          action={
            <Button asChild size="sm" variant="outline">
              <Link href={route("staff-dashboard.requests")}>View unassigned requests</Link>
            </Button>
          }
        />
      );
    }

    return (
      <EmptyState
        icon={<Inbox className="h-6 w-6" />}
        title="You're all caught up — no pending approvals."
      />
    );
  }

  return (
    <>
      {finalApprovalNotice ? (
        <Alert className="mb-3 border-emerald-500/30 bg-emerald-500/10">
          <CheckCircle2 className="h-4 w-4 text-emerald-700 dark:text-emerald-300" />
          <AlertTitle>Request fully approved</AlertTitle>
          <AlertDescription>{finalApprovalNotice}</AlertDescription>
        </Alert>
      ) : null}

      {isMutating ? (
        <div className="mb-2 flex items-center gap-2 text-xs text-muted-foreground">
          <Loader2 className="h-3.5 w-3.5 animate-spin" />
          Updating request status...
        </div>
      ) : null}

      <div className="divide-y divide-border/50">
        {currentRows.map((r, idx) => (
          <div
            key={r.progress_id}
            className="py-3.5 hover:bg-accent/40 transition-colors duration-150 rounded-lg -mx-1 px-2"
            data-tour={idx === 0 ? "staff-requests" : undefined}
          >
            <div className="space-y-2">
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1 space-y-1.5 pr-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm font-semibold text-foreground leading-snug">
                      {r.form_name}
                    </span>
                    <span aria-live="polite">
                      <StatusBadge value={r.status} />
                    </span>
                    {typeof r.version === "number" && (
                      <span className="text-[11px] font-medium text-muted-foreground bg-muted border border-border/60 px-1.5 py-0.5 rounded">
                        v{r.version}
                      </span>
                    )}
                  </div>
                </div>

                {/* Right: action buttons */}
                <div className="flex items-center gap-1.5 flex-shrink-0">
                  {canViewSubmission && (
                    <Button
                      asChild
                      type="button"
                      variant="outline"
                      size="sm"
                      className="h-8 px-2.5 sm:px-3 text-xs gap-1.5 border-border/80"
                      disabled={isMutating}
                      data-tour={idx === 0 ? "staff-view-button" : undefined}
                    >
                      <Link
                        href={route("staff-dashboard.submission.view", { id: r.progress_id })}
                        aria-label={`View ${r.form_name}`}
                      >
                        <Eye className="h-3.5 w-3.5" />
                        <span className="hidden sm:inline">View</span>
                      </Link>
                    </Button>
                  )}

                  {canReviewSubmission && r.status === "Pending" && (
                    <>
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 px-2.5 sm:px-3 text-xs gap-1.5 text-destructive hover:text-destructive"
                        disabled={isMutating}
                        aria-disabled={isMutating}
                        aria-busy={isMutating}
                        aria-label={`Reject ${r.form_name}`}
                        onClick={() => {
                          setRejectId(r.progress_id);
                          setShowRejectModal(true);
                        }}
                        data-tour={idx === 0 ? "staff-approve-reject" : undefined}
                      >
                        <XCircle className="h-3.5 w-3.5" />
                        <span className="hidden sm:inline">Reject</span>
                      </Button>

                      <Button
                        type="button"
                        size="sm"
                        className="h-8 px-2.5 sm:px-3 text-xs gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                        disabled={isMutating}
                        aria-disabled={isMutating}
                        aria-busy={isMutating}
                        aria-label={`Approve ${r.form_name}`}
                        onClick={() => {
                          setApproveId(r.progress_id);
                          setShowApproveModal(true);
                        }}
                      >
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        <span className="hidden sm:inline">Approve</span>
                      </Button>
                    </>
                  )}
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                {r.submitted_at && (
                  <span className="flex items-center gap-1">
                    <Calendar className="h-3 w-3 flex-shrink-0" />
                    {formatDate(r.submitted_at, { month: "short", day: "numeric", year: "numeric" })}
                  </span>
                )}
                {r.submitter && (
                  <span className="flex items-center gap-1">
                    <User className="h-3 w-3 flex-shrink-0" />
                    <span className="truncate max-w-[160px]">{r.submitter}</span>
                  </span>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-4 pt-3 border-t border-border/50">
        <Pagination
          currentPage={page}
          lastPage={lastPage}
          currentCount={currentRows.length}
          total={total}
          itemLabel="submissions"
          alwaysShow
          onPageChange={setPage}
        />
      </div>

      {rejectId !== null && (
        <RejectModal
          open={showRejectModal}
          onOpenChange={setShowRejectModal}
          progressId={rejectId}
          onReject={async (progressId, comment, files) => {
            setBusy(true);
            try {
              await actOnSubmission(progressId, "reject", comment, files);
              updateRowStatus(progressId, "Rejected");
            }
            finally { setBusy(false); }
          }}
        />
      )}

      {approveId !== null && (
        <ApproveModal
          open={showApproveModal}
          onOpenChange={setShowApproveModal}
          progressId={approveId}
          onApprove={async (progressId, comment, files) => {
            setBusy(true);
            try {
              const result = await actOnSubmission(progressId, "approve", comment, files);
              updateRowStatus(progressId, "Approved");
              if (result.finalApproved) {
                setFinalApprovalNotice("This request has been fully approved and the submitter has been notified.");
              }
            }
            finally { setBusy(false); }
          }}
        />
      )}
    </>
  );
}
