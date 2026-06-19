import { useMemo } from "react";
import type { FormField, FormFieldValue } from "@/types/form";
import { isFieldVisible } from "@/pages/Forms/utils/fieldConditions";

/**
 * Given the current list of form fields and a key→value map of field data,
 * returns a Set of field_name strings that should be **visible**.
 *
 * Delegates to isFieldVisible (fieldConditions.ts) — the shared utility that
 * mirrors the backend FieldConditionEvaluator logic exactly.
 */
export function useConditionalFields(
  fields: FormField[],
  formData: Record<string, FormFieldValue>
): Set<string> {
  return useMemo(() => {
    const visible = new Set<string>();

    for (const field of fields) {
      if (isFieldVisible(field, formData)) {
        visible.add(field.field_name);
      }
    }

    return visible;
  }, [fields, formData]);
}
