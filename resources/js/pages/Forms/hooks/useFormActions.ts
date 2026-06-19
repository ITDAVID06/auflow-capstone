import { router } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import { Form, FormFieldItem } from "../types/form.types";

export function useFormActions() {
  const handleRevision = async (id: number) => {
    if (confirm("Create a new revision of this form?\n\n• Previous revisions and their workflows will be archived\n• The workflow configuration will be carried forward as a Draft\n• The new revision will start as Inactive")) {
      await router.post(`/admin/forms/${id}/revise`);
    }
  };

  const handleDuplicate = async (id: number) => {
    if (confirm("Duplicate this form to create a new copy? Only the fields will be copied, and the new form will start as Inactive.")) {
      await router.post(`/admin/forms/${id}/duplicate`);
    }
  };

  const handleArchive = async (form: Form) => {
    if (form.status === "Archived") return;
    if (!confirm("Archive this form? This will hide it from users but keep all data.")) return;
    await router.patch(`/admin/forms/${form.id}/archive`, {}, { preserveScroll: true });
  };

  const handleStatusChange = async (
    form: Form,
    next: "Active" | "Inactive",
    onPendingLock?: (data: { id: number; prevLocked: boolean; targetStatus: "Active" | "Inactive" }) => void
  ) => {
    // Show warning when setting form to Inactive
    if (next === "Inactive" && form.status === "Active") {
      const message =
        "⚠️ Setting this form to Inactive will automatically:\n\n" +
        "• Set all associated Active workflows to Draft\n" +
        "• Lock this form from further editing\n\n" +
        "Do you want to continue?";

      if (!confirm(message)) {
        return;
      }
    }

    if (onPendingLock) {
      onPendingLock({ id: form.id, prevLocked: !!form.is_locked, targetStatus: next });
    }

    router.patch(
      `/forms/${form.id}/status`,
      { status: next },
      {
        preserveScroll: true,
        onSuccess: () => {
          if (next === "Inactive") {
            toast.success("Form set to Inactive. Associated workflows updated to Draft.");
          }
          router.reload({ only: ["forms"] });
        }
      }
    );
  };

  const updateVisibility = async (formId: number, permissionId: string | null) => {
    try {
      await axios.patch(`/admin/forms/${formId}/visibility`, { permission_id: permissionId || null });
      toast.success("Visibility updated");
      router.reload({ only: ["forms"] });
    } catch {
      toast.error("Failed to update visibility");
    }
  };

  const openViewForm = async (formId: number): Promise<FormFieldItem[] | null> => {
    try {
      const response = await axios.get<{ fields: FormFieldItem[] }>(`/admin/forms/${formId}/view`);
      return response.data.fields;
    } catch (error: unknown) {
      const responseStatus = axios.isAxiosError(error) ? error.response?.status : undefined;

      console.error("Failed to load form fields", error);

      // Better error messages based on status code
      if (responseStatus === 403) {
        toast.error("You don't have permission to view this form. It may be Inactive or restricted.");
      } else if (responseStatus === 404) {
        toast.error("Form not found.");
      } else {
        toast.error("Failed to load form preview. Please try again.");
      }

      return null;
    }
  };

  return {
    handleRevision,
    handleDuplicate,
    handleArchive,
    handleStatusChange,
    updateVisibility,
    openViewForm,
  };
}
