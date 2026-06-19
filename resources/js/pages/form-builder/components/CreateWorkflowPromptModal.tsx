import React, { useState } from "react";

import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Workflow, XCircle } from "lucide-react";

interface CreateWorkflowPromptModalProps {
  open: boolean;
  onClose: () => void;
  onConfirm: (formId: number) => void; // Parent handles redirect
  formId: number;
}

export default function CreateWorkflowPromptModal({
  open,
  onClose,
  onConfirm,
  formId,
}: CreateWorkflowPromptModalProps) {
  const [submitting, setSubmitting] = useState(false);

  const handleYes = async () => {
    if (submitting) return;
    setSubmitting(true);

    try {
      onConfirm(formId);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-md border border-border/70 bg-card shadow-xl">
        <DialogHeader className="space-y-3 text-center">
          <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
            <Workflow className="h-5 w-5 text-primary" />
          </div>
          <DialogTitle className="text-xl font-semibold tracking-tight">
            Create a Workflow?
          </DialogTitle>
          <DialogDescription className="text-sm leading-relaxed text-muted-foreground">
            This form doesn’t have an associated workflow yet. Would you like to
            set one up now?
          </DialogDescription>
        </DialogHeader>

        <DialogFooter className="mt-5 flex justify-end gap-2">
          <Button
            variant="outline"
            onClick={onClose}
            className="h-9 rounded-md px-3"
            disabled={submitting}
          >
            <XCircle className="mr-1.5 h-4 w-4" />
            No
          </Button>
          <Button
            onClick={handleYes}
            disabled={submitting}
            className="h-9 rounded-md px-4"
          >
            {submitting ? "Opening…" : "Yes, Create Workflow"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
