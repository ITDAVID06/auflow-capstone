import React, { useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import { FileStack, Clock4, CheckCircle2, XCircle } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import AdminRequestCards, { Row, type SortColumn } from "./components/AdminRequestCards";
import SubmissionsToolbar, { SubmissionFilterStatus } from "./components/SubmissionsToolbar";
import { type SharedData } from "@/types";

const LIST_PER_PAGE = 7;
const GRID_PER_PAGE = 9;

type Props = {
    metrics: { total: number; pending: number; approved: number; rejected: number };
    requests: Row[];
    filters: { q?: string | null; status?: string | null; per_page?: number | null; sort?: string | null; direction?: string | null };
    pagination: { current_page: number; last_page: number; per_page: number; total: number };
};

const METRIC_CONFIG = [
    {
        key: "total" as const,
        label: "Total Submissions",
        Icon: FileStack,
        valueClass: "text-gray-900 dark:text-gray-100",
        iconClass: "text-gray-400 dark:text-gray-500",
    },
    {
        key: "pending" as const,
        label: "Pending",
        Icon: Clock4,
        valueClass: "text-amber-700 dark:text-amber-400",
        iconClass: "text-amber-500 dark:text-amber-400",
    },
    {
        key: "approved" as const,
        label: "Approved",
        Icon: CheckCircle2,
        valueClass: "text-green-700 dark:text-green-400",
        iconClass: "text-green-500 dark:text-green-400",
    },
    {
        key: "rejected" as const,
        label: "Rejected",
        Icon: XCircle,
        valueClass: "text-red-700 dark:text-red-400",
        iconClass: "text-red-500 dark:text-red-400",
    },
];

/** Returns responsive border classes for the metric belt cells. */
function metricCellBorder(i: number): string {
    if (i === 1) return "border-l border-border/60";
    if (i === 2) return "border-t border-border/60 sm:border-t-0 sm:border-l sm:border-border/60";
    if (i === 3) return "border-l border-t border-border/60 sm:border-t-0";
    return "";
}

export default function AdminSubmissionsPage() {
    const { requests, filters, pagination, metrics } = usePage<Props & SharedData>().props;

    const [search, setSearch] = useState(filters.q ?? "");
    const [status, setStatus] = useState<SubmissionFilterStatus>(
        (filters.status?.toLowerCase() as SubmissionFilterStatus) || "all",
    );
    const [viewMode, setViewMode] = useState<"list" | "grid">(
        filters.per_page === GRID_PER_PAGE ? "grid" : "list",
    );
    const [sortColumn, setSortColumn] = useState<string | null>(filters.sort ?? null);
    const [sortDirection, setSortDirection] = useState<"asc" | "desc" | null>(
        (filters.direction as "asc" | "desc") ?? null,
    );
    const currentPerPage = viewMode === "list" ? LIST_PER_PAGE : GRID_PER_PAGE;

    const goWithFilters = (
        next: Partial<{ q: string; status: SubmissionFilterStatus; page: number }>,
    ) => {
        const params: Record<string, string | number | undefined> = {
            q: (next.q ?? search) || undefined,
            status: (next.status ?? status) === "all" ? undefined : (next.status ?? status),
            page: next.page,
            per_page: currentPerPage,
            sort: sortColumn ?? undefined,
            direction: sortDirection ?? undefined,
        };

        router.get(route("admin-submissions.index"), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ["requests", "pagination", "filters", "metrics"],
        });
    };

    const handleSort = (column: SortColumn) => {
        const newDirection: "asc" | "desc" =
            sortColumn === column && sortDirection === "asc" ? "desc" : "asc";
        setSortColumn(column);
        setSortDirection(newDirection);
        router.get(
            route("admin-submissions.index"),
            {
                q: search || undefined,
                status: status === "all" ? undefined : status,
                per_page: currentPerPage,
                sort: column,
                direction: newDirection,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ["requests", "pagination", "filters", "metrics"],
            },
        );
    };

    const goToPage = (page: number) => {
        goWithFilters({ page });
    };

    return (
        <AppLayout title="All Submissions" subtitle="All submissions across the system">
            <Head title="All Submissions" />

            <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-6 sm:px-6 lg:px-8">
                {/* ── Metric Belt ─────────────────────────────────────────────────── */}
                <dl
                    className="grid grid-cols-2 overflow-hidden rounded-lg border border-border/60 bg-card sm:grid-cols-4"
                    aria-label="Submission metrics"
                >
                    {METRIC_CONFIG.map((m, i) => {
                        const Icon = m.Icon;
                        return (
                            <div
                                key={m.key}
                                className={`flex flex-col gap-1.5 px-5 py-5 ${metricCellBorder(i)}`}
                            >
                                <dt className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                    <Icon className={`h-3.5 w-3.5 ${m.iconClass}`} aria-hidden="true" />
                                    {m.label}
                                </dt>
                                <dd
                                    className={`text-3xl font-semibold tabular-nums leading-none ${m.valueClass}`}
                                >
                                    {(metrics?.[m.key] ?? 0).toLocaleString()}
                                </dd>
                            </div>
                        );
                    })}
                </dl>

                {/* ── Toolbar ─────────────────────────────────────────────────────── */}
                <SubmissionsToolbar
                    search={search}
                    status={status}
                    onSearchChange={setSearch}
                    onSearchSubmit={() => goWithFilters({ q: search, page: 1 })}
                    onStatusChange={(nextStatus) => {
                        setStatus(nextStatus);
                        goWithFilters({ status: nextStatus, page: 1 });
                    }}
                    viewMode={viewMode}
                    onViewModeChange={(mode) => {
                        setViewMode(mode);
                        goWithFilters({ page: 1 });
                    }}
                />

                {/* ── Submissions ─────────────────────────────────────────────────── */}
                <div data-tour="submission-cards">
                    <AdminRequestCards
                        requests={requests}
                        pagination={pagination}
                        onPageChange={goToPage}
                        onSort={handleSort}
                        sortColumn={sortColumn}
                        sortDirection={sortDirection}
                        viewMode={viewMode}
                    />
                </div>
            </div>
        </AppLayout>
    );
}