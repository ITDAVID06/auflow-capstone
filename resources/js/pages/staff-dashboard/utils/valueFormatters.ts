// Utility functions for formatting submission values

export const sanitizeValue = (val: unknown): string => {
  if (val === null || val === undefined || val === "null" || val === "undefined" || val === "") {
    return "—";
  }
  return String(val);
};

const imageExts = ["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"];

export const extOf = (p: string): string => {
  const m = (p || "").split("?")[0].match(/\.([a-z0-9]+)$/i);
  return (m?.[1] || "").toLowerCase();
};

export const isPdf = (p: string): boolean => extOf(p) === "pdf";
export const isImage = (p: string): boolean => imageExts.includes(extOf(p));

export const toCompactNumber = (val: string | number): string => {
  const n = typeof val === "number" ? val : Number(String(val).trim());
  if (!Number.isFinite(n)) return String(val);
  const i = Math.trunc(n);
  return Math.abs(n - i) < 1e-9 ? String(i) : String(n);
};

export function deepJsonUnwrap(input: string): unknown {
  let cur: unknown = input;
  for (let i = 0; i < 5; i++) {
    if (typeof cur !== "string") break;
    const trimmed = cur.trim();
    if (!/^[\[{"]/.test(trimmed)) break;
    try {
      cur = JSON.parse(trimmed);
    } catch {
      break;
    }
  }
  if (Array.isArray(cur)) {
    return cur.map((x) => {
      if (typeof x === "string" && /^[\[{"]/.test(x.trim())) {
        try {
          return JSON.parse(x);
        } catch {
          return x;
        }
      }
      return x;
    });
  }
  return cur;
}

export function renderMetaAdvancedDisplay(parsed: unknown): string {
  const itemToText = (item: any): string => {
    if (!item || typeof item !== "object") {
      if (item === null || item === undefined || item === "null" || item === "undefined") return "";
      if (typeof item === "number") return toCompactNumber(item);
      return String(item ?? "");
    }
    const v = (item.value ?? item.label ?? "") as string;
    const q = item.qty as number | undefined;
    const t = item.text as string | undefined;

    const hasValue = Boolean(v && v !== "null" && v !== "undefined");
    const hasText = Boolean(t && String(t).trim() !== "" && String(t).trim() !== "null" && String(t).trim() !== "undefined");
    const qty = typeof q === "number" ? ` × ${toCompactNumber(q)}` : "";
    const normalizedText = hasText ? String(t).trim() : "";

    if (hasValue && hasText) {
      return `${v}${qty} (${normalizedText})`;
    }

    if (hasValue) {
      return `${v}${qty}`;
    }

    if (hasText) {
      return normalizedText;
    }

    return "";
  };

  if (Array.isArray(parsed))
    return parsed.map(itemToText).filter(Boolean).join(", ");
  if (parsed && typeof parsed === "object") return itemToText(parsed);
  return "";
}

export const normalizeValue = (raw: unknown): string => {
  if (raw === null || raw === undefined || raw === "null" || raw === "undefined") return "";

  if (Array.isArray(raw)) {
    const adv = renderMetaAdvancedDisplay(raw);
    if (adv) return adv;
    return raw.map((v) => normalizeValue(v)).filter(Boolean).join(", ");
  }
  if (typeof raw === "object") {
    const adv = renderMetaAdvancedDisplay(raw);
    if (adv) return adv;
    return Object.values(raw as Record<string, unknown>)
      .map((v) => normalizeValue(v))
      .filter(Boolean)
      .join(", ");
  }

  if (typeof raw === "number") return toCompactNumber(raw);

  if (typeof raw === "string") {
    const s = raw.trim();
    if (s === "null" || s === "undefined" || s === "") return "";
    const normalizedValue = s.replace(/_/g, " ");

    const maybe = Number(normalizedValue);
    if (!Number.isNaN(maybe) && normalizedValue !== "") {
      if (!normalizedValue.includes(".") && /^0\d+$/.test(normalizedValue)) return normalizedValue;
      return toCompactNumber(maybe);
    }

    const unwrapped = deepJsonUnwrap(normalizedValue);
    if (unwrapped && typeof unwrapped === "object") {
      const adv = renderMetaAdvancedDisplay(unwrapped);
      if (adv) return adv;
      if (Array.isArray(unwrapped))
        return unwrapped.map((v) => normalizeValue(v)).filter(Boolean).join(", ");
    }

    if (normalizedValue === "true" || normalizedValue === "1") return "Yes";
    if (normalizedValue === "false" || normalizedValue === "0") return "No";
    return normalizedValue;
  }

  return String(raw);
};
