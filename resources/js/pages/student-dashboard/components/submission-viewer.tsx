import React, { useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import AppLayout from "@/layouts/app-layout";
import FileViewerDialog from "@/components/FileViewerDialog";
import EmptyState from "@/components/EmptyState";
import { Button } from "@/components/ui/button";
import { SubmissionFieldsDisplay } from "@/pages/staff-dashboard/components/SubmissionFieldsDisplay";
import { Chip } from "@/pages/staff-dashboard/components/ui/Chip";
import {
  ChevronLeft,
  FileText,
  Download,
  ExternalLink,
  Clock,
  Eye,
  GitBranch,
  MessageSquare,
  History,
  QrCode,
  CalendarDays,
} from "lucide-react";
import type { SubmissionData, WorkflowStep, WorkflowAttachment } from "@/pages/staff-dashboard/types/submissionTypes";
import type { PageProps as InertiaPageProps } from "@inertiajs/core";
import { formatDate, formatDateTime } from "@/utils/dateTime";

interface PageProps extends InertiaPageProps {
  submission: SubmissionData;
  submissionViewRouteName?: string;
  routeNamespace?: "student-dashboard" | "staff-dashboard";
  backHref?: string;
  flash?: {
    submission_success?: {
      form_name: string;
      submission_id: number;
    } | null;
  };
}

type ChipStatusVariant = "approved" | "rejected" | "auto-rejected" | "pending";

interface SubmissionRevision {
  id: number;
  status?: string;
  latest_status?: string;
  created_at?: string;
  updated_at?: string;
}

export default function SubmissionViewer() {
  const {
    submission,
    submissionViewRouteName = "student-dashboard.submission.view",
    routeNamespace = "student-dashboard",
    backHref = "/student-dashboard",
    flash,
  } = usePage<PageProps>().props;
  const [activeTab, setActiveTab] = useState<string>("details");
  const [pdfViewer, setPdfViewer] = useState<{ url: string; title: string; mime?: string } | null>(null);
  const [facilities, setFacilities] = useState<Array<{ id: number; name: string }>>([]);
  const rawHistory = (submission as SubmissionData & { history?: unknown }).history;
  const history: SubmissionRevision[] = Array.isArray(rawHistory) ? (rawHistory as SubmissionRevision[]) : [];
  const submissionSuccess = flash?.submission_success ?? null;
  const dashboardRouteName =
    routeNamespace === "staff-dashboard"
      ? "staff-dashboard.my-submissions.index"
      : "student-dashboard.index";
  const formsRouteName =
    routeNamespace === "staff-dashboard"
      ? "staff-dashboard.forms.index"
      : "student-dashboard.forms.index";

  // Fetch facilities on mount
  React.useEffect(() => {
    const fetchFacilities = async () => {
      try {
        const response = await axios.get("/admin/facilities/active");
        setFacilities(response.data);
      } catch (error) {
        console.error("Failed to fetch facilities:", error);
        setFacilities([]);
      }
    };
    fetchFacilities();
  }, []);

  // Determine latest status
  const latestStatus =
    submission.workflow && submission.workflow.length > 0
      ? submission.workflow[submission.workflow.length - 1].status
      : "Pending";

  const statusChipVariant =
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
      <Chip variant={statusChipVariant as ChipStatusVariant}>{latestStatus}</Chip>
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
    <div className="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden motion-safe:transition-colors">
      <div className="flex items-center gap-2 px-4 sm:px-5 py-3.5 border-b border-gray-100 dark:border-gray-800">
        <QrCode className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">Verification Snapshot</span>
      </div>
      {submission.snapshot?.exists ? (
        <div className="p-4 space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700/60 px-3 py-2.5">
              <p className="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Status</p>
              <p className="text-sm font-semibold capitalize text-gray-900 dark:text-gray-100">{submission.snapshot.status}</p>
            </div>
            <div className="rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700/60 px-3 py-2.5">
              <p className="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Code</p>
              <p className="text-sm font-mono font-bold text-gray-900 dark:text-gray-100 tracking-wide">{submission.snapshot.short_code}</p>
            </div>
          </div>
          <button
            onClick={() => submission.snapshot?.url && window.open(submission.snapshot.url, "_blank")}
            className="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 px-3 h-8 text-xs font-medium text-gray-700 dark:text-gray-300 motion-safe:transition-colors"
          >
            <ExternalLink className="h-3.5 w-3.5" />
            Open Snapshot
          </button>
        </div>
      ) : (
        <div className="px-4 py-6 flex flex-col items-center gap-2 text-center">
          <div className="w-9 h-9 rounded-lg bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
            <QrCode className="h-4 w-4 text-gray-400 dark:text-gray-500" />
          </div>
          <p className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
            Snapshot generated after approval or rejection
          </p>
        </div>
      )}
    </div>
  );

  // History Block
  const HistoryBlock =
    history.length >= 1 ? (
      <div className="space-y-2">          
        {history.map((rev, idx: number) => {
          const displayStatus = rev.latest_status || rev.status || "Pending";

          const revStatusVariant =
            displayStatus === "Approved"
              ? "approved"
              : displayStatus === "Rejected"
              ? "rejected"
              : displayStatus === "Auto-Rejected"
              ? "auto-rejected"
              : "pending";

          const revUrl = route(submissionViewRouteName, {
            formId: submission.form_id,
            submissionId: rev.id,
          });

          return (
            <Link
              key={rev.id}
              href={revUrl}
              className="flex items-center justify-between gap-3 py-3 border-b border-gray-100 dark:border-gray-800 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 motion-safe:transition-colors rounded px-2 -mx-2"
            >
              <div className="min-w-0 space-y-0.5">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">Revision #{idx + 1}</span>
                  <Chip variant={revStatusVariant as ChipStatusVariant}>{displayStatus}</Chip>
                  {rev.id === submission.id && <Chip variant="latest">Latest</Chip>}
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {formatDate(rev.created_at, { month: "short", day: "numeric", year: "numeric" })}
                </p>
              </div>
              <Eye className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
            </Link>
          );
        })}
      </div>
    ) : null;

  return (
    <>
      <Head title={`View: ${submission.form_name}`} />

      <AppLayout title={submission.form_name} subtitle={layoutSubtitle}>
        <div className="mx-auto w-full max-w-[1600px] px-3 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-8 space-y-6 sm:space-y-8">
          {/* Back nav */}
          <div>
            <Link
              href={backHref}
              className="group inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 motion-safe:transition-colors"
            >
              <ChevronLeft className="h-4 w-4 motion-safe:transition-transform motion-safe:group-hover:-translate-x-0.5" />
              Back to Dashboard
            </Link>
          </div>

          {submissionSuccess ? (
            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:p-5 dark:border-emerald-800/30 dark:bg-emerald-900/20">
              <h2 className="text-base font-semibold text-emerald-800 dark:text-emerald-300">Your request has been submitted!</h2>
              <p className="mt-2 text-sm text-emerald-700 dark:text-emerald-400">
                <span className="font-medium text-emerald-900 dark:text-emerald-200">Form:</span> {submissionSuccess.form_name}
              </p>
              <p className="text-sm text-emerald-700 dark:text-emerald-400">
                <span className="font-medium text-emerald-900 dark:text-emerald-200">Reference number:</span> #{submissionSuccess.submission_id}
              </p>
              <p className="mt-3 text-sm text-emerald-700 dark:text-emerald-400">
                Your request will be reviewed by the assigned approvers. You'll be notified by email and in-app when there's an update.
              </p>
              <div className="mt-4 flex flex-wrap gap-2">
                <Button asChild size="sm" variant="outline">
                  <Link href={route(dashboardRouteName)}>View my submissions</Link>
                </Button>
                <Button asChild size="sm">
                  <Link href={route(formsRouteName)}>Submit another request</Link>
                </Button>
              </div>
            </div>
          ) : null}

          <div className="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_360px]">
            {/* ───── Left column: tabbed content ───── */}
            <div className="space-y-5">
              <div>
                {/* Tab bar */}
                <div className="border-b border-gray-200 dark:border-gray-700">
                  <nav role="tablist" aria-label="Submission sections" className="flex gap-0 overflow-x-auto -mx-1 px-1">
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
                                ? "border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400"
                                : "border-transparent text-gray-500 hover:text-gray-900 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-100 dark:hover:border-gray-600"
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
                  tabIndex={0}
                  className="pt-5 focus-visible:outline-none"
                >
                  {activeTab === "details" && (
                    <SubmissionFieldsDisplay
                      submission={submission}
                      facilities={facilities}
                      onFilePreview={handleFilePreview}
                    />
                  )}

                  {activeTab === "workflow" && (
                    <WorkflowTab
                      workflow={submission.workflow ?? undefined}
                      workflowDuration={submission.workflow_duration}
                      onFilePreview={handleFilePreview}
                    />
                  )}

                  {activeTab === "comments" && (
                    <CommentsTab
                      workflow={submission.workflow ?? undefined}
                      onFilePreview={handleFilePreview}
                    />
                  )}

                  {activeTab === "history" &&
                    (HistoryBlock ? (
                      HistoryBlock
                    ) : (
                      <EmptyState
                        icon={<History className="h-5 w-5 text-muted-foreground/50" />}
                        title="No revision history available"
                      />
                    ))}

                  {activeTab === "summary" && (
                    <div className="lg:hidden space-y-3">
                      {SnapshotBlock}

                      <div className="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden motion-safe:transition-colors">
                        <div className="flex items-center gap-2 px-4 sm:px-5 py-3.5 border-b border-gray-100 dark:border-gray-800">
                          <CalendarDays className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
                          <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">Request Summary</span>
                        </div>
                        <dl className="divide-y divide-gray-100 dark:divide-gray-800 px-4">
                          <div className="flex items-center justify-between py-2.5 gap-3">
                            <dt className="text-xs text-gray-500 dark:text-gray-400">Submitted</dt>
                            <dd className="text-xs font-medium text-gray-900 dark:text-gray-100 tabular-nums text-right">
                              {formatDate(submission.created_at)}
                            </dd>
                          </div>
                          <div className="flex items-center justify-between py-2.5 gap-3">
                            <dt className="text-xs text-gray-500 dark:text-gray-400">Current Step</dt>
                            <dd className="flex items-center gap-1">
                              <Chip variant={statusChipVariant as ChipStatusVariant}>{latestStatus}</Chip>
                            </dd>
                          </div>
                          {submission.form_code && (
                            <div className="flex items-center justify-between py-2.5 gap-3">
                              <dt className="text-xs text-gray-500 dark:text-gray-400">Form Code</dt>
                              <dd className="text-xs font-mono font-semibold text-gray-900 dark:text-gray-100">{submission.form_code}</dd>
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

              {/* Request Summary */}
              <div className="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden motion-safe:transition-colors">
                <div className="flex items-center gap-2 px-4 sm:px-5 py-3.5 border-b border-gray-100 dark:border-gray-800">
                  <CalendarDays className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
                  <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">Request Summary</span>
                </div>
                <dl className="divide-y divide-gray-100 dark:divide-gray-800 px-4">
                  <div className="flex items-center justify-between py-2.5 gap-3">
                    <dt className="text-xs text-gray-500 dark:text-gray-400">Submitted</dt>
                    <dd className="text-xs font-medium text-gray-900 dark:text-gray-100 tabular-nums text-right">
                      {formatDate(submission.created_at)}
                    </dd>
                  </div>
                  <div className="flex items-center justify-between py-2.5 gap-3">
                    <dt className="text-xs text-gray-500 dark:text-gray-400">Current Step</dt>
                    <dd className="flex items-center gap-1">
                      <Chip variant={statusChipVariant as ChipStatusVariant}>{latestStatus}</Chip>
                    </dd>
                  </div>
                  {submission.form_code && (
                    <div className="flex items-center justify-between py-2.5 gap-3">
                      <dt className="text-xs text-gray-500 dark:text-gray-400">Form Code</dt>
                      <dd className="text-xs font-mono font-semibold text-gray-900 dark:text-gray-100">{submission.form_code}</dd>
                    </div>
                  )}
                </dl>
              </div>
            </div>
          </div>

        </div>
      </AppLayout>

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
    <div className="flex items-start gap-2.5 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/50 rounded motion-safe:transition-colors">
      <div className="w-7 h-7 rounded-md bg-gray-100 dark:bg-gray-800 flex items-center justify-center flex-shrink-0">
        <FileText className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" title={att.original_name}>
          {att.original_name}
        </p>
        <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
          {att.uploaded_by_name}
          {att.uploaded_at && ` · ${formatDate(att.uploaded_at)}`}
          {att.size_bytes && ` · ${(att.size_bytes / 1024).toFixed(1)} KB`}
        </p>
        <div className="flex gap-1.5 mt-2">
          {canPreview && att.preview_url && (
            <button
              onClick={() => onPreview(att.preview_url!, att.original_name, isPdfFile ? "application/pdf" : "image/*")}
              className="inline-flex items-center gap-1 rounded bg-blue-600 hover:bg-blue-700 text-white dark:bg-blue-600 dark:hover:bg-blue-700 px-2 py-1 text-[11px] font-medium motion-safe:transition-colors"
            >
              <ExternalLink className="h-3 w-3" />
              Preview
            </button>
          )}
          {att.download_url && (
            <a
              href={att.download_url}
              download
              className="inline-flex items-center gap-1 rounded bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 px-2 py-1 text-[11px] font-medium hover:bg-gray-200 dark:hover:bg-gray-700 motion-safe:transition-colors"
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

function WorkflowTab({
  workflow,
  workflowDuration,
  onFilePreview,
}: {
  workflow?: WorkflowStep[];
  workflowDuration?: { total_seconds: number; total_human: string | null };
  onFilePreview: (url: string, name: string, mime?: string) => void;
}) {
  if (!workflow || workflow.length === 0) {
    return (
      <EmptyState
        icon={<GitBranch className="h-5 w-5 text-gray-400 dark:text-gray-500" />}
        title="No workflow steps yet"
      />
    );
  }

  const toStatus = (step: WorkflowStep): "pending" | "in_progress" | "approved" | "rejected" | "skipped" => {
    if (step.status_key) {
      return step.status_key;
    }

    const raw = (step.status || "").toLowerCase();
    if (raw === "pending") return "in_progress";
    if (raw === "waiting") return "pending";
    if (raw.startsWith("appr") || raw === "completed") return "approved";
    if (raw.startsWith("rejec") || raw.includes("auto-reject")) return "rejected";
    if (raw === "skipped") return "skipped";

    return "pending";
  };

  const toLabel = (status: "pending" | "in_progress" | "approved" | "rejected" | "skipped") => {
    if (status === "in_progress") return "In progress";
    if (status === "approved") return "Approved";
    if (status === "rejected") return "Rejected";
    if (status === "skipped") return "Skipped";

    return "Pending";
  };

  const normalizedSteps = workflow.map((step, idx) => {
    const status = toStatus(step);
    return {
      ...step,
      normalized_status: status,
      display_status: step.status_label ?? toLabel(status),
      assignee_label: step.assignee ?? step.actor ?? "Approver role",
      index: step.step_index ?? idx + 1,
    };
  });

  const totalSteps = normalizedSteps.length;
  const rejectedStep = normalizedSteps.find((step) => step.normalized_status === "rejected");
  const currentStep =
    normalizedSteps.find((step) => step.normalized_status === "in_progress") ??
    normalizedSteps.find((step) => step.normalized_status === "pending");
  const allApproved = normalizedSteps.every((step) =>
    ["approved", "skipped"].includes(step.normalized_status),
  );

  const summaryMessage = rejectedStep
    ? `Your request was not approved at step ${rejectedStep.index}.${rejectedStep.comments ? ` ${rejectedStep.comments}` : ""}`
    : allApproved
      ? "Your request has been fully approved."
      : `Currently with ${currentStep?.assignee_label ?? "the assigned approver"} — step ${currentStep?.index ?? 1} of ${totalSteps}`;

  return (
    <div>
      <div className="relative">
        {normalizedSteps.map((step, idx: number) => {
          const isLast = idx === normalizedSteps.length - 1;
          const dotColor =
            step.normalized_status === "approved"
              ? "bg-emerald-500"
              : step.normalized_status === "rejected"
                ? "bg-red-500"
                : step.normalized_status === "in_progress"
                  ? "bg-blue-500"
                  : step.normalized_status === "skipped"
                    ? "bg-gray-400 dark:bg-gray-500"
                    : "bg-gray-200 dark:bg-gray-700";

          const statusChipClass =
            step.normalized_status === "approved"
              ? "bg-emerald-50 border-emerald-500/30 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300"
              : step.normalized_status === "rejected"
                ? "bg-red-50 border-red-500/30 text-red-700 dark:bg-red-950/30 dark:text-red-300"
                : step.normalized_status === "in_progress"
                  ? "bg-blue-50 border-blue-500/30 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"
                  : step.normalized_status === "skipped"
                    ? "bg-gray-100 border-gray-200 text-gray-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400"
                    : "bg-amber-50 border-amber-500/30 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300";

          return (
            <div key={idx} className="relative flex gap-4 pb-7 last:pb-0">
              {/* Timeline dot + connector line */}
              <div className="relative flex flex-col items-center w-4 flex-shrink-0 pt-1">
                <div className={`h-2 w-2 rounded-full ring-2 ring-white dark:ring-gray-900 relative z-10 ${dotColor}`} />
                {!isLast && (
                  <div className="absolute top-3 bottom-0 w-px bg-gray-200 dark:bg-gray-700" style={{ left: "7px" }} />
                )}
              </div>

              {/* Step content */}
              <div className="flex-1 min-w-0 space-y-1 pb-1">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">{step.step}</span>
                  <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${statusChipClass}`}>
                    {step.display_status}
                  </span>
                  {step.duration_human && (
                    <span className="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                      <Clock className="h-3 w-3" />
                      {step.duration_human}
                    </span>
                  )}
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400">Assigned approver: {step.assignee_label}</p>
                {step.acted_at && (
                  <p className="text-xs text-gray-500 dark:text-gray-400">
                    {formatDateTime(step.acted_at)}
                  </p>
                )}
                {Array.isArray(step.attachments) && step.attachments.length > 0 && (
                  <div className="pt-2 space-y-0.5">
                    <p className="text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1.5">
                      {step.attachments.length} Attachment{step.attachments.length !== 1 ? "s" : ""}
                    </p>
                    {step.attachments.map((att: WorkflowAttachment) => (
                      <AttachmentItem key={att.id} att={att} onPreview={onFilePreview} />
                    ))}
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>

      <div className="mt-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-3">
        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status summary</p>
        <p className="mt-1 text-sm text-gray-900 dark:text-gray-100">{summaryMessage}</p>
      </div>

      {workflowDuration?.total_human && (
        <div className="flex items-center gap-1.5 pt-5 mt-1 border-t border-gray-100 dark:border-gray-800">
          <Clock className="h-3.5 w-3.5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
          <span className="text-xs text-gray-500 dark:text-gray-400">Total processing time:</span>
          <span className="text-xs font-semibold text-gray-900 dark:text-gray-100">{workflowDuration.total_human}</span>
        </div>
      )}
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
        icon={<MessageSquare className="h-5 w-5 text-gray-400 dark:text-gray-500" />}
        title="No comments yet"
      />
    );
  }

  const stepsWithComments = workflow.filter(
    (step: WorkflowStep) =>
      (step.comments && step.comments.trim()) ||
      (step.attachments && step.attachments.length > 0),
  );

  if (stepsWithComments.length === 0) {
    return (
      <EmptyState
        icon={<MessageSquare className="h-5 w-5 text-gray-400 dark:text-gray-500" />}
        title="No comments yet"
      />
    );
  }

  return (
    <div className="divide-y divide-gray-100 dark:divide-gray-800">
      {stepsWithComments.map((step: WorkflowStep, idx: number) => {
        const variant =
          step.status === "Approved" ? "approved" : step.status === "Rejected" ? "rejected" : "pending";

        return (
          <div key={idx} className="py-4 space-y-2">
            {/* Comment header */}
            <div className="flex items-center justify-between gap-2">
              <div>
                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{step.step}</p>
                <p className="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                  {step.actor || "Unknown"}&nbsp;·&nbsp;
                  {step.acted_at ? formatDateTime(step.acted_at) : "-"}
                </p>
              </div>
              <Chip variant={variant}>{step.status}</Chip>
            </div>

            {/* Comment body */}
            {step.comments && step.comments.trim() && (
              <p className="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap leading-relaxed">{step.comments}</p>
            )}

            {/* Attachments */}
            {Array.isArray(step.attachments) && step.attachments.length > 0 && (
              <div className="pt-1 space-y-0.5">
                <p className="text-[11px] font-medium text-gray-500 dark:text-gray-400 mb-1.5">
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

