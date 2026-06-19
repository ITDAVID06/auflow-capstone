import { describe, expect, it } from "vitest";
import { computePresetDateRange } from "../datePresets";

/**
 * Date preset tests use a fixed reference date of 2024-03-15 to keep assertions deterministic.
 * computePresetDateRange accepts an optional `now` parameter for testability.
 */
const REFERENCE_DATE = new Date("2024-03-15T12:00:00Z");

describe("computePresetDateRange", () => {
  it("last7: returns today and 6 days ago", () => {
    const { date_from, date_to } = computePresetDateRange("last7", REFERENCE_DATE);
    expect(date_to).toBe("2024-03-15");
    expect(date_from).toBe("2024-03-09");
  });

  it("last30: returns today and 29 days ago", () => {
    const { date_from, date_to } = computePresetDateRange("last30", REFERENCE_DATE);
    expect(date_to).toBe("2024-03-15");
    expect(date_from).toBe("2024-02-15");
  });

  it("this_month: returns the first and last day of the current month", () => {
    const { date_from, date_to } = computePresetDateRange("this_month", REFERENCE_DATE);
    expect(date_from).toBe("2024-03-01");
    expect(date_to).toBe("2024-03-31");
  });

  it("this_quarter: returns correct start and end for Q1", () => {
    const { date_from, date_to } = computePresetDateRange("this_quarter", REFERENCE_DATE);
    // March 15 is in Q1 (Jan–Mar)
    expect(date_from).toBe("2024-01-01");
    expect(date_to).toBe("2024-03-31");
  });

  it("this_quarter: returns correct start and end for Q3", () => {
    const augustRef = new Date("2024-08-10T12:00:00Z");
    const { date_from, date_to } = computePresetDateRange("this_quarter", augustRef);
    // August is in Q3 (Jul–Sep)
    expect(date_from).toBe("2024-07-01");
    expect(date_to).toBe("2024-09-30");
  });

  it("returns YYYY-MM-DD formatted strings", () => {
    const { date_from, date_to } = computePresetDateRange("last7", REFERENCE_DATE);
    expect(date_from).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(date_to).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });
});
