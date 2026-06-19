export function uid(prefix = "id"): string {
  try {
    // Modern browsers
    if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
      // @ts-ignore - TS is fine here; runtime guards exist
      return `${prefix}-${crypto.randomUUID()}`;
    }
  } catch {}
  // Fallback (very low collision risk in-app scale)
  return `${prefix}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
}