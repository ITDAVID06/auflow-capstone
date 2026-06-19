import { useEffect } from "react";
import { usePage } from "@inertiajs/react";
import { toast } from "sonner";

type Flash = { success?: string | null; error?: string | null };
type SubmissionPending = {
  status?: string;
  message?: string;
  reference?: string;
};

export function useFlashToast() {
  const { flash } = usePage<{ flash?: Flash & { submission_pending?: SubmissionPending | null } }>().props as {
    flash?: Flash & { submission_pending?: SubmissionPending | null };
  };

  useEffect(() => {
    if (!flash) return;
    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);

    const pending = flash.submission_pending;
    if (pending?.status === "pending") {
      const description = pending.reference
        ? `${pending.message ?? "Your submission is being processed."} Reference: ${pending.reference}`
        : pending.message ?? "Your submission is being processed.";

      toast.info("Submission queued", { description });
    }
  }, [flash]);
}
