import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { useState, useRef } from "react";
import { toast } from "sonner";
import { Upload, X, FileText, CheckCircle2, Loader2 } from "lucide-react";

interface ApproveModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  progressId: number;
  onApprove: (progressId: number, comment?: string, files?: File[]) => Promise<void> | void;
}

export default function ApproveModal({
  open,
  onOpenChange,
  progressId,
  onApprove,
}: ApproveModalProps) {
  const [comment, setComment] = useState("");
  const [submitting, setSubmitting] = useState(false);
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

  const handleSubmit = async () => {
    if (submitting) return;
    setSubmitting(true);
    try {
      await onApprove(progressId, comment, files);
      onOpenChange(false);
      setComment("");
      setFiles([]);
      if (inputRef.current) inputRef.current.value = "";
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto" aria-describedby="approve-dialog-description">
        <DialogHeader className="space-y-2 pb-3 border-b">
          <div className="flex items-center gap-3">
            <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-muted/50">
              <CheckCircle2 className="w-5 h-5 text-muted-foreground" />
            </div>
            <div>
              <DialogTitle className="text-xl font-bold text-foreground">Approve Request</DialogTitle>
              <p id="approve-dialog-description" className="text-sm text-muted-foreground mt-0.5">
                Confirm approval and optionally provide feedback
              </p>
            </div>
          </div>
        </DialogHeader>

        <div className="space-y-5 pt-4">
          <div className="rounded-md bg-muted/50 border border-border/50 p-3">
            <div className="flex gap-2">
              <svg className="h-4 w-4 text-muted-foreground/60 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p className="text-sm text-muted-foreground leading-relaxed">
                You are about to approve this request. This action will move the submission forward in the workflow.
              </p>
            </div>
          </div>

          {/* Comment Section */}
          <div className="space-y-2">
            <Label htmlFor="approve-comment" className="text-sm font-semibold">
              Approval Comment
              <span className="text-muted-foreground font-normal ml-1">(Optional)</span>
            </Label>
            <Textarea
              id="approve-comment"
              placeholder="Type your comment here..."
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              rows={4}
              maxLength={2000}
              autoFocus
              className="
                resize-none
                border border-border
                rounded-md
                motion-safe:transition-colors
              "
              aria-label="Approval comment"
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
                id="approve-files-input"
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
                  <div className="flex items-center justify-center w-12 h-12 rounded-lg bg-muted/50">
                    <Upload className="h-6 w-6 text-muted-foreground/60" />
                  </div>
                  <div className="text-center">
                    <p className="text-sm font-medium text-primary mb-1">Click to upload files</p>
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
                bg-emerald-600 
                hover:bg-emerald-700
                text-white font-medium
              "
              onClick={handleSubmit}
              disabled={submitting}
            >
              {submitting ? (
                <div className="flex items-center gap-2">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  <span>Approving...</span>
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4" />
                  <span>Approve Request</span>
                </div>
              )}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}