import React from "react";
import { Link } from "@inertiajs/react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Plus, Search } from "lucide-react";
import { motion, useReducedMotion } from "framer-motion";

export type QuickActionsFilterStatus = "all" | "pending" | "approved" | "rejected" | "revision";

interface QuickActionsProps {
  search: string;
  status: QuickActionsFilterStatus;
  onSearchChange: (value: string) => void;
  onStatusChange: (value: QuickActionsFilterStatus) => void;
  routeNamespace?: "student-dashboard" | "staff-dashboard";
}

const STATUS_OPTIONS: { value: QuickActionsFilterStatus; label: string; underlineClass: string }[] = [
  { value: "all", label: "All Status", underlineClass: "bg-gray-900 dark:bg-gray-100" },
  { value: "pending", label: "Pending", underlineClass: "bg-amber-400 dark:bg-amber-500" },
  { value: "approved", label: "Approved", underlineClass: "bg-emerald-400 dark:bg-emerald-500" },
  { value: "rejected", label: "Rejected", underlineClass: "bg-red-400 dark:bg-red-500" },
  { value: "revision", label: "Needs Revision", underlineClass: "bg-orange-400 dark:bg-orange-500" },
];

export default function QuickActions({
  search,
  status,
  onSearchChange,
  onStatusChange,
  routeNamespace = "student-dashboard",
}: QuickActionsProps) {
  const shouldReduceMotion = useReducedMotion();

  return (
    <div
      className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-y-3 gap-x-4"
      data-tour="student-actions"
    >
      {/* Left: Search + Filters */}
      <div className="flex flex-col xl:flex-row gap-3 w-full lg:max-w-3xl">
        {/* Search Input */}
        <div className="relative flex-1 xl:max-w-xs group">
          <Search
            className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500 pointer-events-none"
            aria-hidden="true"
          />
          <Input
            type="search"
            placeholder="Search submissions..."
            aria-label="Search submissions"
            className="w-full pl-9 pr-3 h-9 sm:h-10 text-sm border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
            value={search}
            onChange={(e) => onSearchChange(e.target.value)}
            autoComplete="off"
            spellCheck={false}
          />
        </div>

        {/* Status filter — underline tab style */}
        <div
          className="flex items-end border-b border-gray-200 dark:border-gray-800 overflow-x-auto pb-px scrollbar-hide"
          role="group"
          aria-label="Filter by status"
        >
          {STATUS_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => onStatusChange(opt.value)}
              aria-pressed={status === opt.value}
              className={`relative h-9 sm:h-10 px-3.5 text-xs font-medium transition-colors touch-manipulation whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-1 ${
                status === opt.value
                  ? "text-gray-900 dark:text-gray-100"
                  : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
              }`}
            >
              {opt.label}
              {status === opt.value && (
                <motion.span
                  layoutId="student-submissions-status-tab"
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
      </div>

      {/* Right: Primary Action Button */}
      <div className="flex lg:flex-shrink-0 mt-1 lg:mt-0">
        <Button
          asChild
          className="h-9 sm:h-10 px-4 sm:px-5 gap-2 text-sm w-full sm:w-auto touch-manipulation"
        >
          <Link href={route(`${routeNamespace}.forms.index`)}>
            <Plus className="h-4 w-4" aria-hidden="true" />
            <span>New Request</span>
          </Link>
        </Button>
      </div>
    </div>
  );
}
