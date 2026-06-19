import { useRef, useEffect, useState, useCallback } from "react";
import axios from "axios";
import type { FormBuilderState } from "@/types/form";

type AutoSaveStatus = "idle" | "saving" | "saved" | "error";

const DEBOUNCE_MS = 3_000;

/**
 * Auto-saves the form builder draft to the server after a debounced delay.
 *
 * Only activates for already-persisted forms (form.id exists).
 * Stores the full form state as draft_data via PUT /forms/{id}/draft.
 */
export function useAutoSave(form: FormBuilderState) {
  const [status, setStatus] = useState<AutoSaveStatus>("idle");
  const [lastSaved, setLastSaved] = useState<Date | null>(null);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const prevFormRef = useRef<string>("");
  const abortRef = useRef<AbortController | null>(null);

  // Stable serializer so we only trigger on real changes
  const serialize = useCallback(
    () =>
      JSON.stringify({
        form_name: form.form_name,
        description: form.description,
        fields: form.fields,
        email_notifications: form.email_notifications,
        submission_limit: form.submission_limit,
        permissions: form.permissions,
        form_category_id: form.form_category_id,
      }),
    [form]
  );

  useEffect(() => {
    // Only auto-save existing forms
    if (!form.id) return;

    const current = serialize();

    // Skip if nothing changed
    if (current === prevFormRef.current) return;
    prevFormRef.current = current;

    // Clear existing timeout
    if (timeoutRef.current) clearTimeout(timeoutRef.current);

    setStatus("idle");

    timeoutRef.current = setTimeout(async () => {
      // Abort any in-flight save
      abortRef.current?.abort();
      abortRef.current = new AbortController();

      setStatus("saving");

      try {
        await axios.put(
          `/forms/${form.id}/draft`,
          { draft_data: JSON.parse(current) },
          { signal: abortRef.current.signal }
        );
        setStatus("saved");
        setLastSaved(new Date());
      } catch (err: unknown) {
        if (axios.isCancel(err)) return;
        setStatus("error");
        console.error("[useAutoSave] draft save failed:", err);
      }
    }, DEBOUNCE_MS);

    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
    };
  }, [form.id, serialize]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (timeoutRef.current) clearTimeout(timeoutRef.current);
      abortRef.current?.abort();
    };
  }, []);

  return { status, lastSaved };
}
