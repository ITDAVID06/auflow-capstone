import React from "react";
import { Clock, Download, ExternalLink, FileText, GitBranch, Image, FileSpreadsheet } from "lucide-react";
import { Button } from "@/components/ui/button";
import { formatDate, formatDateTime } from "@/utils/dateTime";
import { Chip } from "./ui/Chip";
import type { WorkflowStep, WorkflowAttachment } from "../types/submissionTypes";

interface WorkflowTimelineProps {
  workflow: WorkflowStep[];
  workflowDuration?: { total_seconds: number; total_human: string | null };
  onFilePreview: (url: string, title: string, mime?: string) => void;
}

type ChipVariant = "approved" | "rejected" | "auto-rejected" | "pending";

function AttachmentRow({
  att,
  onFilePreview,
}: {
  att: WorkflowAttachment;
  onFilePreview: (url: string, title: string, mime?: string) => void;
}) {
  const isImg = att.mime_type?.startsWith("image/") || /\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i.test(att.original_name);
  const isPdf = att.mime_type?.includes("pdf") || att.original_name.toLowerCase().endsWith(".pdf");
  const canPreview = isImg || isPdf;

  return (
    <div className="flex items-start gap-2.5 py-2 hover:bg-accent/20 rounded motion-safe:transition-colors">
      <div className="w-7 h-7 rounded-md bg-muted/60 flex items-center justify-center flex-shrink-0">
        {isImg
          ? <Image className="h-3.5 w-3.5 text-muted-foreground" />
          : /\.(xls|xlsx)$/i.test(att.original_name) || att.mime_type?.includes("spreadsheet")
            ? <FileSpreadsheet className="h-3.5 w-3.5 text-muted-foreground" />
            : <FileText className="h-3.5 w-3.5 text-muted-foreground" />}
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
            <Button
              size="sm"
              variant="default"
              className="h-6 px-2 text-[11px] gap-1"
              onClick={() => onFilePreview(att.preview_url!, att.original_name, isPdf ? "application/pdf" : "image/*")}
            >
              <ExternalLink className="h-3 w-3" />
              Preview
            </Button>
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

export const WorkflowTimeline: React.FC<WorkflowTimelineProps> = ({
  workflow,
  workflowDuration,
  onFilePreview,
}) => {
  if (!workflow || workflow.length === 0) {
    return (
      <div className="flex flex-col items-center gap-3 py-14 rounded-xl border border-dashed border-border/60">
        <div className="w-10 h-10 rounded-xl bg-muted/50 flex items-center justify-center">
          <GitBranch className="h-5 w-5 text-muted-foreground/50" />
        </div>
        <p className="text-sm text-muted-foreground">No workflow steps available</p>
      </div>
    );
  }

  return (
    <div aria-live="polite" aria-label="Workflow timeline">
      <div data-tour="review-workflow">
      <div className="relative">
        {workflow.map((step, idx) => {
          const isApproved = step.status === "Approved" || step.status === "Completed";
          const isRejected = step.status === "Rejected";
          const isAutoRejected = step.status === "Auto-Rejected";
          const isLast = idx === workflow.length - 1;
          const statusVariant: ChipVariant = isApproved
            ? "approved"
            : isRejected
            ? "rejected"
            : isAutoRejected
            ? "auto-rejected"
            : "pending";
          const dotColor = isApproved
            ? "bg-emerald-500"
            : isRejected || isAutoRejected
            ? "bg-destructive"
            : "bg-border";

          return (
            <div key={idx} className="relative flex gap-4 pb-7 last:pb-0">
              {/* Timeline dot + connector line */}
              <div className="relative flex flex-col items-center w-4 flex-shrink-0 pt-1">
                <div className={`h-2 w-2 rounded-full ring-2 ring-background relative z-10 ${dotColor}`} />
                {!isLast && (
                  <div className="absolute top-3 bottom-0 w-px bg-border/40" style={{ left: "7px" }} />
                )}
              </div>

              {/* Step content */}
              <div className="flex-1 min-w-0 space-y-1 pb-1">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="text-sm font-semibold text-foreground">{step.step}</span>
                  <Chip variant={statusVariant}>{step.status}</Chip>
                  {step.duration_human && (
                    <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                      <Clock className="h-3 w-3" />
                      {step.duration_human}
                    </span>
                  )}
                </div>
                {step.actor && (
                  <p className="text-xs text-muted-foreground">
                    {isApproved ? "Completed by " : isRejected || isAutoRejected ? "Rejected by " : "Assigned to "}
                    {step.actor}
                  </p>
                )}
                {step.acted_at && (
                  <p className="text-xs text-muted-foreground">
                    {formatDateTime(step.acted_at)}
                  </p>
                )}
                {Array.isArray(step.attachments) && step.attachments.length > 0 && (
                  <div className="pt-2 space-y-0.5">
                    <p className="text-[11px] font-medium text-muted-foreground/70 mb-1.5">
                      {step.attachments.length} Attachment{step.attachments.length !== 1 ? "s" : ""}
                    </p>
                    {step.attachments.map((att) => (
                      <AttachmentRow key={att.id} att={att} onFilePreview={onFilePreview} />
                    ))}
                  </div>
                )}
              </div>
            </div>
          );
        })}
      </div>

      {workflowDuration?.total_human && (
        <div className="flex items-center gap-1.5 pt-5 mt-1 border-t border-border/50">
          <Clock className="h-3.5 w-3.5 text-muted-foreground/60 flex-shrink-0" />
          <span className="text-xs text-muted-foreground">Total processing time:</span>
          <span className="text-xs font-semibold text-foreground">{workflowDuration.total_human}</span>
        </div>
      )}
    </div>
    </div>
  );
};
