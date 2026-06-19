import * as React from "react";
import { Link } from "@inertiajs/react";
import { motion, useReducedMotion } from "framer-motion";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { Plus, ShieldCheck, Search, LayoutList, LayoutGrid } from "lucide-react";

type StatusFilter = "all" | "active" | "inactive" | "archive";

const STATUS_TABS: { value: StatusFilter; label: string; activeClass: string }[] = [
  { value: "all", label: "All", activeClass: "bg-foreground" },
  { value: "active", label: "Active", activeClass: "bg-emerald-500" },
  { value: "inactive", label: "Inactive", activeClass: "bg-amber-500" },
  { value: "archive", label: "Archived", activeClass: "bg-rose-500" },
];

type Props = {
  searchValue: string;
  onSearchChange: (v: string) => void;
  statusFilter: StatusFilter;
  onStatusChange: (v: StatusFilter) => void;
  onAddUser: () => void;
  viewMode: "list" | "grid";
  onViewModeChange: (mode: "list" | "grid") => void;
};

export default function UserToolbar({
  searchValue,
  onSearchChange,
  statusFilter,
  onStatusChange,
  onAddUser,
  viewMode,
  onViewModeChange,
}: Props) {
  const shouldReduceMotion = useReducedMotion();
  return (
    <div className="flex flex-wrap items-center gap-3" data-tour="users-toolbar">
      {/* Search */}
      <div className="relative min-w-0 flex-1 basis-40 sm:flex-none sm:w-64">
        <Search
          className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden="true"
        />
        <Input
          value={searchValue}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder="Search users…"
          aria-label="Search users"
          className="h-9 w-full pl-9 text-sm"
        />
      </div>

      {/* Status tabs */}
      <nav
        role="tablist"
        aria-label="Filter by status"
        className="flex items-center overflow-x-auto"
      >
        {STATUS_TABS.map((tab) => {
          const isActive = statusFilter === tab.value;
          return (
            <button
              key={tab.value}
              type="button"
              role="tab"
              aria-selected={isActive}
              onClick={() => onStatusChange(tab.value)}
              className={`relative whitespace-nowrap px-3 py-2 text-sm font-medium motion-safe:transition-colors touch-manipulation ${
                isActive
                  ? "text-foreground"
                  : "text-muted-foreground hover:text-foreground"
              }`}
            >
              {tab.label}
              {isActive && (
                <motion.span
                  layoutId="user-status-tab-indicator"
                  className={`absolute bottom-0 left-0 right-0 h-0.5 rounded-full ${tab.activeClass}`}
                  transition={
                    shouldReduceMotion
                      ? { duration: 0 }
                      : { type: "spring", stiffness: 500, damping: 30 }
                  }
                />
              )}
            </button>
          );
        })}
      </nav>

      {/* Spacer */}
      <div className="hidden flex-1 sm:block" />

      {/* View toggle */}
      <div className="flex items-center gap-1">
        <Tooltip>
          <TooltipTrigger asChild>
            <button
              type="button"
              onClick={() => onViewModeChange("list")}
              aria-label="List view"
              aria-pressed={viewMode === "list"}
              className={`inline-flex h-8 w-8 items-center justify-center rounded-md motion-safe:transition-colors touch-manipulation ${
                viewMode === "list"
                  ? "bg-accent text-foreground"
                  : "text-muted-foreground hover:bg-accent hover:text-foreground"
              }`}
            >
              <LayoutList className="h-4 w-4" />
            </button>
          </TooltipTrigger>
          <TooltipContent>List view</TooltipContent>
        </Tooltip>
        <Tooltip>
          <TooltipTrigger asChild>
            <button
              type="button"
              onClick={() => onViewModeChange("grid")}
              aria-label="Grid view"
              aria-pressed={viewMode === "grid"}
              className={`inline-flex h-8 w-8 items-center justify-center rounded-md motion-safe:transition-colors touch-manipulation ${
                viewMode === "grid"
                  ? "bg-accent text-foreground"
                  : "text-muted-foreground hover:bg-accent hover:text-foreground"
              }`}
            >
              <LayoutGrid className="h-4 w-4" />
            </button>
          </TooltipTrigger>
          <TooltipContent>Grid view</TooltipContent>
        </Tooltip>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-2">
        <Button size="sm" className="h-9 px-4 touch-manipulation" onClick={onAddUser}>
          <Plus className="mr-1.5 h-4 w-4" aria-hidden="true" />
          Add User
        </Button>
        <Button variant="outline" size="sm" asChild className="h-9 touch-manipulation">
          <Link href={route("user-management.roles.index")}>
            <ShieldCheck className="mr-1.5 h-4 w-4" aria-hidden="true" />
            <span className="hidden sm:inline">Manage </span>Roles
          </Link>
        </Button>
      </div>
    </div>
  );
}
