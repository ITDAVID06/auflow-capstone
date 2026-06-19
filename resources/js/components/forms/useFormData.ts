export type LooseValues = Record<string, any>;

/**
 * Builds FormData with the correct semantics:
 * - File -> append file
 * - Array -> append as key[] for each value
 * - boolean -> "1" / "0"
 * - null/undefined -> ""
 * - scalar -> String(value)
 */
export function buildFormData(values: LooseValues): FormData {
  const fd = new FormData();

  Object.entries(values).forEach(([key, val]) => {
    if (val instanceof File) {
      fd.append(key, val);
      return;
    }

    if (Array.isArray(val)) {
      val.forEach((v) => fd.append(`${key}[]`, v ?? ""));
      return;
    }

    if (typeof val === "boolean") {
      fd.append(key, val ? "1" : "0");
      return;
    }

    if (val === null || typeof val === "undefined") {
      fd.append(key, "");
      return;
    }

    fd.append(key, String(val));
  });

  return fd;
}
