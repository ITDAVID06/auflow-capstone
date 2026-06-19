import React from "react";
import { Link } from "@inertiajs/react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Search, Plus, LayoutList, LayoutGrid } from "lucide-react";
import { motion, useReducedMotion } from "framer-motion";
import { RBAC } from "@/components/RBAC";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

export type FormFilterStatus = "All" | "Active" | "Inactive" | "Archived";

interface FormToolbarProps {
  search: string;
  status: FormFilterStatus;
  onSearchChange: (search: string) => void;
  onSearchSubmit: () => void;
  onStatusChange: (status: FormFilterStatus) => void;
  viewMode?: "list" | "grid";
  onViewModeChange?: (mode: "list" | "grid") => void;
}

const STATUS_OPTIONS: { value: FormFilterStatus; label: string; underlineClass: string }[] = [
  { value: "All", label: "All", underlineClass: "bg-gray-900 dark:bg-gray-100" },
  { value: "Active", label: "Active", underlineClass: "bg-emerald-400 dark:bg-emerald-500" },
  { value: "Inactive", label: "Inactive", underlineClass: "bg-amber-400 dark:bg-amber-500" },
  { value: "Archived", label: "Archived", underlineClass: "bg-gray-400 dark:bg-gray-500" },
];

export default function FormToolbar({
  search,
  status,
  onSearchChange,
  onSearchSubmit,
  onStatusChange,
  viewMode,
  onViewModeChange,
}: FormToolbarProps) {
  const shouldReduceMotion = useReducedMotion();

  return (
    <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
      {/* Search */}
      <div className="relative min-w-0 flex-1 sm:max-w-72">
        <Search
          className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500"
          aria-hidden="true"
        />
        <Input
          type="search"
          placeholder="Search forms…"
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && onSearchSubmit()}
          className="h-9 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 pl-9 text-sm shadow-sm transition-colors focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
          data-tour="forms-search"
          aria-label="Search forms"
          autoComplete="off"
          spellCheck={false}
        />
      </div>

      {/* Status filter — underline tab style */}
      <div
        className="flex items-end border-b border-gray-200 dark:border-gray-800"
        role="group"
        aria-label="Filter by status"
      >
        {STATUS_OPTIONS.map((opt) => (
          <button
            key={opt.value}
            type="button"
            onClick={() => onStatusChange(opt.value)}
            aria-pressed={status === opt.value}
            className={`relative h-9 px-3.5 text-xs font-medium transition-colors touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-1 ${
              status === opt.value
                ? "text-gray-900 dark:text-gray-100"
                : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
            }`}
          >
            {opt.label}
            {status === opt.value && (
              <motion.span
                layoutId="status-tab-indicator"
                className={`absolute inset-x-0 bottom-0 h-0.5 ${opt.underlineClass}`}
                transition={
                  shouldReduceMotion
                    ? { duration: 0 }
                    : { type: "spring", duration: 0.3, bounce: 0.15 }
                }
              />
            )}
          </button>
        ))}
      </div>

      {/* Spacer */}
      <div className="flex-1" />

      {/* View mode toggle */}
      {onViewModeChange && viewMode && (
        <div
          className="flex items-center gap-0.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-0.5"
          role="group"
          aria-label="View mode"
        >
          <Tooltip>
            <TooltipTrigger asChild>
              <button
                type="button"
                onClick={() => onViewModeChange("list")}
                className={`inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 ${
                  viewMode === "list"
                    ? "bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm"
                    : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                }`}
                aria-label="List view"
                aria-pressed={viewMode === "list"}
              >
                <LayoutList className="h-4 w-4" aria-hidden="true" />
              </button>
            </TooltipTrigger>
            <TooltipContent>List view</TooltipContent>
          </Tooltip>
          <Tooltip>
            <TooltipTrigger asChild>
              <button
                type="button"
                onClick={() => onViewModeChange("grid")}
                className={`inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 ${
                  viewMode === "grid"
                    ? "bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm"
                    : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                }`}
                aria-label="Grid view"
                aria-pressed={viewMode === "grid"}
              >
                <LayoutGrid className="h-4 w-4" aria-hidden="true" />
              </button>
            </TooltipTrigger>
            <TooltipContent>Grid view</TooltipContent>
          </Tooltip>
        </div>
      )}

      {/* New Form */}
      <RBAC permission="forms.create">
        <Button
          asChild
          size="sm"
          className="h-9 rounded-md px-4 shadow-sm"
          data-tour="forms-create"
        >
          <Link href="/admin/forms/create">
            <Plus className="mr-1.5 h-4 w-4" />
            New Form
          </Link>
        </Button>
      </RBAC>
    </div>
  );
}

