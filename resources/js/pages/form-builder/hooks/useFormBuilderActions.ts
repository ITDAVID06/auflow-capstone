import { useState } from "react";
import { FormBuilderState } from "../types/formBuilderTypes";
import { saveFormApi, updateFormApi } from "../api/formBuilderApi";
import { router } from "@inertiajs/react";
import { toast } from "sonner";
import { isDateFieldType, isSelectFieldType } from "../config/fieldTypeRegistry";
import { slugify, normalizeMetaOption } from "../utils/formBuilderUtils";

export function useFormBuilderActions(
  form: FormBuilderState,
  setForm: (form: FormBuilderState) => void,
  setSaving?: (val: boolean) => void
) {
  const [error, setError] = useState<string | null>(null);

  const validateForm = (): string | null => {
    if (!form.form_name.trim()) return "Form name is required.";
    if (form.fields.length === 0) return "Form must have at least one field.";
    if (form.fields.some((f) => !f.label.trim() || !f.field_name.trim())) {
      return "All fields must have a label and field name.";
    }
    return null;
  };

  const sanitizeFields = () => {
    return form.fields.map((f, index) => {
      // Normalize date_mode for date fields; slots force single
      const isDate = isDateFieldType(f.data_type);
      const requestedMode: "single" | "range" =
        ((f as any).date_mode === "range" ? "range" : "single");
      const normalizedDateMode: "single" | "range" | undefined =
        isDate ? (f.use_slots ? "single" : requestedMode) : undefined;

      // If SELECT: force simple options (no quantities/text)
      if (isSelectFieldType(f.data_type)) {
        const optionsFromMeta =
          Array.isArray((f as any).options_meta) && (f as any).options_meta.length > 0
            ? (f as any).options_meta
                .map((o: any) => String(o?.label ?? "").trim())
                .filter(Boolean)
            : undefined;

        const slotsActive = f.use_slots ?? false;
        return {
          ...f,
          field_order: index,
          form_id: form.id ?? undefined,
          use_slots: slotsActive,
          require_facility: slotsActive ? (f.require_facility ?? false) : false,
          // date_mode is undefined on non-date fields and will be dropped from JSON
          date_mode: normalizedDateMode,
          is_publicly_verifiable: f.is_publicly_verifiable ?? true,
          is_sensitive: f.is_sensitive ?? false,
          options: Array.isArray(f.options) && f.options.length > 0 ? f.options : (optionsFromMeta ?? []),
          options_meta: undefined, // DROP meta for select
        };
      }

      // For radio/checkbox: keep meta if present
      const hasMeta =
        Array.isArray((f as any).options_meta) && (f as any).options_meta.length > 0;
      const normalizedMeta = hasMeta
        ? (f as any).options_meta.map((o: any) => normalizeMetaOption(o))
        : undefined;

      const slotsActive = f.use_slots ?? false;
      return {
        ...f,
        field_order: index,
        form_id: form.id ?? undefined,
        use_slots: slotsActive,
        require_facility: slotsActive ? (f.require_facility ?? false) : false,
        date_mode: normalizedDateMode, // present only for date fields
        is_publicly_verifiable: f.is_publicly_verifiable ?? true,
        is_sensitive: f.is_sensitive ?? false,
        ...(hasMeta
          ? {
              options_meta: normalizedMeta,
              options: [], // single source of truth in meta mode
            }
          : {
              options: Array.isArray(f.options) ? f.options : [],
              options_meta: undefined,
            }),
      };
    });
  };

  const save = async (): Promise<number | null> => {
    const errorMsg = validateForm();
    if (errorMsg) {
      setError(errorMsg);
      toast.error(errorMsg);
      return null;
    }

    const payload: any = {
      ...form,
      form_category_id: form.form_category_id || null,
      fields: sanitizeFields(),
      permissions: form.permissions ?? [],
      submission_limit:
        form.submission_limit === "" || form.submission_limit === undefined
          ? null
          : parseInt(form.submission_limit as unknown as string, 10) || null,
    };

    delete (payload as any).form_code;
    delete (payload as any).form_type;

    setSaving?.(true);
    setError(null);

    try {
      if (form.id) {
        await updateFormApi(form.id, payload);
        toast.success("Form updated successfully!");
        router.visit("/admin/forms");
        return form.id;
      } else {
        const response = await saveFormApi(payload);
        const newId = response?.data?.id;

        if (newId) {
          setForm({ ...form, id: newId });
          toast.success("Form created successfully!");
          return newId;
        } else {
          toast.error("Failed to retrieve form ID after save.");
          return null;
        }
      }
    } catch (e: any) {
      const msg =
        e?.response?.data?.message ||
        e?.message ||
        "An unknown error occurred during save.";
      setError(msg);
      toast.error(msg);
      return null;
    } finally {
      setSaving?.(false);
    }
  };

  return { error, save };
}
