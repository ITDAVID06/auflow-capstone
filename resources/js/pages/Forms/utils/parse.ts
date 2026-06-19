import type {
  FormField,
  MultiMetaSelection,
  SingleMetaSelection,
} from "@/types/form";
import { normalizeMetaOption } from "./meta";

const MAX_PARSE_ATTEMPTS = 6;

const deepParseJSON = (value: unknown): unknown => {
  let parsed: unknown = value;

  for (let i = 0; i < MAX_PARSE_ATTEMPTS; i++) {
    if (typeof parsed === "string") {
      const trimmed = parsed.trim();

      if (trimmed.startsWith('["') && trimmed.endsWith('"]')) {
        try {
          const temp = JSON.parse(trimmed);
          if (Array.isArray(temp) && temp.length === 1 && typeof temp[0] === "string") {
            parsed = temp[0];
            continue;
          }
        } catch {
          // fall through to standard parse
        }
      }

      try {
        parsed = JSON.parse(trimmed);
        continue;
      } catch {
        break;
      }
    }

    if (Array.isArray(parsed) && parsed.length === 1 && typeof parsed[0] === "string") {
      parsed = parsed[0];
      continue;
    }

    break;
  }

  return parsed;
};

const parseMetaCheckboxValue = (raw: unknown, field: FormField): MultiMetaSelection => {
  const normalized = (field.options_meta || []).map(normalizeMetaOption);
  if (!normalized.length) return Array.isArray(raw) ? (raw as MultiMetaSelection) : [];

  const parsed = deepParseJSON(raw);

  if (!Array.isArray(parsed)) return [];

  return parsed
    .filter((item) => item && typeof item === "object")
    .map((item: any) => ({
      value: String(item.value ?? item.Value ?? ""),
      qty: item.qty ?? item.Qty,
      text: item.text ?? item.Text,
    }))
    .filter((item) => item.value);
};

const parseMetaSingleValue = (raw: unknown): SingleMetaSelection | undefined => {
  if (!raw) return undefined;

  const parsed = deepParseJSON(raw);
  if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) return undefined;

  const obj = parsed as Record<string, unknown>;
  const value = obj.value ?? obj.Value;
  if (typeof value !== "string") return undefined;

  return {
    value,
    qty:
      typeof obj.qty === "number"
        ? obj.qty
        : typeof obj.Qty === "number"
        ? obj.Qty
        : undefined,
    text:
      typeof obj.text === "string"
        ? obj.text
        : typeof obj.Text === "string"
        ? obj.Text
        : undefined,
  };
};

const parseSimpleCheckboxValue = (raw: unknown): string[] => {
  if (Array.isArray(raw)) return raw.filter((item): item is string => typeof item === "string");

  if (typeof raw === "string") {
    const trimmed = raw.trim();
    if (!trimmed) return [];

    try {
      const parsed = JSON.parse(trimmed);
      if (Array.isArray(parsed)) {
        return parsed.filter((item): item is string => typeof item === "string");
      }
    } catch {
      // fall back to comma-separated values
    }

    return trimmed.split(",").map((value) => value.trim()).filter(Boolean);
  }

  return [];
};

const formatNumberForInput = (value: unknown): string => {
  if (value === null || value === undefined || value === "") return "";
  const numeric = Number(value);
  return Number.isNaN(numeric) ? String(value ?? "") : String(numeric);
};

export const parseFieldValue = (raw: unknown, field: FormField): any => {
  if (field.data_type === "number" || field.data_type === "integer") {
    return formatNumberForInput(raw);
  }

  if (field.options_meta && field.options_meta.length) {
    if (field.data_type === "checkbox") {
      return parseMetaCheckboxValue(raw, field);
    }

    if (["radio", "select"].includes(field.data_type)) {
      return parseMetaSingleValue(raw);
    }
  }

  if (field.data_type === "checkbox") {
    return parseSimpleCheckboxValue(raw);
  }

  return raw ?? "";
};
