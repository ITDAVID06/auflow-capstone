import React, { useEffect, useState } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import axios from "axios";
import { toast } from "sonner";
import {
  Eye,
  FileBarChart,
  GitBranch,
  Copy,
  ArchiveIcon,
  Settings,
  Pencil,
  Play,
  ChevronDown,
  FileStack,
  CheckCircle2,
  Clock4,
} from "lucide-react";

function metricCellBorder(i: number): string {
  if (i === 1) return "border-l border-gray-200 dark:border-gray-700";
  if (i === 2) return "border-t border-gray-200 dark:border-gray-700 sm:border-t-0 sm:border-l sm:border-gray-200 sm:dark:border-gray-700";
  if (i === 3) return "border-l border-t border-gray-200 dark:border-gray-700 sm:border-t-0";
  return "";
}

import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { RBAC } from "@/components/RBAC";

import FormCard from "./components/FormCard";
import FormToolbar from "./components/FormToolbar";
import FormPagination from "./components/FormPagination";
import LockedFormModal from "./components/LockedFormModal";
import ViewFormModal from "./components/ViewFormModal";

import { useFormActions } from "./hooks/useFormActions";
import { Form, PaginatedForms, FormFilters, PermissionOption, FormFieldItem } from "./types/form.types";
import { formatDate } from "@/utils/dateTime";

const LIST_PER_PAGE = 7;
const GRID_PER_PAGE = 9;

interface Props {
  forms: PaginatedForms;
  filters?: FormFilters;
  metrics?: {
    total: number;
    active: number;
    inactive: number;
    archived: number;
  };
}

interface PermissionApiItem {
  id: number;
  action: "student-access" | "staff-access" | "public-access" | string;
}

function FormStatusBadge({
  form,
  canManage,
  onStatusChange,
}: {
  form: Form;
  canManage: boolean;
  onStatusChange: (form: Form, status: "Active" | "Inactive") => void;
}) {
  const map: Record<string, { dot: string; text: string; bg: string }> = {
    active: {
      dot: "bg-emerald-500",
      text: "text-emerald-700 dark:text-emerald-400",
      bg: "bg-emerald-50 dark:bg-emerald-500/10",
    },
    inactive: {
      dot: "bg-amber-500",
      text: "text-amber-700 dark:text-amber-400",
      bg: "bg-amber-50 dark:bg-amber-500/10",
    },
    archived: {
      dot: "bg-zinc-400",
      text: "text-gray-600 dark:text-gray-400",
      bg: "bg-gray-100 dark:bg-gray-800",
    },
  };

  const lower = form.status.toLowerCase();
  const style = map[lower] ?? map.inactive;
  const canToggleStatus = canManage && form.status !== "Archived";

  if (!canToggleStatus) {
    return (
      <span
        className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium leading-none ${style.bg} ${style.text}`}
      >
        <span className={`inline-block h-1.5 w-1.5 rounded-full ${style.dot}`} />
        {form.status}
      </span>
    );
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className={`inline-flex cursor-pointer items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium leading-none transition-colors hover:opacity-80 ${style.bg} ${style.text}`}
        >
          <span className={`inline-block h-1.5 w-1.5 rounded-full ${style.dot}`} />
          {form.status}
          <ChevronDown className="h-3 w-3 opacity-60" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-36">
        <DropdownMenuItem
          disabled={form.status === "Active"}
          onClick={() => onStatusChange(form, "Active")}
          className="cursor-pointer"
        >
          <span className="mr-2 inline-block h-2 w-2 rounded-full bg-emerald-500" />
          Active
        </DropdownMenuItem>
        <DropdownMenuItem
          disabled={form.status === "Inactive"}
          onClick={() => onStatusChange(form, "Inactive")}
          className="cursor-pointer"
        >
          <span className="mr-2 inline-block h-2 w-2 rounded-full bg-amber-500" />
          Inactive
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

function FormEmptyState({
  isGrid,
  hasSearch,
  onClearSearch,
}: {
  isGrid: boolean;
  hasSearch: boolean;
  onClearSearch: () => void;
}) {
  return (
    <div className={`py-20 text-center ${isGrid ? "col-span-full" : ""}`}>
      <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">No forms found</p>
      <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
        {hasSearch ? "Try adjusting your search criteria" : "Get started by creating your first form"}
      </p>
      {hasSearch && (
        <Button
          variant="outline"
          size="sm"
          onClick={onClearSearch}
          className="mt-4 h-8 rounded-md"
        >
          Clear search
        </Button>
      )}
    </div>
  );
}

const FormManagementPage: React.FC<Props> = ({ forms, filters, metrics }) => {
  const permissions: string[] =
    ((usePage().props as { auth?: { user?: { permissions?: string[] } } })?.auth?.user?.permissions ?? []);
  const canManageForms = permissions.includes("forms.manage");

  const [search, setSearch] = useState<string>(filters?.search ?? "");
  const [status, setStatus] = useState<"All" | "Active" | "Inactive" | "Archived">(
    filters?.status ?? "All"
  );
  const [viewMode, setViewMode] = useState<"list" | "grid">("list");
  const [viewingLockedForm, setViewingLockedForm] = useState<null | { form: Form; fields: FormFieldItem[] }>(null);
  const [viewingForm, setViewingForm] = useState<null | { form: Form; fields: FormFieldItem[] }>(null);
  const [permOptions, setPermOptions] = useState<PermissionOption[]>([]);

  const [pendingLockCheck, setPendingLockCheck] = useState<{
    id: number;
    prevLocked: boolean;
    targetStatus: "Active" | "Inactive";
  } | null>(null);

  const { handleRevision, handleDuplicate, handleArchive, handleStatusChange, updateVisibility, openViewForm } = useFormActions();
  const currentPerPage = viewMode === "list" ? LIST_PER_PAGE : GRID_PER_PAGE;

  // Load permission options
  useEffect(() => {
    axios.get<PermissionApiItem[]>("/admin/forms/permissions").then((res) => {
      const mapped = res.data.map((p) => ({
        id: p.id,
        label:
          p.action === "student-access"
            ? "Student"
            : p.action === "staff-access"
            ? "Staff"
            : p.action === "public-access"
            ? "Public"
            : "Hidden",
      }));
      setPermOptions(mapped);
    }).catch(() => {
      toast.error("Could not load visibility options.");
    });
  }, []);

  // Lock check effect
  useEffect(() => {
    if (!pendingLockCheck) return;
    const updated = forms?.data?.find((f) => f.id === pendingLockCheck.id);
    if (!updated) return;

    const becameInactive = updated.status === "Inactive";
    const isTargetingInactive = pendingLockCheck.targetStatus === "Inactive";
    const lockTriggered =
      isTargetingInactive && becameInactive && updated.is_locked === true && !pendingLockCheck.prevLocked;

    if (lockTriggered) toast.info("This form is now Inactive and has been locked. Editing is disabled.");
    setPendingLockCheck(null);
  }, [forms?.data, pendingLockCheck]);

  const goWithFilters = (next: Partial<{ search: string; status: string; page: number }>) => {
    const params: Record<string, string> = {};
    const s = next.search ?? search;
    const st = (next.status ?? status) as string;
    if (s) params["search"] = s;
    if (st && st !== "All") params["status"] = st;
    if (next.page) params["page"] = String(next.page);
    params["per_page"] = String(currentPerPage);

    router.get(window.location.pathname, params, { preserveState: true, replace: true });
  };

  const gotoPage = (page: number) => goWithFilters({ page });

  const handleViewForm = async (form: Form) => {
    const fields = await openViewForm(form.id);
    if (fields) {
      setViewingForm({ form, fields });
    }
  };

  const handleStatusChangeWithLock = (form: Form, next: "Active" | "Inactive") => {
    handleStatusChange(form, next, setPendingLockCheck);
  };

  const METRIC_CONFIG = [
    {
      key: "total",
      label: "Total Forms",
      Icon: FileStack,
      value: metrics?.total ?? forms.total,
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
      key: "inactive",
      label: "Inactive",
      Icon: Clock4,
      value: metrics?.inactive ?? 0,
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
    <FormEmptyState
      isGrid={viewMode === "grid"}
      hasSearch={!!search}
      onClearSearch={() => {
        setSearch("");
        goWithFilters({ search: "", page: 1 });
      }}
    />
  );

  return (
    <AppLayout title="Form Management" subtitle="Create and manage form templates">
      <div className="mx-auto w-full max-w-[1520px] space-y-5 px-4 py-6 sm:px-6 lg:px-8">

        {/* Stats */}
        <dl
          className="grid grid-cols-2 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 sm:grid-cols-4"
          aria-label="Form metrics"
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
        <FormToolbar
          search={search}
          status={status}
          onSearchChange={setSearch}
          onSearchSubmit={() => goWithFilters({ search })}
          onStatusChange={(val) => {
            setStatus(val);
            goWithFilters({ status: val, page: 1 });
          }}
          viewMode={viewMode}
          onViewModeChange={(mode) => {
            setViewMode(mode);
            goWithFilters({ page: 1 });
          }}
        />

        {/* List View */}
        {viewMode === "list" && (
          <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            {/* Column header */}
            <div className="hidden sm:grid grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_156px] gap-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-4 pr-5 py-2.5">
              <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Form</span>
              <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Status</span>
              <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Category</span>
              <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Updated</span>
              <span className="sr-only">Actions</span>
            </div>

            {forms.data.length === 0 ? (
              <EmptyState />
            ) : (
              <div className="divide-y divide-gray-100 dark:divide-gray-700/60">
                {forms.data.map((form) => (
                  <div
                    key={form.id}
                    className="group grid grid-cols-1 gap-y-2 py-3.5 pr-5 pl-4 motion-safe:transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_156px] sm:items-center sm:gap-4 sm:gap-y-0"
                  >
                    {/* Name + code */}
                    <div className="min-w-0">
                      <p className="line-clamp-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{form.form_name}</p>
                      <p className="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{form.form_code}</p>
                    </div>

                    {/* Status */}
                    <div>
                      <FormStatusBadge
                        form={form}
                        canManage={canManageForms}
                        onStatusChange={handleStatusChangeWithLock}
                      />
                    </div>

                    {/* Category */}
                    <div className="hidden sm:block min-w-0">
                      {form.category_name ? (
                        <span className="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                          {form.category_name}
                        </span>
                      ) : (
                        <span className="text-xs text-gray-400 dark:text-gray-500">—</span>
                      )}
                    </div>

                    {/* Updated */}
                    <div className="hidden sm:block">
                      <span className="font-mono text-xs tabular-nums text-gray-500 dark:text-gray-400">
                        {formatDate(form.updated_at)}
                      </span>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-1 sm:justify-center sm:gap-1.5">
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <button
                            type="button"
                            onClick={() => handleViewForm(form)}
                            className="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 motion-safe:transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            aria-label="Preview form"
                          >
                            <Eye className="h-4 w-4" aria-hidden="true" />
                          </button>
                        </TooltipTrigger>
                        <TooltipContent>Preview</TooltipContent>
                      </Tooltip>

                      {form.status === "Inactive" && (
                        <RBAC permission="forms.manage">
                          <Tooltip>
                            <TooltipTrigger asChild>
                              <button
                                type="button"
                                onClick={() => handleStatusChangeWithLock(form, "Active")}
                                className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 motion-safe:transition-colors hover:bg-emerald-100 hover:text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20 dark:hover:text-emerald-300 touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30"
                                aria-label="Activate form"
                              >
                                <Play className="h-4 w-4" aria-hidden="true" />
                              </button>
                            </TooltipTrigger>
                            <TooltipContent>Activate</TooltipContent>
                          </Tooltip>
                        </RBAC>
                      )}

                      {!form.is_locked && form.status !== "Active" && (
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Link
                              href={`/admin/forms/${form.id}/edit`}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 motion-safe:transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                              aria-label="Edit form"
                            >
                              <Pencil className="h-4 w-4" aria-hidden="true" />
                            </Link>
                          </TooltipTrigger>
                          <TooltipContent>Edit</TooltipContent>
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
                          <DropdownMenuItem asChild>
                            <Link href={`/reports?form_id=${form.id}`} className="cursor-pointer">
                              <FileBarChart className="mr-2 h-4 w-4" />
                              View Report
                            </Link>
                          </DropdownMenuItem>

                          {(form.is_locked || form.status === "Active") && (
                            <DropdownMenuItem className="cursor-pointer" onClick={() => handleRevision(form.id)}>
                              <GitBranch className="mr-2 h-4 w-4" />
                              Create Revision
                            </DropdownMenuItem>
                          )}
                          <DropdownMenuItem className="cursor-pointer" onClick={() => handleDuplicate(form.id)}>
                            <Copy className="mr-2 h-4 w-4" />
                            Duplicate Copy
                          </DropdownMenuItem>
                          {form.status !== "Archived" && (
                            <DropdownMenuItem
                              variant="destructive"
                              className="cursor-pointer"
                              onClick={() => handleArchive(form)}
                            >
                              <ArchiveIcon className="mr-2 h-4 w-4" />
                              Archive
                            </DropdownMenuItem>
                          )}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Grid View */}
        {viewMode === "grid" && (
          <div className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            {forms.data.length === 0 ? (
              <EmptyState />
            ) : (
              forms.data.map((form) => (
                <FormCard
                  key={form.id}
                  form={form}
                  permOptions={permOptions}
                  onView={handleViewForm}
                  onRevision={handleRevision}
                  onDuplicate={handleDuplicate}
                  onArchive={handleArchive}
                  onStatusChange={handleStatusChangeWithLock}
                  onVisibilityChange={updateVisibility}
                />
              ))
            )}
          </div>
        )}

        {(forms.last_page ?? 1) > 1 && <FormPagination forms={forms} onPageChange={gotoPage} />}

        {/* View Form Modal */}
        {viewingForm && (
          <ViewFormModal
            form={viewingForm.form}
            fields={viewingForm.fields}
            open={true}
            onClose={() => setViewingForm(null)}
          />
        )}

        {/* Locked Form Modal - Legacy */}
        {viewingLockedForm && (
          <LockedFormModal
            form={{
              form_name: viewingLockedForm.form.form_name,
              form_code: viewingLockedForm.form.form_code,
              description: viewingLockedForm.form.description ?? "",
              version: viewingLockedForm.form.version,
              status: viewingLockedForm.form.status,
            }}
            fields={viewingLockedForm.fields}
            onClose={() => setViewingLockedForm(null)}
          />
        )}
      </div>
    </AppLayout>
  );
};

export default FormManagementPage;

