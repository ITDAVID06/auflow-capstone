import React from "react";
import { Link, usePage } from "@inertiajs/react";
import { Tooltip, TooltipTrigger, TooltipContent } from "@/components/ui/tooltip";
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuLabel,
} from "@/components/ui/dropdown-menu";
import {
  Eye,
  Pencil,
  GitBranch,
  Copy,
  Archive as ArchiveIcon,
  Play,
  FileBarChart,
  Settings,
  EyeOff,
  Lock,
  ChevronDown,
} from "lucide-react";
import { RBAC } from "@/components/RBAC";
import { Form, PermissionOption } from "../types/form.types";

interface FormCardProps {
  form: Form;
  permOptions: PermissionOption[];
  onView: (form: Form) => void;
  onRevision: (id: number) => void;
  onDuplicate: (id: number) => void;
  onArchive: (form: Form) => void;
  onStatusChange: (form: Form, status: "Active" | "Inactive") => void;
  onVisibilityChange: (formId: number, permissionId: string | null) => void;
}

const statusConfig = {
  Active: {
    dot: "bg-emerald-500",
    text: "text-emerald-700 dark:text-emerald-400",
    bg: "bg-emerald-50 dark:bg-emerald-500/10",
  },
  Inactive: {
    dot: "bg-amber-500",
    text: "text-amber-700 dark:text-amber-400",
    bg: "bg-amber-50 dark:bg-amber-500/10",
  },
  Archived: {
    dot: "bg-gray-400",
    text: "text-gray-600 dark:text-gray-400",
    bg: "bg-gray-100 dark:bg-gray-800",
  },
} as const;

export default function FormCard({
  form,
  permOptions,
  onView,
  onRevision,
  onDuplicate,
  onArchive,
  onStatusChange,
  onVisibilityChange,
}: FormCardProps) {
  const permissions: string[] =
    ((usePage().props as { auth?: { user?: { permissions?: string[] } } })?.auth?.user?.permissions ?? []);
  const canManageForms = permissions.includes("forms.manage");

  const hasDescription = (form.description ?? "").trim().length > 0;
  const desc = hasDescription ? form.description!.trim() : null;
  const categoryLabel = (form.category_name || "").trim() || "Uncategorized";
  const badge = statusConfig[form.status] ?? statusConfig.Inactive;
  const canEditInPlace = !form.is_locked && form.status !== "Active";

  const visibilityLabel =
    permOptions.find((o) => String(o.id) === String(form.permission_id))?.label ?? "Hidden";

  return (
    <div
      className="group relative flex min-h-[168px] flex-col rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 transition-colors hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50/50 dark:hover:bg-gray-800/30"
      data-tour="forms-card"
    >
      {/* Header */}
      <div className="flex items-start gap-3 px-5 pt-5 pb-0">
        <div className="min-w-0 flex-1">
          <h3 className="line-clamp-1 text-base font-semibold leading-snug text-gray-900 dark:text-gray-100">
            {form.form_name}
          </h3>
          <div className="mt-1.5 flex items-center gap-2">
            {/* Status pill — interactive dropdown */}
            {canManageForms ? (
              <RBAC permission="forms.manage">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <button
                      type="button"
                      className={`inline-flex cursor-pointer items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium leading-none transition-colors hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 ${badge.bg} ${badge.text}`}
                    >
                      <span className={`inline-block h-1.5 w-1.5 rounded-full ${badge.dot}`} />
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
              </RBAC>
            ) : (
              <span
                className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium leading-none ${badge.bg} ${badge.text}`}
              >
                <span className={`inline-block h-1.5 w-1.5 rounded-full ${badge.dot}`} />
                {form.status}
              </span>
            )}
            <span className="text-[11px] text-gray-500 dark:text-gray-400">
              v{String(form.version)}
            </span>
            {form.family_revision_count !== undefined && form.family_revision_count > 1 && (
              <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[11px] font-medium text-gray-600 dark:text-gray-300">
                <GitBranch className="h-2.5 w-2.5" />
                {form.family_revision_count}
              </span>
            )}
          </div>
        </div>

        {/* Actions cluster — icon buttons + kebab */}
        <div className="flex shrink-0 items-center gap-0.5">
          <Tooltip>
            <TooltipTrigger asChild>
              <button
                type="button"
                onClick={() => onView(form)}
                className="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 touch-manipulation"
                aria-label="Preview form"
              >
                <Eye className="h-[18px] w-[18px]" aria-hidden="true" />
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
                    onClick={() => onStatusChange(form, "Active")}
                    className="inline-flex h-9 w-9 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 transition-colors hover:bg-emerald-100 hover:text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20 dark:hover:text-emerald-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 touch-manipulation"
                    aria-label="Activate form"
                  >
                    <Play className="h-[18px] w-[18px]" aria-hidden="true" />
                  </button>
                </TooltipTrigger>
                <TooltipContent>Activate</TooltipContent>
              </Tooltip>
            </RBAC>
          )}

          {canEditInPlace ? (
            <RBAC permission="forms.manage">
              <Tooltip>
                <TooltipTrigger asChild>
                  <Link
                    href={`/admin/forms/${form.id}/edit`}
                    className="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 touch-manipulation"
                    aria-label="Edit form"
                  >
                    <Pencil className="h-[18px] w-[18px]" aria-hidden="true" />
                  </Link>
                </TooltipTrigger>
                <TooltipContent>Edit</TooltipContent>
              </Tooltip>
            </RBAC>
          ) : (
            <Tooltip>
              <TooltipTrigger asChild>
                <span
                  className="inline-flex h-9 w-9 cursor-default items-center justify-center rounded-md text-gray-400/50 dark:text-gray-500/50"
                  aria-label="Editing disabled — form is read-only"
                >
                  <Lock className="h-[18px] w-[18px]" aria-hidden="true" />
                </span>
              </TooltipTrigger>
              <TooltipContent>Read-only — create a revision to keep version history, or duplicate a separate copy</TooltipContent>
            </Tooltip>
          )}

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 touch-manipulation"
                aria-label="More actions"
              >
                <Settings className="h-[18px] w-[18px]" aria-hidden="true" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
              <DropdownMenuItem asChild>
                <Link href={`/reports?form_id=${form.id}`} className="cursor-pointer">
                  <FileBarChart className="mr-2 h-4 w-4" />
                  View Report
                </Link>
              </DropdownMenuItem>

              <RBAC permission="forms.manage">
                {(form.is_locked || form.status === "Active") && (
                  <DropdownMenuItem onClick={() => onRevision(form.id)} className="cursor-pointer">
                    <GitBranch className="mr-2 h-4 w-4" />
                    Create Revision
                  </DropdownMenuItem>
                )}

                <DropdownMenuItem onClick={() => onDuplicate(form.id)} className="cursor-pointer">
                  <Copy className="mr-2 h-4 w-4" />
                  Duplicate Copy
                </DropdownMenuItem>
              </RBAC>

              <RBAC permission="forms.manage">
                <DropdownMenuSeparator />
                <DropdownMenuLabel className="text-[11px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                  Visibility
                </DropdownMenuLabel>
                <div className="px-2 pb-1">
                  <select
                    className="w-full cursor-pointer rounded-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-2 py-1 text-xs text-gray-900 dark:text-gray-100 outline-none transition-colors focus:ring-1 focus:ring-gray-400"
                    value={form.permission_id ?? ""}
                    onChange={(e) => onVisibilityChange(form.id, e.target.value || null)}
                  >
                    <option value="">Hidden</option>
                    {permOptions.map((opt) => (
                      <option key={opt.id} value={opt.id}>
                        {opt.label}
                      </option>
                    ))}
                  </select>
                </div>
              </RBAC>

              {form.status !== "Archived" && (
                <RBAC permission="forms.manage">
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    variant="destructive"
                    onClick={() => onArchive(form)}
                    className="cursor-pointer"
                    data-tour="forms-archive"
                  >
                    <ArchiveIcon className="mr-2 h-4 w-4" />
                    Archive
                  </DropdownMenuItem>
                </RBAC>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      {/* Body */}
      <div className="flex flex-1 flex-col px-5 pt-3.5 pb-5">
        {desc ? (
          <p className="line-clamp-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
            {desc}
          </p>
        ) : (
          <p className="text-sm italic text-gray-400 dark:text-gray-500/70">No description</p>
        )}

        {/* Footer meta */}
        <div className="mt-auto flex items-center gap-3 border-t border-gray-100 dark:border-gray-700/60 pt-3.5 mt-3.5">
          <span className="text-xs font-medium text-gray-500 dark:text-gray-400">{categoryLabel}</span>
          <span className="text-xs text-gray-300 dark:text-gray-600">•</span>
          <span className="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
            <EyeOff className="h-3 w-3" aria-hidden="true" />
            {visibilityLabel}
          </span>
        </div>
      </div>
    </div>
  );
}
