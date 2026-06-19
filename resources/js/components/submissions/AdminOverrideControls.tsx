import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import { AlertCircle, CheckCircle, XCircle } from "lucide-react";

interface AdminOverrideControlsProps {
  progressId: number;
  canApprove: boolean;
  canReject: boolean;
  onActionStart?: () => void;
  onActionComplete?: () => void;
}

export default function AdminOverrideControls({
  progressId,
  canApprove,
  canReject,
  onActionStart,
  onActionComplete,
}: AdminOverrideControlsProps) {
  const [showApprove, setShowApprove] = useState(false);
  const [showReject, setShowReject] = useState(false);
  const [comment, setComment] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const handleAction = async (action: "approve" | "reject") => {
    if (submitting) return;

    setSubmitting(true);
    onActionStart?.();

    try {
      await new Promise<void>((resolve, reject) => {
        router.put(
          `/admin/submissions/${progressId}/${action}`,
          { comment: comment.trim() || "" },
          {
            preserveScroll: true,
            onSuccess: () => {
              toast.success(
                action === "approve"
                  ? "✅ Approved and snapshot created"
                  : "❌ Rejected and snapshot created"
              );
              setComment("");
              setShowApprove(false);
              setShowReject(false);
              router.reload({ only: ["submission", "canAct"] });
              resolve();
            },
            onError: (errors) => {
              console.error(`${action} error:`, errors);
              const errorMsg = Object.values(errors)[0] as string;
              toast.error(errorMsg || `Failed to ${action} submission`);
              reject(new Error(errorMsg));
            },
            onFinish: () => {
              setSubmitting(false);
              onActionComplete?.();
            },
          }
        );
      });
    } catch (error) {
      console.error("Action error:", error);
      setSubmitting(false);
      onActionComplete?.();
    }
  };

  return (
    <>
      {/* Action Buttons */}
      <div className="bg-card border border-border rounded-lg shadow-sm overflow-hidden">
        <div className="bg-muted/30 px-5 py-4 border-b border-border">
          <h2 className="text-sm font-semibold tracking-wide text-foreground flex items-center gap-2">
            <AlertCircle className="h-4 w-4 text-amber-500" />
            Admin Override Actions
          </h2>
          <p className="text-xs text-muted-foreground mt-1">
            Override workflow with admin privileges
          </p>
        </div>

        <div className="p-5 space-y-3">
          {canApprove && (
            <Button
              onClick={() => setShowApprove(true)}
              disabled={submitting}
              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              <CheckCircle className="h-4 w-4 mr-2" />
              Admin Approve
            </Button>
          )}

          {canReject && (
            <Button
              onClick={() => setShowReject(true)}
              disabled={submitting}
              variant="destructive"
              className="w-full"
            >
              <XCircle className="h-4 w-4 mr-2" />
              Admin Reject
            </Button>
          )}

          {!canApprove && !canReject && (
            <p className="text-sm text-muted-foreground text-center py-4">
              No override actions available for this submission
            </p>
          )}
        </div>
      </div>

      {/* Approve Dialog */}
      <Dialog open={showApprove} onOpenChange={setShowApprove}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <CheckCircle className="h-5 w-5 text-emerald-600" />
              Admin Approval Override
            </DialogTitle>
            <DialogDescription>
              You are about to approve this submission with admin privileges, overriding the
              normal workflow. A verification snapshot will be created.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="approve-comment">
                Comment <span className="text-muted-foreground">(Optional)</span>
              </Label>
              <Textarea
                id="approve-comment"
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder="Add a comment explaining the override reason..."
                rows={4}
                className="resize-none"
              />
            </div>

            <div className="rounded-lg bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 p-3">
              <p className="text-xs text-amber-800 dark:text-amber-200 flex items-start gap-2">
                <AlertCircle className="h-4 w-4 mt-0.5 flex-shrink-0" />
                <span>
                  This action will immediately approve the submission and create an immutable
                  snapshot, bypassing any pending workflow steps.
                </span>
              </p>
            </div>
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              variant="outline"
              onClick={() => {
                setShowApprove(false);
                setComment("");
              }}
              disabled={submitting}
            >
              Cancel
            </Button>
            <Button
              onClick={() => handleAction("approve")}
              disabled={submitting}
              className="bg-emerald-600 hover:bg-emerald-700"
            >
              {submitting ? "Approving..." : "Confirm Approval"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Reject Dialog */}
      <Dialog open={showReject} onOpenChange={setShowReject}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <XCircle className="h-5 w-5 text-red-600" />
              Admin Rejection Override
            </DialogTitle>
            <DialogDescription>
              You are about to reject this submission with admin privileges, overriding the
              normal workflow. A verification snapshot will be created.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="reject-comment">
                Reason for Rejection <span className="text-red-500">*</span>
              </Label>
              <Textarea
                id="reject-comment"
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder="Explain why this submission is being rejected..."
                rows={4}
                className="resize-none"
                required
              />
              <p className="text-xs text-muted-foreground">
                A reason is recommended for rejection to help the submitter understand the decision.
              </p>
            </div>

            <div className="rounded-lg bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 p-3">
              <p className="text-xs text-red-800 dark:text-red-200 flex items-start gap-2">
                <AlertCircle className="h-4 w-4 mt-0.5 flex-shrink-0" />
                <span>
                  This action will immediately reject the submission and create an immutable
                  snapshot, bypassing any pending workflow steps.
                </span>
              </p>
            </div>
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              variant="outline"
              onClick={() => {
                setShowReject(false);
                setComment("");
              }}
              disabled={submitting}
            >
              Cancel
            </Button>
            <Button
              onClick={() => handleAction("reject")}
              disabled={submitting}
              variant="destructive"
            >
              {submitting ? "Rejecting..." : "Confirm Rejection"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
