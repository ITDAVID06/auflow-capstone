/** Stable, URL-safe-ish key from a label. */
export function slugify(input: string): string {
  return (input || '')
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

/** Clamp numeric value within [min, max] where max may be null (no cap). */
export function clamp(value: number, min: number, max: number | null): number {
  if (Number.isNaN(value)) return min;
  const v = Math.max(value, min);
  return max == null ? v : Math.min(v, max);
}

/** Normalize a single options_meta record (safe defaults + clamping). */
export function normalizeMetaOption(o: any) {
  const label = String(o?.label ?? '').trim();
  const min_qty = Math.max(0, Number(o?.min_qty ?? 0));
  const max_qty_raw = o?.max_qty;
  const max_qty =
    max_qty_raw === '' || max_qty_raw === null || typeof max_qty_raw === 'undefined'
      ? null
      : Math.max(0, Number(max_qty_raw));
  const step = Math.max(1, Number(o?.step ?? 1));
  const default_qty_raw = Number(o?.default_qty ?? 1);
  const default_qty = clamp(default_qty_raw, min_qty, max_qty);

  return {
    label,
    value: o?.value && String(o.value).trim() !== '' ? String(o.value) : slugify(label),
    requires_qty: Boolean(o?.requires_qty ?? false),
    qty_label: String(o?.qty_label ?? 'Qty'),
    min_qty,
    max_qty,
    step,
    default_qty,
    unit: String(o?.unit ?? 'pcs'),
    requires_text: Boolean(o?.requires_text ?? false),
    text_label: String(o?.text_label ?? 'Specify'),
  };
}
