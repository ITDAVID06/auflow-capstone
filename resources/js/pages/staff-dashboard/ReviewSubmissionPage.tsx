import React, { useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import { toast } from "sonner";
import axios from "axios";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import FileViewerDialog from "@/components/FileViewerDialog";
import ApproveModal from "./components/ApproveModal";
import RejectModal from "./components/RejectModal";
import { SubmissionFieldsDisplay } from "./components/SubmissionFieldsDisplay";
import { WorkflowTimeline } from "./components/WorkflowTimeline";
import { Chip } from "./components/ui/Chip";
import {
  ChevronLeft,
  FileText,
  Download,
  ExternalLink,
  CheckCircle2,
  XCircle,
  GitBranch,
  MessageSquare,
  History,
  QrCode,
  CalendarDays,
  Eye,
  AlertCircle,
  Loader2,
} from "lucide-react";
import type { SubmissionData, SnapshotInfo, WorkflowStep, WorkflowAttachment } from "./types/submissionTypes";
import type { PageProps as InertiaPageProps } from "@inertiajs/core";
import type { AxiosError } from "axios";
import { type SharedData } from "@/types";
import { formatDate, formatDateTime } from "@/utils/dateTime";

interface PageProps extends InertiaPageProps {
  submission: SubmissionData;
}

type ChipVariant = "approved" | "rejected" | "auto-rejected" | "pending";

interface SubmissionRevision {
  id: number;
  latest_status?: string | null;
  status?: string | null;
  progress_id?: number;
  created_at?: string;
  is_latest?: boolean;
}

interface ApiErrorData {
  error?: string;
  message?: string;
}

const getApiErrorMessage = (error: unknown, fallback: string): string => {
  const axiosError = error as AxiosError<ApiErrorData>;

  return axiosError.response?.data?.error ?? axiosError.response?.data?.message ?? fallback;
};

export default function ReviewSubmissionPage() {
  const { submission, auth } = usePage<PageProps & SharedData>().props;
  const [activeTab, setActiveTab] = useState<string>("details");
  const [showApproveModal, setShowApproveModal] = useState(false);
  const [showRejectModal, setShowRejectModal] = useState(false);
  const [pdfViewer, setPdfViewer] = useState<{ url: string; title: string; mime?: string } | null>(null);
  const [snapshot, setSnapshot] = useState<SnapshotInfo>(submission.snapshot || { exists: false });
  const [latestStatusOverride, setLatestStatusOverride] = useState<string | null>(null);
  const [canReviewCurrent, setCanReviewCurrent] = useState<boolean>(submission.can_review);
  const [finalApprovalNotice, setFinalApprovalNotice] = useState<string | null>(null);
  const [facilities, setFacilities] = useState<Array<{ id: number; name: string }>>([]);
  const [snapshotPolling, setSnapshotPolling] = useState(false);
  const rawHistory = (submission as SubmissionData & { history?: unknown }).history;
  const history: SubmissionRevision[] = Array.isArray(rawHistory)
    ? (rawHistory as SubmissionRevision[])
    : [];

  const permissions = auth?.user?.permissions ?? [];
  const canReviewSubmission = permissions.some((permission) =>
    ["requests.approve", "submissions.override"].includes(permission)
  );

  // Fetch snapshot on mount
  React.useEffect(() => {
    const fetchSnapshot = async () => {
      try {
        const { data } = await axios.get<SnapshotInfo>(
          `/staff-dashboard/progress/${submission.progress_id}/snapshot`,
          { headers: { "X-Requested-With": "XMLHttpRequest" } }
        );
        setSnapshot(data?.exists ? data : { exists: false });
      } catch {
        setSnapshot({ exists: false });
      }
    };
    fetchSnapshot();
  }, [submission.progress_id]);

  // Fetch facilities on mount
  React.useEffect(() => {
    const fetchFacilities = async () => {
      try {
        const { data } = await axios.get<Array<{ id: number; name: string }>>(
          route("admin.facilities.active"),
          { headers: { "X-Requested-With": "XMLHttpRequest" } }
        );
        setFacilities(Array.isArray(data) ? data : []);
      } catch {
        // facilities are optional — silently fail
      }
    };
    fetchFacilities();
  }, []);

  // Approval/rejection handlers
  const handleApprove = async (progressId: number, comment?: string, files?: File[]) => {
    try {
      const fd = new FormData();
      fd.append("_method", "PUT");
      fd.append("comment", comment ?? "");
      (files ?? []).forEach((f, i) => fd.append(`attachments[${i}]`, f));

      const approveResponse = await axios.post(route("staff-dashboard.progress.approve", { id: progressId }), fd, {
        headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        withCredentials: true,
      });

      // Poll for snapshot (it's generated asynchronously)
      setSnapshotPolling(true);
      let snap: SnapshotInfo | null = null;
      let attempts = 0;
      const maxAttempts = 10; // 5 seconds max

      while (attempts < maxAttempts) {
        await new Promise(resolve => setTimeout(resolve, 500)); // Wait 500ms
        const { data } = await axios.get<SnapshotInfo>(
          `/staff-dashboard/progress/${progressId}/snapshot`,
          { headers: { "X-Requested-With": "XMLHttpRequest" } }
        );
        if (data?.exists) {
          snap = data;
          break;
        }
        attempts++;
      }
      setSnapshotPolling(false);

      const approvalMessage = snap?.exists ? "Approved • Snapshot created" : "Approved";
      if (snap?.exists) {
        toast.success(approvalMessage);
        setSnapshot(snap);
      } else {
        toast.success(approvalMessage);
      }

      // Use the API response flag — snapshot.status reflects only this step's outcome,
      // not whether the entire workflow is complete.
      if (approveResponse.data?.final_approved) {
        setFinalApprovalNotice("This request has been fully approved and the submitter has been notified.");
      }

      setShowApproveModal(false);
      setCanReviewCurrent(false);
      setLatestStatusOverride("Approved");
    } catch (error: unknown) {
      setSnapshotPolling(false);
      toast.error(getApiErrorMessage(error, "Approval failed"));
    }
  };

  const handleReject = async (progressId: number, comment: string, files?: File[]) => {
    try {
      const fd = new FormData();
      fd.append("_method", "PUT");
      fd.append("comment", comment ?? "");
      (files ?? []).forEach((f, i) => fd.append(`attachments[${i}]`, f));

      await axios.post(route("staff-dashboard.progress.reject", { id: progressId }), fd, {
        headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        withCredentials: true,
      });

      // Poll for snapshot (it's generated asynchronously)
      setSnapshotPolling(true);
      let snap: SnapshotInfo | null = null;
      let attempts = 0;
      const maxAttempts = 10; // 5 seconds max

      while (attempts < maxAttempts) {
        await new Promise(resolve => setTimeout(resolve, 500)); // Wait 500ms
        const { data } = await axios.get<SnapshotInfo>(
          `/staff-dashboard/progress/${progressId}/snapshot`,
          { headers: { "X-Requested-With": "XMLHttpRequest" } }
        );
        if (data?.exists) {
          snap = data;
          break;
        }
        attempts++;
      }
      setSnapshotPolling(false);

      toast.success(snap?.exists ? "Rejected • Snapshot created" : "Rejected");
      if (snap?.exists) setSnapshot(snap);
      setShowRejectModal(false);
      setCanReviewCurrent(false);
      setLatestStatusOverride("Rejected");
    } catch (error: unknown) {
      setSnapshotPolling(false);
      toast.error(getApiErrorMessage(error, "Rejection failed"));
    }
  };

  // Determine latest status
  const derivedLatestStatus =
    submission.workflow && submission.workflow.length > 0
      ? submission.workflow[submission.workflow.length - 1].status
      : "Pending";
  const latestStatus = latestStatusOverride ?? derivedLatestStatus;

  const statusChipVariant: ChipVariant =
    latestStatus === "Approved"
      ? "approved"
      : latestStatus === "Rejected"
      ? "rejected"
      : latestStatus === "Auto-Rejected"
      ? "auto-rejected"
      : "pending";

  const tabs = [
    { id: "details", label: "Details" },
    { id: "workflow", label: "Workflow" },
    { id: "comments", label: "Comments" },
    ...(history.length >= 1 ? [{ id: "history", label: "History" }] : []),
  ];

  const mobileTabs = [...tabs, { id: "summary", label: "Summary" }];

  const layoutSubtitle = (
    <div className="flex items-center gap-2 flex-wrap">
      <Chip variant={statusChipVariant}>{latestStatus}</Chip>
      {submission.form_code && (
        <span className="inline-flex items-center rounded-full border border-border/60 bg-muted/40 px-2.5 py-0.5 text-xs font-medium text-foreground">
          {submission.form_code}
        </span>
      )}
    </div>
  );

  const handleFilePreview = (url: string, title: string, mime?: string) => {
    setPdfViewer({ url, title, mime });
  };

  // Snapshot Block
  const SnapshotBlock = (
    <div className="relative bg-card border border-border/60 rounded-xl overflow-hidden motion-safe:transition-colors">
      <div className="flex items-center gap-2 px-4 sm:px-5 py-3.5 border-b border-border/50">
        <QrCode className="h-3.5 w-3.5 text-muted-foreground/60 flex-shrink-0" />
        <span className="text-sm font-semibold text-foreground">Verification Snapshot</span>
      </div>
      {snapshotPolling && !snapshot?.exists ? (
        <div className="px-4 py-6 flex flex-col items-center gap-2 text-center">
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground/60" />
          <p className="text-xs text-muted-foreground leading-relaxed">Generating snapshot…</p>
        </div>
      ) : snapshot?.exists ? (
        <div className="p-4 space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-lg bg-muted/40 border border-border/50 px-3 py-2.5">
              <p className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">Status</p>
              <p className="text-sm font-semibold capitalize text-foreground">
                {snapshot.is_workflow_complete === false ? "In Progress" : (snapshot.status ?? "—")}
              </p>
            </div>
            <div className="rounded-lg bg-muted/40 border border-border/50 px-3 py-2.5">
              <p className="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">Code</p>
              <p className="text-sm font-mono font-bold text-foreground tracking-wide">{snapshot.short_code}</p>
            </div>
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={() => snapshot?.url && window.open(snapshot.url, "_blank")}
            className="w-full h-8 text-xs border-border/80 gap-1.5"
          >
            <ExternalLink className="h-3.5 w-3.5" />
            Open Snapshot
          </Button>
        </div>
      ) : (
        <div className="px-4 py-6 flex flex-col items-center gap-2 text-center">
          <div className="w-9 h-9 rounded-lg bg-muted/50 flex items-center justify-center">
            <QrCode className="h-4 w-4 text-muted-foreground/60" />
          </div>
          <p className="text-xs text-muted-foreground leading-relaxed">
            Snapshot generated after approval or rejection
          </p>
        </div>
      )}
    </div>
  );

  // History Block
  const HistoryBlock = history.length >= 1 ? (
    <div className="space-y-2">
      {history.map((rev: SubmissionRevision, idx: number) => {
        const displayStatus = rev.latest_status || rev.status || "Pending";

        const revStatusVariant: ChipVariant =
          displayStatus === "Approved"
            ? "approved"
            : displayStatus === "Rejected"
            ? "rejected"
            : displayStatus === "Auto-Rejected"
            ? "auto-rejected"
            : "pending";

        const revUrl = route("staff-dashboard.submission.view", {
          id: rev.progress_id || submission.progress_id,
        });

        return (
          <a
            key={rev.id}
            href={revUrl}
            className="flex items-center justify-between gap-3 py-3 border-b border-border/50 last:border-0 hover:bg-accent/30 motion-safe:transition-colors rounded px-1 -mx-1 cursor-pointer"
          >
            <div className="min-w-0 space-y-0.5">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="text-sm font-semibold text-foreground">Revision #{idx + 1}</span>
                <Chip variant={revStatusVariant}>{displayStatus}</Chip>
                {rev.is_latest && <Chip variant="latest">Latest</Chip>}
              </div>
              <p className="text-xs text-muted-foreground">
                {formatDate(rev.created_at, { month: "short", day: "numeric", year: "numeric" })}
              </p>
            </div>
            <Eye className="h-3.5 w-3.5 text-muted-foreground/50 flex-shrink-0" />
          </a>
        );
      })}
    </div>
  ) : null;

  return (
    <>
      <Head title={`Review: ${submission.form_name}`} />

      <AppLayout title={submission.form_name} subtitle={layoutSubtitle}>
        <div className="mx-auto w-full max-w-[1520px] space-y-4 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
          {/* Back nav */}
          <div>
            <Link
              href={route("staff-dashboard.index")}
              className="group inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground motion-safe:transition-colors"
            >
              <ChevronLeft className="h-4 w-4 motion-safe:transition-transform motion-safe:group-hover:-translate-x-0.5" />
              Back to Dashboard
            </Link>
          </div>

          {finalApprovalNotice ? (
            <Alert className="border-emerald-500/30 bg-emerald-500/10">
              <CheckCircle2 className="h-4 w-4 text-emerald-700 dark:text-emerald-300" />
              <AlertTitle>Fully approved</AlertTitle>
              <AlertDescription>{finalApprovalNotice}</AlertDescription>
            </Alert>
          ) : null}

          <div className="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_360px]">
            {/* ───── Left column: tabbed content ───── */}
            <div className="space-y-5">
              <div>
                {/* Tab bar */}
                <div className="border-b border-border/50">
                  <nav className="flex gap-0 overflow-x-auto -mx-1 px-1" role="tablist">
                    {mobileTabs.map((t) => {
                      const icons: Record<string, React.ReactNode> = {
                        details: <FileText className="h-3.5 w-3.5" />,
                        workflow: <GitBranch className="h-3.5 w-3.5" />,
                        comments: <MessageSquare className="h-3.5 w-3.5" />,
                        history: <History className="h-3.5 w-3.5" />,
                        summary: <CalendarDays className="h-3.5 w-3.5" />,
                      };
                      return (
                        <button
                          key={t.id}
                          role="tab"
                          id={`tab-${t.id}`}
                          aria-selected={activeTab === t.id}
                          aria-controls={`panel-${t.id}`}
                          onClick={() => setActiveTab(t.id)}
                          className={`
                            shrink-0 inline-flex items-center gap-1.5 px-3 py-2.5 text-xs font-medium
                            border-b-2 motion-safe:transition-colors whitespace-nowrap
                            ${t.id === "summary" ? "lg:hidden" : ""}
                            ${
                              activeTab === t.id
                                ? "border-primary text-primary"
                                : "border-transparent text-muted-foreground hover:text-foreground hover:border-border"
                            }
                          `}
                        >
                          {icons[t.id]}
                          {t.label}
                        </button>
                      );
                    })}
                  </nav>
                </div>

                {/* Tab panels */}
                <div
                  role="tabpanel"
                  id={`panel-${activeTab}`}
                  aria-labelledby={`tab-${activeTab}`}
                  className="pt-5"
                >
                  {activeTab === "details" && (
                    <SubmissionFieldsDisplay
                      submission={submission}
                      facilities={facilities}
                      onFilePreview={handleFilePreview}
                    />
                  )}

                  {activeTab === "workflow" && (
                    submission.workflow && submission.workflow.length > 0 ? (
                      <WorkflowTimeline
                        workflow={submission.workflow}
                        workflowDuration={submission.workflow_duration}
                        onFilePreview={handleFilePreview}
                      />
                    ) : (
                      <EmptyState
                        icon={<GitBranch className="h-5 w-5 text-muted-foreground/50" />}
                        message="No workflow steps yet"
                      />
                    )
                  )}

                  {activeTab === "comments" && (
                    <CommentsTab
                      workflow={submission.workflow ?? undefined}
                      onFilePreview={handleFilePreview}
                    />
                  )}

                  {activeTab === "history" && (
                    HistoryBlock ? (
                      HistoryBlock
                    ) : (
                      <EmptyState
                        icon={<History className="h-5 w-5 text-muted-foreground/50" />}
                        message="No revision history available"
                      />
                    )
                  )}

                  {activeTab === "summary" && (
                    <div className="lg:hidden space-y-3">
                      {SnapshotBlock}

                      <div className="relative bg-card border border-border/60 rounded-xl overflow-hidden motion-safe:transition-colors">
                        <div className="flex items-center gap-2 px-4 sm:px-5 py-3.5 border-b border-border/50">
                          <CalendarDays className="h-3.5 w-3.5 text-muted-foreground/60 flex-shrink-0" />
                          <span className="text-sm font-semibold text-foreground">Request Summary</span>
                        </div>
                        <dl className="divide-y divide-border/60 px-4">
                          <div className="flex items-center justify-between py-2.5 gap-3">
                            <dt className="text-xs text-muted-foreground">Submitted</dt>
                            <dd className="text-xs font-medium text-foreground tabular-nums text-right">
                              {formatDate(submission.created_at)}
                            </dd>
                          </div>
                          <div className="flex items-center justify-between py-2.5 gap-3">
                            <dt className="text-xs text-muted-foreground">Current Step</dt>
                            <dd className="flex items-center gap-1">
                              <Chip variant={statusChipVariant}>{latestStatus}</Chip>
                            </dd>
                          </div>
                          {submission.form_code && (
                            <div className="flex items-center justify-between py-2.5 gap-3">
                              <dt className="text-xs text-muted-foreground">Form Code</dt>
                              <dd className="text-xs font-mono font-semibold text-foreground">{submission.form_code}</dd>
                            </div>
                          )}
                        </dl>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* ───── Right column: sidebar cards ───── */}
            <div className="hidden lg:block lg:sticky lg:top-6 lg:self-start space-y-3 lg:pt-14">
              {SnapshotBlock}

              {/* Review Actions */}
              <div className="relative bg-card border border-border/60 rounded-xl overflow-hidden motion-safe:transition-colors">
                <div className="flex items-center gap-2 px-4 sm:px-5 py-3.5 border-b border-border/50">
                  <CheckCircle2 className="h-3.5 w-3.5 text-muted-foreground/60 flex-shrink-0" />
                  <span className="text-sm font-semibold text-foreground">Review Actions</span>
                </div>
                <div className="p-4">
                  {canReviewSubmission && canReviewCurrent && submission.is_latest ? (
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        onClick={() => setShowApproveModal(true)}
                        className="flex-1 h-9 text-xs gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white"
                      >
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        Approve
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => setShowRejectModal(true)}
                        className="flex-1 h-9 text-xs gap-1.5"
                      >
                        <XCircle className="h-3.5 w-3.5" />
                        Reject
                      </Button>
                    </div>
                  ) : canReviewSubmission ? (
                    <div className="flex items-start gap-2 rounded-lg bg-muted/40 border border-border/50 p-3">
                      <AlertCircle className="h-4 w-4 text-muted-foreground/70 flex-shrink-0 mt-0.5" />
                      <p className="text-xs text-muted-foreground leading-relaxed">
                        Already reviewed or not the latest revision.
                      </p>
                    </div>
                  ) : (
                    <div className="flex items-start gap-2 rounded-lg bg-muted/40 border border-border/50 p-3">
                      <AlertCircle className="h-4 w-4 text-muted-foreground/70 flex-shrink-0 mt-0.5" />
                      <p className="text-xs text-muted-foreground leading-relaxed">
                        No permission to approve or reject.
                      </p>
                    </div>
                  )}
                </div>
              </div>

              {/* Request Summary */}
              <div className="relative bg-card border border-border/60 rounded-xl overflow-hidden motion-safe:transition-colors">
                <div className="flex items-center gap-2 px-4 sm:px-5 py-3.5 border-b border-border/50">
                  <CalendarDays className="h-3.5 w-3.5 text-muted-foreground/60 flex-shrink-0" />
                  <span className="text-sm font-semibold text-foreground">Request Summary</span>
                </div>
                <dl className="divide-y divide-border/60 px-4">
                  <div className="flex items-center justify-between py-2.5 gap-3">
                    <dt className="text-xs text-muted-foreground">Submitted</dt>
                    <dd className="text-xs font-medium text-foreground tabular-nums text-right">
                      {formatDate(submission.created_at)}
                    </dd>
                  </div>
                  <div className="flex items-center justify-between py-2.5 gap-3">
                    <dt className="text-xs text-muted-foreground">Current Step</dt>
                    <dd className="flex items-center gap-1">
                      <Chip variant={statusChipVariant}>{latestStatus}</Chip>
                    </dd>
                  </div>
                  {submission.form_code && (
                    <div className="flex items-center justify-between py-2.5 gap-3">
                      <dt className="text-xs text-muted-foreground">Form Code</dt>
                      <dd className="text-xs font-mono font-semibold text-foreground">{submission.form_code}</dd>
                    </div>
                  )}
                </dl>
              </div>
            </div>
          </div>

          {/* Mobile sticky action bar */}
          {canReviewSubmission && canReviewCurrent && submission.is_latest && (
            <div className="lg:hidden sticky bottom-0 left-0 right-0 bg-background/95 backdrop-blur-sm border-t border-border/50 shadow-lg p-4 -mx-4 sm:-mx-6 mt-4">
              <div className="flex gap-2">
                <Button
                  className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white h-10 text-sm gap-1.5"
                  onClick={() => setShowApproveModal(true)}
                >
                  <CheckCircle2 className="h-4 w-4" />
                  Approve
                </Button>
                <Button
                  variant="destructive"
                  className="flex-1 h-10 text-sm gap-1.5"
                  onClick={() => setShowRejectModal(true)}
                >
                  <XCircle className="h-4 w-4" />
                  Reject
                </Button>
              </div>
            </div>
          )}
        </div>
      </AppLayout>

      {showApproveModal && (
        <ApproveModal
          open={showApproveModal}
          onOpenChange={setShowApproveModal}
          progressId={submission.progress_id}
          onApprove={handleApprove}
        />
      )}

      {showRejectModal && (
        <RejectModal
          open={showRejectModal}
          onOpenChange={setShowRejectModal}
          progressId={submission.progress_id}
          onReject={handleReject}
        />
      )}

      {pdfViewer && (
        <FileViewerDialog
          open={!!pdfViewer}
          onOpenChange={(open) => !open && setPdfViewer(null)}
          url={pdfViewer.url}
          title={pdfViewer.title}
          mime={pdfViewer.mime}
        />
      )}
    </>
  );
}

// ─── Sub-components ──────────────────────────────────────────────────────────

function EmptyState({ icon, message }: { icon: React.ReactNode; message: string }) {
  return (
    <div className="flex flex-col items-center gap-3 py-14 rounded-xl border border-dashed border-border/60">
      <div className="w-10 h-10 rounded-xl bg-muted/50 flex items-center justify-center">{icon}</div>
      <p className="text-sm text-muted-foreground">{message}</p>
    </div>
  );
}

function AttachmentItem({
  att,
  onPreview,
}: {
  att: WorkflowAttachment;
  onPreview: (url: string, name: string, mime?: string) => void;
}) {
  const isImg = att.mime_type?.startsWith("image/") || /\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i.test(att.original_name);
  const isPdfFile = att.mime_type?.includes("pdf") || att.original_name.toLowerCase().endsWith(".pdf");
  const canPreview = isImg || isPdfFile;

  return (
    <div className="flex items-start gap-2.5 py-2 hover:bg-accent/20 rounded motion-safe:transition-colors">
      <div className="w-7 h-7 rounded-md bg-muted/60 flex items-center justify-center flex-shrink-0">
        <FileText className="h-3.5 w-3.5 text-muted-foreground" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium truncate" title={att.original_name}>
          {att.original_name}
        </p>
        <p className="text-[11px] text-muted-foreground mt-0.5">
          {att.uploaded_by_name}
          {att.uploaded_at && ` · ${formatDate(att.uploaded_at)}`}
          {att.size_bytes && ` · ${(att.size_bytes / 1024).toFixed(1)} KB`}
        </p>
        <div className="flex gap-1.5 mt-2">
          {canPreview && att.preview_url && (
            <button
              onClick={() => onPreview(att.preview_url!, att.original_name, isPdfFile ? "application/pdf" : "image/*")}
              className="inline-flex items-center gap-1 rounded bg-primary hover:bg-primary/90 text-primary-foreground px-2 py-1 text-[11px] font-medium motion-safe:transition-colors"
            >
              <ExternalLink className="h-3 w-3" />
              Preview
            </button>
          )}
          {att.download_url && (
            <a
              href={att.download_url}
              download
              className="inline-flex items-center gap-1 rounded bg-secondary text-secondary-foreground px-2 py-1 text-[11px] font-medium hover:bg-secondary/80 motion-safe:transition-colors"
            >
              <Download className="h-3 w-3" />
              Download
            </a>
          )}
        </div>
      </div>
    </div>
  );
}

function CommentsTab({
  workflow,
  onFilePreview,
}: {
  workflow?: WorkflowStep[];
  onFilePreview: (url: string, name: string, mime?: string) => void;
}) {
  if (!workflow || workflow.length === 0) {
    return (
      <EmptyState
        icon={<MessageSquare className="h-5 w-5 text-muted-foreground/50" />}
        message="No comments yet"
      />
    );
  }

  const stepsWithComments = workflow.filter(
    (step: WorkflowStep) =>
      (step.comments && step.comments.trim()) ||
      (step.attachments && step.attachments.length > 0)
  );

  if (stepsWithComments.length === 0) {
    return (
      <EmptyState
        icon={<MessageSquare className="h-5 w-5 text-muted-foreground/50" />}
        message="No comments yet"
      />
    );
  }

  return (
    <div className="divide-y divide-border/50">
      {stepsWithComments.map((step: WorkflowStep, idx: number) => {
        const variant =
          step.status === "Approved" ? "approved" : step.status === "Rejected" ? "rejected" : "pending";

        return (
          <div key={idx} className="py-4 space-y-2">
            {/* Comment header */}
            <div className="flex items-center justify-between gap-2">
              <div>
                <p className="text-sm font-semibold text-foreground">{step.step}</p>
                <p className="text-[11px] text-muted-foreground mt-0.5">
                  {step.actor || "Unknown"}&nbsp;·&nbsp;
                  {formatDateTime(step.acted_at)}
                </p>
              </div>
              <Chip variant={variant}>{step.status}</Chip>
            </div>

            {/* Comment body */}
            {step.comments && step.comments.trim() && (
              <p className="text-sm text-foreground whitespace-pre-wrap leading-relaxed">{step.comments}</p>
            )}

            {/* Attachments */}
            {Array.isArray(step.attachments) && step.attachments.length > 0 && (
              <div className="pt-1 space-y-0.5">
                <p className="text-[11px] font-medium text-muted-foreground/70 mb-1.5">
                  {step.attachments.length} Attachment{step.attachments.length !== 1 ? "s" : ""}
                </p>
                {step.attachments.map((att: WorkflowAttachment) => (
                  <AttachmentItem key={att.id} att={att} onPreview={onFilePreview} />
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
