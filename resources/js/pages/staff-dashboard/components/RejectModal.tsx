import React, { useRef, useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";
import { Upload, X, FileText, XCircle, Loader2 } from "lucide-react";

interface RejectModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  progressId: number;
  onReject: (progressId: number, comment: string, files?: File[]) => Promise<void> | void;
}

export default function RejectModal({
  open,
  onOpenChange,
  progressId,
  onReject,
}: RejectModalProps) {
  const [comment, setComment] = useState("");
  const trimmedComment = comment.trim();
  const [submitting, setSubmitting] = useState(false);
  const [submitAttempted, setSubmitAttempted] = useState(false);
  const [files, setFiles] = useState<File[]>([]);
  const inputRef = useRef<HTMLInputElement | null>(null);

  const handleFiles = (list: FileList | null) => {
    if (!list) return;
    const next = Array.from(list);
    const total = files.length + next.length;
    if (total > 5) {
      toast.error("You can upload up to 5 files.");
      return;
    }
    const allowed = [
      "application/pdf",
      "image/jpeg",
      "image/png",
      "image/webp",
      "application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      "application/vnd.ms-excel",
      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    ];
    const filtered = next.filter(f => allowed.includes(f.type) || /\.(pdf|jpe?g|png|webp|docx?|xlsx?)$/i.test(f.name));
    setFiles(prev => [...prev, ...filtered]);
  };

  const removeAt = (idx: number) => setFiles(prev => prev.filter((_, i) => i !== idx));

  const handleReject = async () => {
    if (submitting) return;
    if (trimmedComment.length === 0) {
      setSubmitAttempted(true);
      return;
    }
    setSubmitting(true);
    try {
      await onReject(progressId, trimmedComment, files);
      setComment("");
      setFiles([]);
      if (inputRef.current) inputRef.current.value = "";
      onOpenChange(false);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto" aria-describedby="reject-dialog-description">
        <DialogHeader className="space-y-2 pb-3 border-b">
          <div className="flex items-center gap-3">
            <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-destructive/10">
              <XCircle className="w-5 h-5 text-destructive" />
            </div>
            <div>
              <DialogTitle className="text-xl font-bold text-foreground">Reject Request</DialogTitle>
              <p id="reject-dialog-description" className="text-sm text-muted-foreground mt-1">
                Provide feedback and decline this submission
              </p>
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-5 pt-4">
          <div className="rounded-md bg-muted/50 border border-border/50 p-3">
            <div className="flex gap-2">
              <svg className="h-4 w-4 text-muted-foreground/60 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <p className="text-sm text-muted-foreground">
                You are about to reject this request. Please provide clear feedback to help the submitter understand what needs to be corrected.
              </p>
            </div>
          </div>

          {/* Comment Section */}
          <div className="space-y-2">
            <Label htmlFor="reject-comment" className="text-sm font-semibold">
              Reason for Rejection
              <span className="text-muted-foreground font-normal ml-1">(Required)</span>
            </Label>
            <Textarea
              id="reject-comment"
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              placeholder="Type your comment here..."
              maxLength={2000}
              rows={5}
              autoFocus
              className="
                resize-none
                border border-border
                rounded-md
                motion-safe:transition-colors
              "
              aria-label="Rejection comment"
            />
            <div className="flex items-center justify-between text-xs">
              <span className="text-muted-foreground">
                {comment.length}/2,000 characters
              </span>
              {comment.length > 1800 && (
                <span className="text-amber-600 dark:text-amber-400">
                  {2000 - comment.length} remaining
                </span>
              )}
            </div>
            {submitAttempted && trimmedComment.length === 0 ? (
              <p className="text-xs text-destructive">Please add a comment before rejecting this request.</p>
            ) : null}
          </div>

          {/* File Upload Section */}
          <div className="space-y-2">
            <Label className="text-sm font-semibold">
              Supporting Documents
              <span className="text-muted-foreground font-normal ml-1">(Optional)</span>
            </Label>
            
            <div className="space-y-2">
              <input
                ref={inputRef}
                type="file"
                multiple
                className="hidden"
                id="reject-files-input"
                onChange={(e) => handleFiles(e.target.files)}
                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
              />
              
              <button
                type="button"
                onClick={() => inputRef.current?.click()}
                className="
                  group relative w-full
                  border border-dashed border-border
                  hover:border-border hover:bg-muted/30
                  rounded-md p-6
                  motion-safe:transition-colors
                  cursor-pointer
                "
              >
                <div className="flex flex-col items-center gap-3">
                  <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-destructive/10">
                    <Upload className="h-6 w-6 text-destructive/80" />
                  </div>
                  <div className="text-center">
                    <p className="text-sm font-medium text-destructive mb-1">Click to upload files</p>
                    <p className="text-xs text-muted-foreground">
                      PDF, Images (JPG, PNG, WEBP), Documents (DOC, DOCX, XLS, XLSX)
                    </p>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      Maximum 5 files
                    </p>
                  </div>
                </div>
              </button>
            </div>

            {/* File List */}
            {files.length > 0 && (
              <div className="space-y-2 pt-2">
                <div className="flex items-center justify-between">
                  <p className="text-sm font-medium text-foreground">
                    {files.length} {files.length === 1 ? 'file' : 'files'} attached
                  </p>
                  <span className="text-xs text-muted-foreground">
                    {files.length}/5
                  </span>
                </div>
                <ul className="space-y-2">
                  {files.map((f, i) => (
                    <li
                      key={i}
                      className="
                        group flex items-center gap-3 p-3 
                        rounded-md border border-border 
                        bg-muted/30 hover:bg-muted/60
                        transition-all duration-200
                      "
                    >
                      <div className="flex items-center justify-center w-9 h-9 rounded-md bg-muted/50 flex-shrink-0">
                        <FileText className="h-4 w-4 text-muted-foreground" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium truncate">{f.name}</p>
                        <p className="text-xs text-muted-foreground">
                          {(f.size / 1024).toFixed(1)} KB
                        </p>
                      </div>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="
                          h-8 w-8 p-0 flex-shrink-0
                          hover:bg-destructive/10
                          hover:text-destructive
                        "
                        onClick={() => removeAt(i)}
                        aria-label={`Remove ${f.name}`}
                      >
                        <X className="h-4 w-4" />
                      </Button>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          {/* Action Buttons */}
          <div className="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t">
            <Button 
              variant="outline" 
              onClick={() => onOpenChange(false)} 
              disabled={submitting}
              className="min-w-[120px] h-10"
            >
              Cancel
            </Button>
            <Button
              className="
                min-w-[120px] h-10
                bg-destructive 
                hover:bg-destructive/90
                text-destructive-foreground font-medium
              "
              onClick={handleReject}
              disabled={submitting || trimmedComment.length === 0}
            >
              {submitting ? (
                <div className="flex items-center gap-2">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  <span>Rejecting...</span>
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <XCircle className="h-4 w-4" />
                  <span>Reject Request</span>
                </div>
              )}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}