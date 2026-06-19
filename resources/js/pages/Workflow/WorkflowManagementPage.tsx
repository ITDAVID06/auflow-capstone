import React from "react";
import { Link, router } from "@inertiajs/react";
import {
  Eye,
  Pencil,
  Copy,
  ArchiveIcon,
  Settings,
  Link2,
  Play,
  RotateCcw,
  GitMerge,
  FileStack,
  CheckCircle2,
} from "lucide-react";

function metricCellBorder(i: number): string {
  if (i === 1) return "border-l border-gray-200 dark:border-gray-700";
  if (i === 2) return "border-t border-gray-200 dark:border-gray-700 sm:border-t-0 sm:border-l sm:border-gray-200 sm:dark:border-gray-700";
  if (i === 3) return "border-l border-t border-gray-200 dark:border-gray-700 sm:border-t-0";
  return "";
}

import AppLayout from "@/layouts/app-layout";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

import WorkflowCard from "@/pages/Workflow/components/WorkflowCard";
import StatusBadge from "@/pages/Workflow/components/StatusBadge";
import WorkflowToolbar from "./components/WorkflowToolbar";
import WorkflowPagination from "./components/WorkflowPagination";
import ViewWorkflowModal from "./components/ViewWorkflowModal";
import { useWorkflowActions } from "./hooks/useWorkflowActions";
import { Workflow, Paginated, WorkflowFilters } from "./types/workflow.types";
import EmptyStateComponent from "@/components/EmptyState";
import { Button } from "@/components/ui/button";
import { formatDate } from "@/utils/dateTime";

interface Props {
  workflows?: Paginated<Workflow> | null;
  filters?: WorkflowFilters & { perPage?: number };
  metrics?: {
    total: number;
    active: number;
    draft: number;
    archived: number;
  };
}

type WorkflowManagementPageComponent = React.FC<Props> & {
  layout?: (page: React.ReactNode) => React.ReactNode;
};

const WorkflowManagementPage: WorkflowManagementPageComponent = ({ workflows, filters, metrics }) => {
  const safeWorkflows: Paginated<Workflow> = workflows ?? {
    data: [],
    current_page: 1,
    last_page: 1,
    next_page_url: null,
    prev_page_url: null,
    total: 0,
  };

  const [search, setSearch] = React.useState(filters?.search ?? "");
  const [status, setStatus] = React.useState<"all" | "active" | "draft" | "archived">(
    filters?.status ?? "all"
  );
  const [viewMode, setViewMode] = React.useState<"list" | "grid">("list");
  const perPage = filters?.perPage ?? 9;
  const [viewingWorkflow, setViewingWorkflow] = React.useState<Workflow | null>(null);

  const { handlePublish, handleDuplicate, handleArchive, handleEnable, refreshOnly } = useWorkflowActions();

  // Sync filters with URL
  React.useEffect(() => {
    const t = setTimeout(() => {
      router.get(
        route("admin.workflows.index"),
        {
          search: search || undefined,
          status: status === "all" ? undefined : status,
          page: 1,
          per_page: perPage,
        },
        { preserveState: true, replace: true }
      );
    }, 300);
    return () => clearTimeout(t);
  }, [search, status, perPage]);

  const gotoPage = (page: number) => {
    router.get(
      route("admin.workflows.index"),
      {
        page,
        search: search || undefined,
        status: status === "all" ? undefined : status,
        per_page: perPage,
      },
      { preserveState: true, replace: true }
    );
  };

  const METRIC_CONFIG = [
    {
      key: "total",
      label: "Total Workflows",
      Icon: FileStack,
      value: metrics?.total ?? safeWorkflows.total,
      valueClass: "text-gray-900 dark:text-gray-100",
      iconClass: "text-gray-400 dark:text-gray-500",
    },
    {
      key: "active",
      label: "Active",
      Icon: CheckCircle2,
      value: metrics?.active ?? 0,
      valueClass: "text-green-700 dark:text-green-400",
      iconClass: "text-green-500 dark:text-green-400",
    },
    {
      key: "draft",
      label: "Draft",
      Icon: Pencil,
      value: metrics?.draft ?? 0,
      valueClass: "text-amber-700 dark:text-amber-400",
      iconClass: "text-amber-500 dark:text-amber-400",
    },
    {
      key: "archived",
      label: "Archived",
      Icon: ArchiveIcon,
      value: metrics?.archived ?? 0,
      valueClass: "text-red-700 dark:text-red-400",
      iconClass: "text-red-500 dark:text-red-400",
    },
  ];

  const EmptyState = () => (
    <div className={`${viewMode === "grid" ? "col-span-full py-12" : "py-12"}`}>
      <EmptyStateComponent
        icon={<GitMerge className="h-6 w-6" />}
        title="No workflows found"
        message={search ? "Try adjusting your search criteria" : "Get started by creating your first workflow"}
        action={
          search ? (
            <Button
              type="button"
              variant="link"
              onClick={() => {
                setSearch("");
                setStatus("all");
              }}
            >
              Clear filters
            </Button>
          ) : undefined
        }
      />
    </div>
  );

  return (
    <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-6 sm:px-6 lg:px-8">

      {/* Stats */}
      <dl
        className="grid grid-cols-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 sm:grid-cols-4"
        aria-label="Workflow metrics"
      >
        {METRIC_CONFIG.map((m, i) => {
          const Icon = m.Icon;
          return (
            <div key={m.key} className={`flex flex-col gap-1.5 px-5 py-5 ${metricCellBorder(i)}`}>
              <dt className="flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                <Icon className={`h-3.5 w-3.5 ${m.iconClass}`} aria-hidden="true" />
                {m.label}
              </dt>
              <dd className={`text-3xl font-semibold tabular-nums leading-none ${m.valueClass}`}>
                {m.value.toLocaleString()}
              </dd>
            </div>
          );
        })}
      </dl>

      {/* Toolbar */}
      <WorkflowToolbar
        search={search}
        status={status}
        onSearchChange={setSearch}
        onStatusChange={setStatus}
        viewMode={viewMode}
        onViewModeChange={setViewMode}
      />

      {/* List View */}
      {viewMode === "list" && (
        <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
          {/* Column header */}
          <div className="hidden sm:grid grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_156px] gap-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 pr-5 py-2.5">
            <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Workflow</span>
            <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Status</span>
            <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Linked Form</span>
            <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Updated</span>
            <span className="sr-only">Actions</span>
          </div>

          {safeWorkflows.data.length === 0 ? (
            <EmptyState />
          ) : (
            <div className="divide-y divide-gray-100 dark:divide-gray-700/60">
              {safeWorkflows.data.map((w) => {
                const lowerStatus = String(w.status).toLowerCase();
                const linkedForm = (w.form?.form_name ?? "").trim() || null;

                return (
                  <div
                    key={w.id}
                    className="group grid grid-cols-1 gap-y-2 py-3.5 pr-5 pl-4 motion-safe:transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_156px] sm:items-center sm:gap-4 sm:gap-y-0"
                  >
                    {/* Name + type */}
                    <div className="min-w-0">
                      <p className="line-clamp-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{w.workflow_name}</p>
                      <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{w.workflow_type}</p>
                    </div>

                    {/* Status */}
                    <div>
                      <StatusBadge
                        status={w.status as "Active" | "Draft" | "Archived"}
                        workflow={{ id: w.id, status: w.status as "Active" | "Draft" | "Archived" }}
                        onChanged={refreshOnly}
                      />
                    </div>

                    {/* Linked form */}
                    <div className="hidden sm:flex items-center gap-1 min-w-0">
                      <Link2 className="h-3 w-3 shrink-0 text-gray-400 dark:text-gray-500" aria-hidden="true" />
                      <span className="truncate text-xs text-gray-500 dark:text-gray-400">
                        {linkedForm ?? "No form bound"}
                      </span>
                    </div>

                    {/* Updated */}
                    <div className="hidden sm:block">
                      <span className="font-mono text-xs tabular-nums text-gray-500 dark:text-gray-400">
                        {formatDate(w.updated_at)}
                      </span>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-1 sm:justify-center sm:gap-1.5">
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <button
                            type="button"
                            onClick={() => setViewingWorkflow(w)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 motion-safe:transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            aria-label="Preview workflow"
                          >
                            <Eye className="h-4 w-4" aria-hidden="true" />
                          </button>
                        </TooltipTrigger>
                        <TooltipContent>Preview</TooltipContent>
                      </Tooltip>

                      {lowerStatus === "draft" && (
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <button
                              type="button"
                              onClick={() => handlePublish(w.id)}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 motion-safe:transition-colors hover:bg-emerald-100 hover:text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20 dark:hover:text-emerald-300 touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30"
                              aria-label="Publish workflow"
                            >
                              <Play className="h-4 w-4" aria-hidden="true" />
                            </button>
                          </TooltipTrigger>
                          <TooltipContent>Publish</TooltipContent>
                        </Tooltip>
                      )}

                      {lowerStatus === "draft" && (
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Link
                              href={`/admin/workflows/${w.id}/edit`}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 motion-safe:transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                              aria-label="Edit workflow"
                            >
                              <Pencil className="h-4 w-4" aria-hidden="true" />
                            </Link>
                          </TooltipTrigger>
                          <TooltipContent>Edit</TooltipContent>
                        </Tooltip>
                      )}

                      {lowerStatus === "archived" && (
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <button
                              type="button"
                              onClick={() => handleEnable(w.id)}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-blue-50 text-blue-700 motion-safe:transition-colors hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-500/10 dark:text-blue-400 dark:hover:bg-blue-500/20 dark:hover:text-blue-300 touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/30"
                              aria-label="Enable workflow"
                            >
                              <RotateCcw className="h-4 w-4" aria-hidden="true" />
                            </button>
                          </TooltipTrigger>
                          <TooltipContent>Re-enable</TooltipContent>
                        </Tooltip>
                      )}

                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <button
                            type="button"
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 motion-safe:transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            aria-label="More actions"
                          >
                            <Settings className="h-4 w-4" />
                          </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                          {lowerStatus === "archived" && (
                            <DropdownMenuItem className="cursor-pointer" onClick={() => handleEnable(w.id)}>
                              <RotateCcw className="mr-2 h-4 w-4" />
                              Re-enable
                            </DropdownMenuItem>
                          )}
                          <DropdownMenuItem className="cursor-pointer" onClick={() => handleDuplicate(w.id)}>
                            <Copy className="mr-2 h-4 w-4" />
                            Duplicate
                          </DropdownMenuItem>
                          {lowerStatus !== "archived" && (
                            <DropdownMenuItem
                              variant="destructive"
                              className="cursor-pointer"
                              onClick={() => handleArchive(w)}
                            >
                              <ArchiveIcon className="mr-2 h-4 w-4" />
                              Archive
                            </DropdownMenuItem>
                          )}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Grid View */}
      {viewMode === "grid" && (
        <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
          {safeWorkflows.data.length === 0 ? (
            <EmptyState />
          ) : (
            safeWorkflows.data.map((w) => (
              <WorkflowCard
                key={w.id}
                workflow={w}
                onView={setViewingWorkflow}
                onPublish={handlePublish}
                onEnable={handleEnable}
                onDuplicate={handleDuplicate}
                onArchive={handleArchive}
                onRefresh={refreshOnly}
              />
            ))
          )}
        </div>
      )}

      <WorkflowPagination
        pagination={safeWorkflows}
        currentItemsCount={safeWorkflows.data.length}
        onPageChange={gotoPage}
      />

      <ViewWorkflowModal
        open={!!viewingWorkflow}
        onClose={() => setViewingWorkflow(null)}
        workflow={viewingWorkflow}
      />
    </div>
  );
};

WorkflowManagementPage.layout = (page: React.ReactNode) => (
  <AppLayout title="Workflow Management" subtitle="Manage approval workflows">
    {page}
  </AppLayout>
);

export default WorkflowManagementPage;

