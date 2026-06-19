import React, { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, Legend,
  BarChart, Bar,
} from "recharts";
import { KpiCard } from "../components/KpiCard";
import { ChartDataResponse, ReportTab } from "../types";

const STATUS_COLORS: Record<string, string> = {
  approved: "#22c55e",
  pending:  "#f59e0b",
  rejected: "#ef4444",
  completed: "#3b82f6",
};

interface OverviewTabProps {
  formId: number;
  onNavigateToData: (dateFrom: string, dateTo: string) => void;
}

export const OverviewTab: React.FC<OverviewTabProps> = ({ formId, onNavigateToData }) => {
  const [dateFrom, setDateFrom] = useState<string>(() => {
    const d = new Date();
    d.setDate(d.getDate() - 30);
    return d.toISOString().slice(0, 10);
  });
  const [dateTo, setDateTo] = useState<string>(new Date().toISOString().slice(0, 10));
  const [fieldKey, setFieldKey] = useState<string | null>(null);
  const [data, setData] = useState<ChartDataResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  const fetchData = useCallback(() => {
    abortRef.current?.abort();
    abortRef.current = new AbortController();
    setLoading(true);
    setError(null);
    axios
      .get<ChartDataResponse>(route("reports.chart-data"), {
        params: { form_id: formId, date_from: dateFrom, date_to: dateTo, field_key: fieldKey },
        signal: abortRef.current.signal,
      })
      .then((r) => {
        setData(r.data);
        if (!fieldKey && r.data.field_distribution_column) {
          setFieldKey(r.data.field_distribution_column);
        }
      })
      .catch((e) => {
        if (!axios.isCancel(e)) setError("Could not load chart data.");
      })
      .finally(() => setLoading(false));
  }, [formId, dateFrom, dateTo, fieldKey]);

  useEffect(() => {
    fetchData();
    return () => abortRef.current?.abort();
  }, [fetchData]);

  return (
    <div className="space-y-6">
      {/* Date range controls */}
      <div className="flex flex-wrap items-end gap-3">
        <div className="flex flex-col gap-1">
          <Label className="text-xs text-muted-foreground">From</Label>
          <Input type="date" className="w-36 h-8 text-sm" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
        </div>
        <div className="flex flex-col gap-1">
          <Label className="text-xs text-muted-foreground">To</Label>
          <Input type="date" className="w-36 h-8 text-sm" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
        </div>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* KPI row */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <KpiCard label="Total Submissions" value={data?.kpi.total_submissions ?? null} loading={loading} />
        <KpiCard label="Approved"          value={data?.kpi.approved ?? null}           loading={loading} />
        <KpiCard label="Pending"           value={data?.kpi.pending ?? null}            loading={loading} />
        <KpiCard label="Avg. Completion"   value={data?.kpi.avg_completion_human ?? null} loading={loading} />
      </div>

      {/* Charts row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Trend chart */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Submission Trend</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="h-56 rounded bg-muted animate-pulse" />
            ) : (
              <ResponsiveContainer width="100%" height={240}>
                <LineChart data={data?.trend ?? []} onClick={(e) => {
                  if (e?.activePayload?.[0]?.payload?.date) {
                    const d = e.activePayload[0].payload.date as string;
                    onNavigateToData(d, d);
                  }
                }}>
                  <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} />
                  <Tooltip />
                  <Line type="monotone" dataKey="count" stroke="#3b82f6" dot={false} strokeWidth={2} />
                </LineChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>

        {/* Status donut */}
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium">Status Breakdown</CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="h-56 rounded bg-muted animate-pulse" />
            ) : (
              <ResponsiveContainer width="100%" height={240}>
                <PieChart>
                  <Pie
                    data={data?.status_breakdown ?? []}
                    dataKey="count"
                    nameKey="status"
                    cx="50%"
                    cy="50%"
                    outerRadius={70}
                    innerRadius={35}
                  >
                    {(data?.status_breakdown ?? []).map((entry) => (
                      <Cell key={entry.status} fill={STATUS_COLORS[entry.status] ?? "#94a3b8"} />
                    ))}
                  </Pie>
                  <Legend formatter={(v) => String(v)} />
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Field distribution */}
      <Card>
        <CardHeader className="pb-2 flex flex-row items-center justify-between">
          <CardTitle className="text-sm font-medium">Field Distribution</CardTitle>
          {data && data.available_field_columns.length > 0 && (
            <Select value={fieldKey ?? ""} onValueChange={(v) => setFieldKey(v || null)}>
              <SelectTrigger className="w-44 h-7 text-xs">
                <SelectValue placeholder="Select field" />
              </SelectTrigger>
              <SelectContent>
                {data.available_field_columns.map((f) => (
                  <SelectItem key={f.key} value={f.key}>{f.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="h-56 rounded bg-muted animate-pulse" />
          ) : !data || !data.field_distribution || data.field_distribution.length === 0 ? (
            <p className="text-sm text-muted-foreground py-8 text-center">
              {!data?.available_field_columns || data?.available_field_columns.length === 0
                ? "No categorical fields on this form."
                : "No data for selected field."}
            </p>
          ) : (
            <ResponsiveContainer width="100%" height={240}>
              <BarChart data={data.field_distribution} layout="vertical">
                <XAxis type="number" tick={{ fontSize: 11 }} />
                <YAxis type="category" dataKey="value" width={120} tick={{ fontSize: 11 }} />
                <Tooltip />
                <Bar dataKey="count" fill="#3b82f6" radius={[0, 4, 4, 0]} />
              </BarChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
