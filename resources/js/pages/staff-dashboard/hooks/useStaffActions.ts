import axios from "axios";
import { toast } from "sonner";

export function useStaffActions() {
  const pastTenseMap = { approve: "approved", reject: "rejected" } as const;

  const actOnSubmission = async (
    progressId: number,
    action: "approve" | "reject",
    comment?: string,
    files?: File[]
  ): Promise<{ finalApproved: boolean; message: string }> => {
    const fd = new FormData();
    fd.append("_method", "PUT");
    fd.append("comment", comment ?? "");
    (files ?? []).forEach((f, i) => fd.append(`attachments[${i}]`, f));

    try {
      const response = await axios.post(route(`staff-dashboard.progress.${action}`, { id: progressId }), fd, {
        headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        withCredentials: true,
      });

      const finalApproved = Boolean(response.data?.final_approved);
      const message = String(response.data?.message ?? `Successfully ${pastTenseMap[action]}.`);

      if (finalApproved) {
        toast.success("This request has been fully approved and the submitter has been notified.");
      } else {
        toast.success(message);
      }

      return { finalApproved, message };
    } catch (error: unknown) {
      const typedError = error as {
        response?: {
          data?: {
            errors?: { comment?: string[] };
            error?: string;
            message?: string;
          };
        };
      };
      const msg =
        typedError.response?.data?.errors?.comment?.[0] ||
        typedError.response?.data?.error ||
        typedError.response?.data?.message ||
        `Failed to ${action}.`;
      toast.error(msg);
      throw error;
    }
  };

  return { actOnSubmission };
}
