import React, { useState } from "react";
import { Head, Link, usePage } from "@inertiajs/react";
import { toast } from "sonner";
import axios from "axios";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import FileViewerDialog from "@/components/FileViewerDialog";
import SnapshotQRDialog from "@/components/snapshots/SnapshotQRDialog";
import AdminOverrideControls from "@/components/submissions/AdminOverrideControls";
import { SubmissionFieldsDisplay } from "@/pages/staff-dashboard/components/SubmissionFieldsDisplay";
import { WorkflowTimeline } from "@/pages/staff-dashboard/components/WorkflowTimeline";
import { Chip } from "@/pages/staff-dashboard/components/ui/Chip";
import { ChevronLeft, QrCode, Copy, FileText, Download, ExternalLink } from "lucide-react";
import type { SubmissionData, SnapshotInfo } from "@/pages/staff-dashboard/types/submissionTypes";
import type { PageProps as InertiaPageProps } from "@inertiajs/core";
import type { SharedData } from "@/types";

interface PageProps extends InertiaPageProps {
  submission: SubmissionData;
  canAct: boolean;
  backUrl: string;
  auth: SharedData["auth"];
}

export default function AdminReviewPage() {
  const { submission, canAct, backUrl, auth } = usePage<PageProps>().props;
  const [activeTab, setActiveTab] = useState<string>("details");
  const [pdfViewer, setPdfViewer] = useState<{ url: string; title: string; mime?: string } | null>(null);
  const [snapshotOpen, setSnapshotOpen] = useState(false);
  const [snapshot, setSnapshot] = useState<SnapshotInfo>(
    (submission as any).snapshot?.exists ? (submission as any).snapshot : { exists: false }
  );
  const [facilities, setFacilities] = useState<Array<{ id: number; name: string }>>([]);
  const [actionInProgress, setActionInProgress] = useState(false);
  const [pollingForSnapshot, setPollingForSnapshot] = useState(false);

  const firstName = auth?.user?.name?.split(" ")[0] || "Admin";

  // Fetch snapshot on mount and when polling
  React.useEffect(() => {
    // Skip if we already have a snapshot from props and not polling
    if (!pollingForSnapshot && (submission as any).snapshot?.exists) {
      return;
    }

    const fetchSnapshot = async () => {
      if (!submission.progress_id) return;
      
      try {
        const { data } = await axios.get<SnapshotInfo>(
          `/admin/submissions/${submission.progress_id}/snapshot`,
          { headers: { "X-Requested-With": "XMLHttpRequest" } }
        );
        setSnapshot(data?.exists ? data : { exists: false });
        
        // If we found a snapshot while polling, stop polling
        if (pollingForSnapshot && data?.exists) {
          setPollingForSnapshot(false);
        }
      } catch (error) {
        console.error('Snapshot fetch error:', error);
        setSnapshot({ exists: false });
      }
    };
    fetchSnapshot();
  }, [submission.progress_id, pollingForSnapshot, submission]);

  // Poll for snapshot every 2 seconds when pollingForSnapshot is true
  React.useEffect(() => {
    if (!pollingForSnapshot) return;

    const interval = setInterval(async () => {
      if (!submission.progress_id) return;
      
      try {
        const { data } = await axios.get<SnapshotInfo>(
          `/admin/submissions/${submission.progress_id}/snapshot`,
          { headers: { "X-Requested-With": "XMLHttpRequest" } }
        );
        if (data?.exists) {
          setSnapshot(data);
          setPollingForSnapshot(false);
          toast.success("Snapshot generated successfully!");
        }
      } catch {
        // Continue polling on error
      }
    }, 2000);

    // Stop polling after 30 seconds
    const timeout = setTimeout(() => {
      setPollingForSnapshot(false);
    }, 30000);

    return () => {
      clearInterval(interval);
      clearTimeout(timeout);
    };
  }, [pollingForSnapshot, submission.progress_id]);

  // Fetch facilities on mount
  React.useEffect(() => {
    const fetchFacilities = async () => {
      try {
        const response = await fetch("/admin/facilities/active");
        if (response.ok) {
          const data = await response.json();
          setFacilities(data);
        }
      } catch (error) {
        console.error("Failed to fetch facilities:", error);
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

  // Check if submission is finalized (approved/rejected)
  const isFinalized = ["Approved", "Rejected", "Auto-Rejected"].includes(latestStatus);

  const tabs = [
    { id: "details", label: "Details" },
    { id: "workflow", label: "Workflow" },
    { id: "comments", label: "Comments" },
    ...((submission as any).history && (submission as any).history.length >= 1 ? [{ id: "history", label: "History" }] : []),
  ];

  const handleFilePreview = (url: string, title: string, mime?: string) => {
    setPdfViewer({ url, title, mime });
  };

  const handleCopySnapshotUrl = () => {
    if (snapshot?.url) {
      navigator.clipboard.writeText(snapshot.url);
      toast.success("Snapshot URL copied to clipboard");
    }
  };

  // Snapshot Block
  const SnapshotBlock = snapshot?.exists ? (
    <div className="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
      <div className="bg-muted/30 px-5 py-4 border-b border-border">
        <h2 className="text-sm font-semibold tracking-wide text-foreground">Verification Snapshot</h2>
        <p className="text-xs text-muted-foreground mt-1">
          Immutable, read-only view created on approval/rejection
        </p>
      </div>
      <div className="p-5 space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-xs text-muted-foreground mb-1">Status</p>
            <p className="text-sm font-medium capitalize">{snapshot.status}</p>
          </div>
          <div>
            <p className="text-xs text-muted-foreground mb-1">Short Code</p>
            <p className="text-sm font-mono font-semibold">{snapshot.short_code}</p>
          </div>
        </div>

        <div className="flex flex-col gap-2">
          <Button variant="outline" onClick={() => setSnapshotOpen(true)} className="w-full">
            <QrCode className="h-4 w-4 mr-2" />
            View QR Code
          </Button>
          <Button variant="outline" onClick={handleCopySnapshotUrl} className="w-full">
            <Copy className="h-4 w-4 mr-2" />
            Copy URL
          </Button>
        </div>
      </div>
    </div>
  ) : (
    <div className="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
      <div className="bg-muted/30 px-5 py-4 border-b border-border">
        <h2 className="text-sm font-semibold tracking-wide text-foreground">Verification Snapshot</h2>
        <p className="text-xs text-muted-foreground mt-1">
          Immutable, read-only view created on approval/rejection
        </p>
      </div>
      <div className="p-5">
        <p className="text-sm text-muted-foreground text-center py-4">
          Snapshot will be generated after admin approval or rejection
        </p>
      </div>
    </div>
  );

  // History Block
  const HistoryBlock = submission.history && submission.history.length > 1 ? (
    <div className="space-y-3">
      {submission.history.map((rev) => {
        const revStatusVariant =
          rev.status === "Approved"
            ? "approved"
            : rev.status === "Rejected"
            ? "rejected"
            : rev.status === "Auto-Rejected"
            ? "auto-rejected"
            : "pending";

        const revUrl = route("admin-submissions.show", {
          formId: (submission as any).form_id,
          submissionId: rev.id || (submission as any).submission_id,
        });

        return (
          <a
            key={rev.id}
            href={revUrl}
            className="block rounded-lg border border-border/60 bg-card p-4 transition-all hover:shadow-md hover:border-border cursor-pointer"
          >
            <div className="space-y-2">
              <div className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                  <h3 className="font-semibold text-sm">Version {rev.version || 1}</h3>
                  <Chip variant={revStatusVariant as any}>{rev.status}</Chip>
                  {rev.is_latest && <Chip variant="latest">Latest</Chip>}
                </div>
              </div>
              <div className="text-xs text-muted-foreground space-y-1">
                <div className="flex items-center gap-2">
                  <span className="flex-1">
                    Submitted: {rev.created_at ? new Date(rev.created_at).toLocaleString() : "—"}
                  </span>
                </div>
                {rev.updated_at && (
                  <div className="flex items-center gap-2">
                    <span className="flex-1">
                      Updated: {new Date(rev.updated_at).toLocaleString()}
                    </span>
                  </div>
                )}
              </div>
            </div>
          </a>
        );
      })}
    </div>
  ) : null;

  return (
    <>
      <Head title={`Admin Review: ${submission.form_name}`} />

      <AppLayout>
        {/* Header */}
        <div className="bg-gradient-to-br from-blue-50 via-indigo-50/30 to-background dark:from-blue-950/20 dark:via-indigo-950/10 dark:to-background border-b border-border">
          <div className="mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
            <Link
              href={backUrl}
              className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors mb-4"
            >
              <ChevronLeft className="h-4 w-4" />
              Back to All Submissions
            </Link>

            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div className="flex-1 min-w-0">
                <h1 className="text-2xl sm:text-3xl font-bold text-foreground mb-2 truncate">
                  {submission.form_name}
                </h1>
                <p className="text-sm text-muted-foreground mb-3">
                  Welcome back, {firstName}! Reviewing submission with admin privileges.
                </p>
                <div className="flex items-center gap-2 flex-wrap">
                  <Chip variant={statusChipVariant as any}>{latestStatus}</Chip>
                  <Chip variant="revision">Admin Override Mode</Chip>
                  {submission.form_code && (
                    <span className="text-sm text-muted-foreground">• {submission.form_code}</span>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Main Content */}
        <div className="mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-[2fr_1fr]">
            {/* Left Column */}
            <div className="space-y-6">
              {/* Tabs */}
              <div className="bg-card border border-border rounded-lg overflow-hidden shadow-sm">
                <nav className="flex w-full max-w-full flex-nowrap gap-1 overflow-x-auto whitespace-nowrap bg-muted/30 p-2">
                  {tabs.map((t) => (
                    <button
                      key={t.id}
                      onClick={() => setActiveTab(t.id)}
                      className={`
                        shrink-0 rounded-md px-4 py-2 text-sm font-medium transition-all
                        ${
                          activeTab === t.id
                            ? "bg-background text-foreground shadow-sm"
                            : "text-muted-foreground hover:text-foreground hover:bg-background/50"
                        }
                      `}
                    >
                      {t.label}
                    </button>
                  ))}
                </nav>
              </div>

              {/* Tab Content */}
              <div className="space-y-6">
                {activeTab === "details" && (
                  <SubmissionFieldsDisplay
                    submission={submission}
                    facilities={facilities}
                    onFilePreview={handleFilePreview}
                  />
                )}

                {activeTab === "workflow" && (
                  <WorkflowTimeline
                    workflow={submission.workflow || []}
                    workflowDuration={submission.workflow_duration}
                    onFilePreview={handleFilePreview}
                  />
                )}

                {activeTab === "comments" && (
                  <div className="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
                    <div className="bg-muted/30 px-5 py-4 border-b border-border">
                      <h2 className="text-sm font-semibold tracking-wide text-foreground">Approval Comments</h2>
                      <p className="text-xs text-muted-foreground mt-1">
                        Comments and attachments from approvers during the workflow process
                      </p>
                    </div>
                    <div className="p-5">
                      {submission.workflow && submission.workflow.length > 0 ? (
                        <div className="space-y-4">
                          {submission.workflow
                            .filter((step: any) => 
                              (step.comments && step.comments.trim()) || 
                              (step.attachments && step.attachments.length > 0)
                            )
                            .map((step: any, idx: number) => (
                              <div
                                key={idx}
                                className="rounded-lg border border-border/60 bg-muted/30 p-4"
                              >
                                <div className="flex items-start justify-between gap-3 mb-2">
                                  <div>
                                    <h3 className="font-semibold text-sm">{step.step}</h3>
                                    <p className="text-xs text-muted-foreground mt-1">
                                      {step.actor || "Unknown"} • {step.acted_at ? new Date(step.acted_at).toLocaleString() : "—"}
                                    </p>
                                  </div>
                                  <Chip variant={
                                    step.status === "Approved" ? "approved" :
                                    step.status === "Rejected" ? "rejected" :
                                    "pending"
                                  }>{step.status}</Chip>
                                </div>
                                
                                {step.comments && step.comments.trim() && (
                                  <div className="mt-3 text-sm text-foreground whitespace-pre-wrap bg-background/50 rounded-md p-3 border border-border/40">
                                    {step.comments}
                                  </div>
                                )}

                                {Array.isArray(step.attachments) && step.attachments.length > 0 && (
                                  <div className="mt-3 pt-3 border-t border-border/40">
                                    <div className="flex items-center gap-2 mb-3">
                                      <FileText className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                      <span className="text-xs font-semibold text-blue-600 dark:text-blue-400">
                                        {step.attachments.length} Attachment{step.attachments.length !== 1 ? "s" : ""}
                                      </span>
                                    </div>
                                    <div className="space-y-2">
                                      {step.attachments.map((att: any) => {
                                        const isImg = att.mime_type?.startsWith("image/") || /\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i.test(att.original_name);
                                        const isPdfFile = att.mime_type?.includes("pdf") || att.original_name.toLowerCase().endsWith(".pdf");
                                        const canPreview = isImg || isPdfFile;

                                        return (
                                          <div
                                            key={att.id}
                                            className="rounded-md border border-border/60 bg-background p-3 transition-all hover:bg-muted/50 hover:border-border"
                                          >
                                            <div className="flex items-start gap-3">
                                              <div className="flex-shrink-0">
                                                <FileText className="h-5 w-5 text-muted-foreground" />
                                              </div>
                                              <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium truncate" title={att.original_name}>
                                                  {att.original_name}
                                                </p>
                                                <div className="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
                                                  <span>{att.uploaded_by_name}</span>
                                                  {att.uploaded_at && (
                                                    <>
                                                      <span>•</span>
                                                      <span>{new Date(att.uploaded_at).toLocaleDateString()}</span>
                                                    </>
                                                  )}
                                                  {att.size_bytes && (
                                                    <>
                                                      <span>•</span>
                                                      <span>{(att.size_bytes / 1024).toFixed(1)} KB</span>
                                                    </>
                                                  )}
                                                </div>
                                                <div className="flex gap-2 mt-2">
                                                  {canPreview && att.preview_url && (
                                                    <button
                                                      onClick={() => handleFilePreview(
                                                        att.preview_url,
                                                        att.original_name,
                                                        isPdfFile ? "application/pdf" : "image/*"
                                                      )}
                                                      className="inline-flex items-center gap-1 rounded-md bg-[#1551f1] hover:bg-[#5296ea] text-white px-2 py-1 text-xs font-medium"
                                                    >
                                                      <ExternalLink className="h-3 w-3" />
                                                      Preview
                                                    </button>
                                                  )}
                                                  {att.download_url && (
                                                    <a
                                                      href={att.download_url}
                                                      download
                                                      className="inline-flex items-center gap-1 rounded-md bg-secondary text-secondary-foreground px-2 py-1 text-xs font-medium hover:bg-secondary/80"
                                                    >
                                                      <Download className="h-3 w-3" />
                                                      Download
                                                    </a>
                                                  )}
                                                </div>
                                              </div>
                                            </div>
                                          </div>
                                        );
                                      })}
                                    </div>
                                  </div>
                                )}
                              </div>
                            ))}
                        </div>
                      ) : (
                        <div className="text-center py-12 rounded-lg border border-dashed border-border/60">
                          <div className="flex flex-col items-center gap-3">
                            <div className="flex items-center justify-center w-12 h-12 rounded-full bg-muted/50">
                              <svg className="h-6 w-6 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                              </svg>
                            </div>
                            <p className="text-sm text-muted-foreground">No comments yet</p>
                          </div>
                        </div>
                      )}
                    </div>
                  </div>
                )}

                {activeTab === "history" && HistoryBlock}
              </div>
            </div>

            {/* Right Column - Desktop Only */}
            <div className="hidden lg:block lg:sticky lg:top-6 lg:self-start space-y-4">
              {SnapshotBlock}

              {/* Admin Override Controls - Only show if not finalized */}
              {!isFinalized && canAct && (
                <AdminOverrideControls
                  progressId={submission.progress_id}
                  canApprove={true}
                  canReject={true}
                  onActionStart={() => setActionInProgress(true)}
                  onActionComplete={() => {
                    setActionInProgress(false);
                    setPollingForSnapshot(true);
                  }}
                />
              )}

              {/* Request Summary */}
              <div className="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
                <div className="bg-muted/30 px-5 py-4 border-b border-border">
                  <h2 className="text-sm font-semibold tracking-wide text-foreground">Request Summary</h2>
                </div>
                <div className="p-5 space-y-2 text-sm">
                  <p>
                    <strong>Submitted:</strong> {new Date(submission.created_at).toLocaleString()}
                  </p>
                  <p>
                    <strong>Current Step:</strong> {latestStatus}
                  </p>
                  <p>
                    <strong>Submitter:</strong> {submission.submitter}
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Mobile Action Panel - Fixed at Bottom - Only show if not finalized */}
          {!isFinalized && canAct && (
            <div className="lg:hidden sticky bottom-0 left-0 right-0 bg-background border-t border-border shadow-lg p-4 -mx-4 sm:-mx-6 mt-6">
              <AdminOverrideControls
                progressId={submission.progress_id}
                canApprove={true}
                canReject={true}
                onActionStart={() => setActionInProgress(true)}
                onActionComplete={() => {
                  setActionInProgress(false);
                  setPollingForSnapshot(true);
                }}
              />
            </div>
          )}
        </div>
      </AppLayout>

      {/* Modals */}
      {pdfViewer && (
        <FileViewerDialog
          open={!!pdfViewer}
          onOpenChange={(open) => !open && setPdfViewer(null)}
          url={pdfViewer.url}
          title={pdfViewer.title}
          mime={pdfViewer.mime}
        />
      )}

      {snapshotOpen && snapshot?.exists && (
        <SnapshotQRDialog
          open={snapshotOpen}
          onOpenChange={setSnapshotOpen}
          url={snapshot.url!}
          shortCode={snapshot.short_code}
          status={snapshot.status}
        />
      )}
    </>
  );
}
