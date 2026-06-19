import type {
  FormField,
  MultiMetaSelection,
  OptionMeta,
  SingleMetaSelection,
} from "@/types/form";

export type NormalizedMetaOption = ReturnType<typeof normalizeMetaOption>;

export const MAX_META_JSON_DEPTH = 2;

export const metaHasQty = (meta?: OptionMeta[] | null) =>
  Array.isArray(meta) && meta.some((option) => Boolean(option?.requires_qty));

export const metaHasText = (meta?: OptionMeta[] | null) =>
  Array.isArray(meta) && meta.some((option) => Boolean(option?.requires_text));

export const normalizeMetaOption = (option: OptionMeta) => ({
  label: option.label ?? "",
  value: (option.value ?? option.label ?? "").toString(),
  requires_qty: Boolean(option.requires_qty),
  qty_label: option.qty_label ?? "Qty",
  min_qty: typeof option.min_qty === "number" ? option.min_qty : 0,
  max_qty:
    option.max_qty === null || typeof option.max_qty === "undefined"
      ? null
      : Number(option.max_qty),
  step: typeof option.step === "number" ? option.step : 1,
  default_qty: typeof option.default_qty === "number" ? option.default_qty : 1,
  unit: option.unit ?? "pcs",
  requires_text: Boolean(option.requires_text),
  text_label: option.text_label ?? "Specify",
});

const parseJsonDeep = (value: unknown): unknown => {
  let current = value;

  for (let i = 0; i < MAX_META_JSON_DEPTH + 2; i++) {
    if (typeof current !== "string") {
      break;
    }

    const trimmed = current.trim();
    if (!trimmed) {
      break;
    }

    const looksJsonLike =
      trimmed.startsWith("[") ||
      trimmed.startsWith("{") ||
      (trimmed.startsWith('"') && trimmed.endsWith('"'));

    if (!looksJsonLike) {
      break;
    }

    try {
      current = JSON.parse(trimmed);
    } catch {
      break;
    }
  }

  return current;
};

const normalizeMetaRawForSubmit = (raw: unknown): unknown => {
  const parsed = parseJsonDeep(raw);

  if (Array.isArray(parsed) && parsed.length === 1 && typeof parsed[0] === "string") {
    return parseJsonDeep(parsed[0]);
  }

  return parsed;
};

export function encodeMetaForSubmit(field: FormField, raw: unknown): string {
  const anyQty = metaHasQty(field.options_meta);
  const anyText = metaHasText(field.options_meta);
  const normalizedRaw = normalizeMetaRawForSubmit(raw);

  if (anyQty || anyText) {
    try {
      if (typeof normalizedRaw === "string") {
        const reparsed = parseJsonDeep(normalizedRaw);
        if (Array.isArray(reparsed) || (reparsed !== null && typeof reparsed === "object")) {
          return JSON.stringify(reparsed);
        }

        return normalizedRaw;
      }

      return JSON.stringify(normalizedRaw ?? null);
    } catch {
      return "";
    }
  }

  if (field.data_type === "checkbox") {
    if (Array.isArray(normalizedRaw)) {
      const values = normalizedRaw
        .map((item) => {
          if (typeof item === "string") {
            return item;
          }

          if (item && typeof item === "object" && "value" in item) {
            return String((item as { value?: unknown }).value ?? "");
          }

          return "";
        })
        .filter(Boolean);
      return values.join(",");
    }

    if (typeof normalizedRaw === "string") {
      return normalizedRaw;
    }

    return "";
  }

  if (typeof normalizedRaw === "string") return normalizedRaw;

  if (normalizedRaw && typeof normalizedRaw === "object" && "value" in (normalizedRaw as SingleMetaSelection)) {
    return String((normalizedRaw as SingleMetaSelection).value ?? "");
  }

  return "";
}

export const toCompact = (value: number | undefined | null) => {
  if (typeof value !== "number" || !Number.isFinite(value)) return "";
  return Math.trunc(value) === value ? String(value) : String(value);
};

export const resolveMetaLookup = (meta: OptionMeta[] | null | undefined) =>
  Object.fromEntries(
    (meta || [])
      .map((item) => normalizeMetaOption(item))
      .map((option) => [option.value, option.label])
  );

export const ensureSingleMetaSelection = (
  selection: SingleMetaSelection | undefined,
  option: NormalizedMetaOption
): SingleMetaSelection => ({
  value: option.value,
  qty: option.requires_qty ? option.default_qty ?? 1 : undefined,
  text: option.requires_text ? "" : undefined,
});

export const updateMultiMetaSelection = (
  current: MultiMetaSelection,
  value: string,
  updater: (existing: MultiMetaSelection[number]) => MultiMetaSelection[number]
) =>
  current.map((item) => (item.value === value ? updater(item) : item));
