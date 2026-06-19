const DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
const DATETIME_WITHOUT_TZ_PATTERN = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/;

const DISPLAY_TIMEZONE =
  import.meta.env.VITE_DISPLAY_TIMEZONE ||
  import.meta.env.VITE_APP_TIMEZONE ||
  "Asia/Manila";

function parseDateValue(value?: string | null): Date | null {
  if (!value) {
    return null;
  }

  if (DATE_ONLY_PATTERN.test(value)) {
    const [year, month, day] = value.split("-").map(Number);
    return new Date(year, month - 1, day);
  }

  const normalizedValue = DATETIME_WITHOUT_TZ_PATTERN.test(value)
    ? value.replace(" ", "T") + "Z"
    : value;

  const parsed = new Date(normalizedValue);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function formatDate(
  value?: string | null,
  options?: Intl.DateTimeFormatOptions,
  locale: string = "en-US"
): string {
  const parsed = parseDateValue(value);
  if (!parsed) {
    return "—";
  }

  return new Intl.DateTimeFormat(locale, {
    timeZone: DISPLAY_TIMEZONE,
    ...(options ?? {}),
  }).format(parsed);
}

export function formatDateTime(
  value?: string | null,
  options?: Intl.DateTimeFormatOptions,
  locale: string = "en-US"
): string {
  const parsed = parseDateValue(value);
  if (!parsed) {
    return "—";
  }

  return new Intl.DateTimeFormat(locale, {
    timeZone: DISPLAY_TIMEZONE,
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
    ...(options ?? {}),
  }).format(parsed);
}
