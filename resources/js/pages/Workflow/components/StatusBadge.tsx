import React from "react";
import axios from "axios";
import { ChevronDown } from "lucide-react";
import { toast } from "sonner";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";

type WorkflowStatus = "Active" | "Draft" | "Archived";

interface StatusBadgeProps {
  status: WorkflowStatus;
  workflow: { id: number; status: WorkflowStatus };
  onChanged?: () => void;
}

const statusConfig = {
  Active: {
    dot: "bg-emerald-500",
    text: "text-emerald-700 dark:text-emerald-400",
    bg: "bg-emerald-50 dark:bg-emerald-500/10",
  },
  Draft: {
    dot: "bg-amber-500",
    text: "text-amber-700 dark:text-amber-400",
    bg: "bg-amber-50 dark:bg-amber-500/10",
  },
  Archived: {
    dot: "bg-gray-400",
    text: "text-gray-600 dark:text-gray-400",
    bg: "bg-gray-100 dark:bg-gray-800",
  },
} as const;

export default function StatusBadge({ status, workflow, onChanged }: StatusBadgeProps) {
  const [showDraftWarning, setShowDraftWarning] = React.useState(false);
  const [submitting, setSubmitting] = React.useState(false);

  const badge = statusConfig[status] ?? statusConfig.Draft;
  const canSetDraft = status === "Active" || status === "Archived";

  const setToDraft = async (): Promise<void> => {
    try {
      setSubmitting(true);
      await axios.patch(`/workflows/${workflow.id}/draft`, { force: true }, {
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
      });
      toast.success("Workflow moved to Draft.");
      setShowDraftWarning(false);
      onChanged?.();
    } catch (error: unknown) {
      const message =
        axios.isAxiosError(error) && typeof error.response?.data?.message === "string"
          ? error.response.data.message
          : "Failed to set workflow to Draft.";
      toast.error(message);
    } finally {
      setSubmitting(false);
    }
  };

  if (!canSetDraft) {
    return (
      <span
        className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium leading-none ${badge.bg} ${badge.text}`}
      >
        <span className={`inline-block h-1.5 w-1.5 rounded-full ${badge.dot}`} />
        {status}
      </span>
    );
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <button
            type="button"
            className={`inline-flex cursor-pointer items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium leading-none transition-colors hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 ${badge.bg} ${badge.text}`}
          >
            <span className={`inline-block h-1.5 w-1.5 rounded-full ${badge.dot}`} />
            {status}
            <ChevronDown className="h-3 w-3 opacity-60" />
          </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" className="w-40">
          <DropdownMenuItem className="cursor-pointer" onClick={() => setShowDraftWarning(true)}>
            <span className="mr-2 inline-block h-2 w-2 rounded-full bg-amber-500" />
            Set to Draft
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <Dialog open={showDraftWarning} onOpenChange={setShowDraftWarning}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Set workflow to Draft?</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            Setting this workflow to Draft may require the associated form to be inactive before publishing again.
          </p>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowDraftWarning(false)}>
              Cancel
            </Button>
            <Button onClick={setToDraft} disabled={submitting}>
              {submitting ? "Processing..." : "Proceed"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}