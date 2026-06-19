import { useCallback, useEffect, useRef, useState } from "react";
import { ReportFiltersState } from "../types";
import { buildReportQueryParams } from "../queryBuilder";

export interface TrendPoint {
  date: string;
  count: number;
}

export interface StatusPoint {
  status: string;
  count: number;
}

export interface FieldPoint {
  value: string;
  count: number;
}

export interface CategoricalField {
  key: string;
  label: string;
}

export interface ChartData {
  trend: TrendPoint[];
  status_breakdown: StatusPoint[];
  field_distribution: FieldPoint[] | null;
  categorical_fields: CategoricalField[];
}

export function useChartData(
  filters: ReportFiltersState | null,
  normalizedSubmitter: string | null,
  fieldKey: string | null,
) {
  const [chartData, setChartData] = useState<ChartData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const fetchChartData = useCallback(async () => {
    if (!filters?.form_id) {
      setChartData(null);
      return;
    }

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    setError(null);

    try {
      const exportFilters: ReportFiltersState = {
        ...filters,
        submitter: normalizedSubmitter ?? null,
        page: 1,
      };

      const params: Record<string, unknown> = buildReportQueryParams(exportFilters);
      if (fieldKey) {
        params.field_key = fieldKey;
      }

      // Build URL via Ziggy route helper
      const url = route("reports.chart-data", params);
      const response = await fetch(url, {
        credentials: "same-origin",
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const json = (await response.json()) as ChartData;
      setChartData(json);
    } catch (err) {
      if ((err as Error).name === "AbortError") return;
      setError("Could not load chart data");
    } finally {
      setLoading(false);
    }
  }, [filters, normalizedSubmitter, fieldKey]);

  useEffect(() => {
    void fetchChartData();
    return () => {
      abortRef.current?.abort();
    };
  }, [fetchChartData]);

  return { chartData, loading, error };
}
