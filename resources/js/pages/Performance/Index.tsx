import React, { useMemo } from "react";
import { useForm } from "@inertiajs/react";
import { toast } from "sonner";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip as RechartsTooltip,
    ResponsiveContainer,
    Legend,
} from "recharts";
import {
    Users,
    CheckCircle2,
    Clock4,
    AlertTriangle,
    Search,
    SlidersHorizontal,
    BarChart2,
    AlertCircle,
    Clock,
} from "lucide-react";

interface PerformanceMetric {
    actor_id: number;
    staff_name: string;
    department: string;
    total_approvals: number;
    avg_response_time_seconds: number;
    median_response_time_seconds: number;
    longest_duration_seconds: number;
}

interface PendingMetric {
    actor_id: number;
    staff_name: string;
    department: string;
    pending_count: number;
    oldest_pending_seconds: number;
}

interface Filters {
    date_from?: string | null;
    date_to?: string | null;
    form_id?: string | null;
}

interface Props {
    metrics: PerformanceMetric[];
    pending: PendingMetric[];
    filters: Filters;
}

function metricCellBorder(i: number): string {
    if (i === 1) return "border-l border-gray-200 dark:border-gray-700";
    if (i === 2)
        return "border-t border-gray-200 dark:border-gray-700 sm:border-t-0 sm:border-l sm:border-gray-200 sm:dark:border-gray-700";
    if (i === 3) return "border-l border-t border-gray-200 dark:border-gray-700 sm:border-t-0";
    return "";
}

const formatDuration = (seconds: number): string => {
    if (!seconds || seconds === 0) return "—";
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    const parts: string[] = [];
    if (h > 0) parts.push(`${h}h`);
    if (m > 0) parts.push(`${m}m`);
    if (s > 0 || parts.length === 0) parts.push(`${s}s`);
    return parts.join(" ");
};

const toHours = (seconds: number): number =>
    parseFloat((seconds / 3600).toFixed(1));

const METRIC_CONFIG = [
    {
        key: "staffCount" as const,
        label: "Staff Tracked",
        Icon: Users,
        valueClass: "text-gray-900 dark:text-gray-100",
        iconClass: "text-gray-400 dark:text-gray-500",
        format: (v: number) => v.toLocaleString(),
    },
    {
        key: "totalApprovals" as const,
        label: "Total Approvals",
        Icon: CheckCircle2,
        valueClass: "text-green-700 dark:text-green-400",
        iconClass: "text-green-500 dark:text-green-400",
        format: (v: number) => v.toLocaleString(),
    },
    {
        key: "avgResponseAll" as const,
        label: "Avg Response Time",
        Icon: Clock4,
        valueClass: "text-amber-700 dark:text-amber-400",
        iconClass: "text-amber-500 dark:text-amber-400",
        format: formatDuration,
    },
    {
        key: "totalPending" as const,
        label: "Currently Pending",
        Icon: AlertTriangle,
        valueClass: null, // computed at render
        iconClass: null,
        format: (v: number) => v.toLocaleString(),
    },
];

export default function PerformancePage({ metrics, pending, filters }: Props) {
    const form = useForm<Filters>({
        date_from: filters.date_from || "",
        date_to: filters.date_to || "",
        form_id: filters.form_id || "",
    });

    const handleFilterChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        form.setData(e.target.name as keyof Filters, e.target.value);
    };

    const applyFilters = () => {
        form.get(route("performance.index"), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onError: () => toast.error("Failed to load performance data. Please try again."),
        });
    };

    const resetFilters = () => {
        const emptyFilters = { date_from: "", date_to: "", form_id: "" };
        form.transform(() => emptyFilters);
        form.setData(emptyFilters);
        form.get(route("performance.index"), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => form.transform((d) => d),
            onError: () => toast.error("Failed to load performance data. Please try again."),
        });
    };

    const summaryMetrics = useMemo(() => {
        const totalApprovals = metrics.reduce((sum, m) => sum + m.total_approvals, 0);
        const avgResponseAll =
            metrics.length > 0
                ? Math.round(
                      metrics.reduce((sum, m) => sum + m.avg_response_time_seconds, 0) /
                          metrics.length,
                  )
                : 0;
        const totalPending = pending.reduce((sum, p) => sum + p.pending_count, 0);
        return { staffCount: metrics.length, totalApprovals, avgResponseAll, totalPending };
    }, [metrics, pending]);

    const chartData = useMemo(
        () =>
            metrics.slice(0, 10).map((m) => ({
                name: m.staff_name,
                avgHours: toHours(m.avg_response_time_seconds),
                medianHours: toHours(m.median_response_time_seconds),
            })),
        [metrics],
    );

    const hasFilters = Boolean(
        form.data.date_from || form.data.date_to || form.data.form_id,
    );

    return (
        <AppLayout title="Staff Performance" subtitle="Monitor approval speeds and identify bottlenecks">
            <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-6 sm:px-6 lg:px-8">
                {/* Filter Bar — always visible */}
                <div className="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3">
                    <span className="flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 shrink-0">
                        <SlidersHorizontal className="h-3.5 w-3.5" aria-hidden="true" />
                        Filters
                    </span>
                    <div className="min-w-0 flex items-center gap-1.5">
                        <label
                            htmlFor="perf-date-from"
                            className="text-xs font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap"
                        >
                            From
                        </label>
                        <Input
                            id="perf-date-from"
                            type="date"
                            name="date_from"
                            value={form.data.date_from || ""}
                            onChange={handleFilterChange}
                            className="h-8 w-[140px] sm:w-[160px] text-xs"
                        />
                    </div>
                    <div className="min-w-0 flex items-center gap-1.5">
                        <label
                            htmlFor="perf-date-to"
                            className="text-xs font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap"
                        >
                            To
                        </label>
                        <Input
                            id="perf-date-to"
                            type="date"
                            name="date_to"
                            value={form.data.date_to || ""}
                            onChange={handleFilterChange}
                            className="h-8 w-[140px] sm:w-[160px] text-xs"
                        />
                    </div>
                    <div className="min-w-0 flex items-center gap-1.5">
                        <label
                            htmlFor="perf-form-id"
                            className="text-xs font-medium text-gray-600 dark:text-gray-400 whitespace-nowrap"
                        >
                            Form ID
                        </label>
                        <Input
                            id="perf-form-id"
                            type="text"
                            name="form_id"
                            placeholder="Any"
                            value={form.data.form_id || ""}
                            onChange={handleFilterChange}
                            className="h-8 w-[90px] sm:w-[100px] text-xs"
                        />
                    </div>
                    <div className="flex gap-2 ml-auto">
                        <Button
                            onClick={applyFilters}
                            size="sm"
                            className="h-8 text-xs touch-manipulation"
                            disabled={form.processing}
                        >
                            <Search className="mr-1.5 h-3.5 w-3.5" aria-hidden="true" />
                            {form.processing ? "Applying…" : "Apply"}
                        </Button>
                        {hasFilters && (
                            <Button
                                onClick={resetFilters}
                                size="sm"
                                variant="outline"
                                className="h-8 text-xs touch-manipulation"
                                disabled={form.processing}
                            >
                                Reset
                            </Button>
                        )}
                    </div>
                </div>

                {form.processing ? (
                    /* ── Loading skeletons ── */
                    <>
                        {/* Metric belt skeleton */}
                        <div
                            className="grid grid-cols-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 sm:grid-cols-4 animate-pulse"
                            aria-hidden="true"
                        >
                            {[0, 1, 2, 3].map((i) => (
                                <div key={i} className={`flex flex-col gap-2 px-5 py-5 ${metricCellBorder(i)}`}>
                                    <div className="h-3 w-24 rounded bg-gray-100 dark:bg-gray-800" />
                                    <div className="h-8 w-16 rounded bg-gray-100 dark:bg-gray-800" />
                                </div>
                            ))}
                        </div>

                        {/* Chart skeleton */}
                        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg animate-pulse">
                            <div className="border-b border-gray-200 dark:border-gray-700 px-5 py-4">
                                <div className="h-4 w-48 rounded bg-gray-100 dark:bg-gray-800" />
                                <div className="mt-1.5 h-3 w-64 rounded bg-gray-100 dark:bg-gray-800" />
                            </div>
                            <div className="px-2 py-4 h-[300px] flex items-end gap-3 px-6 pb-8">
                                {[60, 90, 45, 75, 55, 80, 40, 65, 50, 70].map((h, i) => (
                                    <div key={i} className="flex-1 rounded-t bg-gray-100 dark:bg-gray-800" style={{ height: `${h}%` }} />
                                ))}
                            </div>
                        </div>

                        {/* Table skeletons */}
                        {[0, 1].map((t) => (
                            <div key={t} className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden animate-pulse">
                                <div className="border-b border-gray-200 dark:border-gray-700 px-5 py-4">
                                    <div className="h-4 w-40 rounded bg-gray-100 dark:bg-gray-800" />
                                    <div className="mt-1.5 h-3 w-56 rounded bg-gray-100 dark:bg-gray-800" />
                                </div>
                                <div className="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    {[0, 1, 2, 3].map((r) => (
                                        <div key={r} className="flex items-center gap-4 px-5 py-3.5">
                                            <div className="h-4 w-32 rounded bg-gray-100 dark:bg-gray-800" />
                                            <div className="h-4 w-24 rounded bg-gray-100 dark:bg-gray-800" />
                                            <div className="ml-auto h-4 w-12 rounded bg-gray-100 dark:bg-gray-800" />
                                            <div className="h-4 w-16 rounded bg-gray-100 dark:bg-gray-800" />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </>
                ) : (
                    /* ── Live data ── */
                    <>
                        <div className="flex flex-col gap-5 motion-safe:animate-in motion-safe:fade-in duration-300">
                {/* Metric Belt */}
                <dl
                    className="grid grid-cols-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 sm:grid-cols-4"
                    aria-label="Performance metrics"
                >
                    {METRIC_CONFIG.map((m, i) => {
                        const Icon = m.Icon;
                        const value = summaryMetrics[m.key];
                        const isPending = m.key === "totalPending";
                        const hasPending = isPending && (value as number) > 0;
                        const valueClass =
                            m.valueClass ??
                            (hasPending
                                ? "text-red-700 dark:text-red-400"
                                : "text-gray-900 dark:text-gray-100");
                        const iconClass =
                            m.iconClass ??
                            (hasPending
                                ? "text-red-500 dark:text-red-400"
                                : "text-gray-400 dark:text-gray-500");
                        return (
                            <div
                                key={m.key}
                                className={`flex flex-col gap-1.5 px-5 py-5 ${metricCellBorder(i)}`}
                            >
                                <dt className="flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    <Icon
                                        className={`h-3.5 w-3.5 ${iconClass}`}
                                        aria-hidden="true"
                                    />
                                    {m.label}
                                </dt>
                                <dd
                                    className={`text-3xl font-semibold tabular-nums leading-none ${valueClass}`}
                                >
                                    {m.format(value as number)}
                                </dd>
                            </div>
                        );
                    })}
                </dl>

                {/* Chart */}
                <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <div className="flex items-start justify-between border-b border-gray-200 dark:border-gray-700 px-5 py-4">
                        <div>
                            <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                <BarChart2
                                    className="h-4 w-4 text-gray-400 dark:text-gray-500"
                                    aria-hidden="true"
                                />
                                Response Time — Top 10 Staff
                            </h2>
                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Average vs median approval response time in hours
                            </p>
                        </div>
                    </div>
                    {metrics.length === 0 ? (
                        <div className="py-20 text-center">
                            <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">No chart data for the selected filters</p>
                            <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">Try adjusting your date range or form selection.</p>
                        </div>
                    ) : (
                        <div className="px-2 py-4 h-[260px] sm:h-[320px]">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart
                                    data={chartData}
                                    margin={{ top: 4, right: 16, left: 0, bottom: 64 }}
                                    barCategoryGap="32%"
                                >
                                    <CartesianGrid
                                        strokeDasharray="3 3"
                                        vertical={false}
                                        stroke="#e5e7eb"
                                    />
                                    <XAxis
                                        dataKey="name"
                                        angle={-40}
                                        textAnchor="end"
                                        height={72}
                                        tick={{ fontSize: 11, fill: "#6b7280" }}
                                        tickLine={false}
                                        axisLine={false}
                                    />
                                    <YAxis
                                        tick={{ fontSize: 11, fill: "#6b7280" }}
                                        tickLine={false}
                                        axisLine={false}
                                        unit=" hr"
                                    />
                                    <RechartsTooltip
                                        contentStyle={{
                                            backgroundColor: "#ffffff",
                                            border: "1px solid #e5e7eb",
                                            borderRadius: "6px",
                                            fontSize: "12px",
                                        }}
                                        formatter={(value: number, name: string) => [
                                            `${value} hrs`,
                                            name === "avgHours" ? "Avg Response" : "Median Response",
                                        ]}
                                        labelStyle={{ color: "#111827", fontWeight: 600 }}
                                        cursor={{ fill: "#f9fafb" }}
                                    />
                                    <Legend
                                        verticalAlign="top"
                                        align="right"
                                        iconType="square"
                                        iconSize={8}
                                        wrapperStyle={{ fontSize: "11px", paddingBottom: "8px" }}
                                        formatter={(value: string) =>
                                            value === "avgHours" ? "Avg Response" : "Median Response"
                                        }
                                    />
                                    <Bar
                                        dataKey="avgHours"
                                        name="avgHours"
                                        fill="#3b82f6"
                                        radius={[3, 3, 0, 0]}
                                    />
                                    <Bar
                                        dataKey="medianHours"
                                        name="medianHours"
                                        fill="#d1d5db"
                                        radius={[3, 3, 0, 0]}
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </div>

                {/* Staff Approval Speed Table */}
                <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-5 py-4">
                        <div>
                            <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                <CheckCircle2
                                    className="h-4 w-4 text-green-500 dark:text-green-400"
                                    aria-hidden="true"
                                />
                                Staff Approval Speed
                            </h2>
                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Completed tasks (approved / rejected / skipped) in the selected period
                            </p>
                        </div>
                        {metrics.length > 0 && (
                            <span className="text-xs font-medium text-gray-400 dark:text-gray-500 tabular-nums">
                                {metrics.length.toLocaleString()} staff
                            </span>
                        )}
                    </div>
                    {metrics.length === 0 ? (
                        <div className="py-20 text-center">
                            <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">No metrics found for the selected filters</p>
                            <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">Try adjusting your date range or form selection.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm" aria-label="Staff approval speed">
                                <thead>
                                    <tr className="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Staff Member
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Department
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Approvals
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Avg Response
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Median Response
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Longest
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    {metrics.map((row) => (
                                        <tr
                                            key={row.actor_id}
                                            className="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                        >
                                            <td className="px-5 py-3.5 font-medium text-gray-900 dark:text-gray-100">
                                                {row.staff_name}
                                            </td>
                                            <td className="px-5 py-3.5 text-gray-500 dark:text-gray-400">
                                                {row.department}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-mono tabular-nums text-gray-900 dark:text-gray-100">
                                                {row.total_approvals.toLocaleString()}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-mono tabular-nums text-amber-700 dark:text-amber-400">
                                                {formatDuration(row.avg_response_time_seconds)}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
                                                {formatDuration(row.median_response_time_seconds)}
                                            </td>
                                            <td className="px-5 py-3.5 text-right font-mono tabular-nums text-gray-500 dark:text-gray-400">
                                                {formatDuration(row.longest_duration_seconds)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Current Bottlenecks Table */}
                <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                    <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-5 py-4">
                        <div>
                            <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                <AlertCircle
                                    className="h-4 w-4 text-amber-500 dark:text-amber-400"
                                    aria-hidden="true"
                                />
                                Current Bottlenecks
                            </h2>
                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                Staff with the oldest unresolved pending items right now
                            </p>
                        </div>
                        {pending.length > 0 && (
                            <span className="text-xs font-medium text-gray-400 dark:text-gray-500 tabular-nums">
                                {pending.length.toLocaleString()} staff
                            </span>
                        )}
                    </div>
                    {pending.length === 0 ? (
                        <div className="py-20 text-center">
                            <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">No pending items — all caught up.</p>
                            <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">All assigned tasks have been actioned.</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm" aria-label="Current bottlenecks">
                                <thead>
                                    <tr className="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Staff Member
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Department
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Pending Tasks
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Oldest Pending
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700/60">
                                    {pending.map((row) => (
                                        <tr
                                            key={row.actor_id}
                                            className="hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                        >
                                            <td className="px-5 py-3.5 font-medium text-gray-900 dark:text-gray-100">
                                                {row.staff_name}
                                            </td>
                                            <td className="px-5 py-3.5 text-gray-500 dark:text-gray-400">
                                                {row.department}
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <span
                                                    className={`inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums ring-1 ring-inset ${
                                                        row.pending_count >= 5
                                                            ? "bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-900/20 dark:text-red-400 dark:ring-red-500/30"
                                                            : row.pending_count >= 2
                                                              ? "bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/20 dark:text-amber-400 dark:ring-amber-500/30"
                                                              : "bg-gray-50 text-gray-700 ring-gray-500/20 dark:bg-gray-700/50 dark:text-gray-300 dark:ring-gray-500/30"
                                                    }`}
                                                >
                                                    {row.pending_count}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3.5 text-right">
                                                <span className="inline-flex items-center justify-end gap-1.5 font-mono tabular-nums text-amber-700 dark:text-amber-400">
                                                    <Clock
                                                        className="h-3.5 w-3.5 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    {formatDuration(row.oldest_pending_seconds)}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
