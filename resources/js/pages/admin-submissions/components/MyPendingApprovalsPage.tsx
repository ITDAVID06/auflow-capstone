import React, { useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import { FileStack, Clock4, CheckCircle2, XCircle } from "lucide-react";
import AppLayout from "@/layouts/app-layout";
import AdminRequestCards, {
  type Row as AdminCardRow,
} from "./AdminRequestCards";
import SubmissionsToolbar, { type SubmissionFilterStatus } from "./SubmissionsToolbar";
import { type SharedData } from "@/types";

const METRIC_CONFIG = [
  {
    key: "total" as const,
    label: "Total",
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

function metricCellBorder(i: number): string {
  if (i === 1) return "border-l border-border/60";
  if (i === 2) return "border-t border-border/60 sm:border-t-0 sm:border-l sm:border-border/60";
  if (i === 3) return "border-l border-t border-border/60 sm:border-t-0";
  return "";
}

const LIST_PER_PAGE = 7;
const GRID_PER_PAGE = 9;

type Props = {
  metrics: { total: number; pending: number; approved: number; rejected: number };
  requests: AdminCardRow[];
  pagination: { current_page: number; last_page: number; per_page: number; total: number };
  filters?: { q?: string | null; status?: string | null; per_page?: number | null };
};
type StatusValue = SubmissionFilterStatus;

function toStatus(v: unknown): StatusValue {
  return v === "all" || v === "pending" || v === "approved" || v === "rejected"
    ? v
    : "pending";
}

export default function MyPendingApprovalsPage() {
  const { requests, pagination, filters, metrics } = usePage<Props & SharedData>().props;

  const [search, setSearch] = useState(filters?.q ?? "");
  const [status, setStatus] = useState<StatusValue>(toStatus(filters?.status));
  const [viewMode, setViewMode] = useState<"list" | "grid">(
    filters?.per_page === GRID_PER_PAGE ? "grid" : "list"
  );
  const currentPerPage = viewMode === "list" ? LIST_PER_PAGE : GRID_PER_PAGE;

  const goWithFilters = (next: Partial<{ q: string; status: StatusValue; page: number }>) => {
    const params: Record<string, string | number | undefined> = {
      q: (next.q ?? search) || undefined,
      status: (next.status ?? status) === "all" ? undefined : (next.status ?? status),
      page: next.page,
      per_page: currentPerPage,
    };

    router.get(route("admin-submissions.my-pending"), params, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ["requests", "pagination", "filters", "metrics"],
    });
  };

  const goToPage = (page: number) => {
    goWithFilters({ page });
  };

  return (
    <AppLayout
      title="My Pending Approvals"
      subtitle="Submissions assigned to you that require action"
    >
      <Head title="My Pending Approvals" />

      <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        {/* Metric Belt */}
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
                <dd className={`text-3xl font-semibold tabular-nums leading-none ${m.valueClass}`}>
                  {(metrics?.[m.key] ?? 0).toLocaleString()}
                </dd>
              </div>
            );
          })}
        </dl>

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

        <div>
          <AdminRequestCards
            requests={requests}
            pagination={pagination}
            onPageChange={goToPage}
            viewMode={viewMode}
          />
        </div>
      </div>
    </AppLayout>
  );
}
