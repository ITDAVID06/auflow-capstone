import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { Workflow } from "../types/workflow.types";

/**
 * Type guard to check if error is a record object
 */
function isErrorRecord(error: unknown): error is Record<string, unknown> {
  return typeof error === "object" && error !== null && !Array.isArray(error);
}

/**
 * Extract first error message from Inertia error object
 */
function getFirstErrorMessage(errors: unknown): string {
  if (!isErrorRecord(errors)) {
    return "An error occurred";
  }

  const values = Object.values(errors);
  if (values.length === 0) {
    return "An error occurred";
  }

  const firstError = values[0];
  
  // Handle nested error arrays (Laravel validation format)
  if (Array.isArray(firstError) && firstError.length > 0) {
    return String(firstError[0]);
  }
  
  return String(firstError);
}

export function useWorkflowActions() {
  const refreshOnly = () => router.reload({ only: ["workflows"] });

  const handleDuplicate = (id: number) => {
    router.post(
      `/workflows/${id}/duplicate`,
      {},
      {
        onSuccess: () => {
          toast.success("Duplicated as Draft and unbound. Select a form before publishing.");
          refreshOnly();
        },
        onError: (errors) => {
          const message = getFirstErrorMessage(errors);
          toast.error(message || "Failed to duplicate.");
        },
      }
    );
  };

  const handleArchive = (w: Workflow) => {
    if (String(w.status).toLowerCase() === "archived") return;

    // Show warning if workflow is Active and has a form
    if (String(w.status).toLowerCase() === "active" && w.form) {
      const message =
        "⚠️ Archiving this workflow will automatically:\n\n" +
        `• Set the associated form "${w.form.form_name}" to Inactive\n` +
        "• Lock the form from further editing\n\n" +
        "Do you want to continue?";

      if (!confirm(message)) {
        return;
      }
    }

    router.patch(
      `/workflows/${w.id}/archive`,
      {},
      {
        onSuccess: () => {
          toast.success("Workflow archived. Associated form set to Inactive.");
          refreshOnly();
        },
        onError: (errors) => {
          const message = getFirstErrorMessage(errors);
          toast.error(message || "Archiving failed.");
        },
      }
    );
  };

  const handlePublish = (id: number) => {
    router.patch(
      `/workflows/${id}/publish`,
      {},
      {
        onSuccess: () => {
          toast.success("Workflow published.");
          refreshOnly();
        },
        onError: (errors) => {
          const message = getFirstErrorMessage(errors);
          toast.error(message || "Publish failed. Check validation.");
        },
      }
    );
  };

  const handleEnable = (id: number) => {
    router.patch(
      `/workflows/${id}/enable`,
      {},
      {
        onSuccess: () => {
          toast.success("Workflow enabled. Associated form is now Active.");
          refreshOnly();
        },
        onError: (errors) => {
          const message = getFirstErrorMessage(errors);
          toast.error(message || "Enable failed.");
        },
      }
    );
  };

  return {
    handleDuplicate,
    handleArchive,
    handlePublish,
    handleEnable,
    refreshOnly,
  };
}
