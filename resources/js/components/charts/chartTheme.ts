// Tailwind-ish palette you already use
export const palette = {
  primary: "#6366f1",   // indigo-500
  success: "#10b981",   // emerald-500
  warning: "#f59e0b",   // amber-500
  info: "#3b82f6",      // blue-500
  fg: "#6b7280",        // gray-500
  grid: "rgba(148,163,184,0.25)", // slate-400/25
};

export const formatHours = (totalHours: number): string => {
  const d = Math.floor(totalHours / 24);
  const h = Math.floor(totalHours % 24);
  const m = Math.floor((totalHours % 1) * 60);
  if (d > 0) return `${d}d ${h}h ${m}m`;
  if (h > 0) return `${h}h ${m}m`;
  return `${m}m`;
};

export const axisTick = {
  fontSize: 12,
  fill: palette.fg,
};

export const tooltipBox =
  "rounded-lg border bg-popover text-popover-foreground shadow-md px-3 py-2";
