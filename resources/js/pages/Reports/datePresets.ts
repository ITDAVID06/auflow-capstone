import {
  format,
  startOfMonth,
  endOfMonth,
  startOfQuarter,
  endOfQuarter,
  subDays,
} from "date-fns";

export type DatePreset = "last7" | "last30" | "this_month" | "this_quarter";

export interface DatePresetRange {
  date_from: string;
  date_to: string;
}

/**
 * Compute a date range for a given preset, formatted as YYYY-MM-DD strings.
 *
 * @param preset  - The preset key to compute.
 * @param now     - Reference date (defaults to today). Inject for deterministic tests.
 */
export const computePresetDateRange = (
  preset: DatePreset,
  now: Date = new Date(),
): DatePresetRange => {
  const fmt = (d: Date) => format(d, "yyyy-MM-dd");

  switch (preset) {
    case "last7":
      return { date_from: fmt(subDays(now, 6)), date_to: fmt(now) };

    case "last30":
      return { date_from: fmt(subDays(now, 29)), date_to: fmt(now) };

    case "this_month":
      return { date_from: fmt(startOfMonth(now)), date_to: fmt(endOfMonth(now)) };

    case "this_quarter":
      return { date_from: fmt(startOfQuarter(now)), date_to: fmt(endOfQuarter(now)) };
  }
};
