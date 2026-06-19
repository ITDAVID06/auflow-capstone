import React from "react";
import { Input } from "@/components/ui/input";
import { motion, useReducedMotion } from "framer-motion";
import { Search, LayoutList, LayoutGrid } from "lucide-react";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

export type SubmissionFilterStatus = "all" | "pending" | "approved" | "rejected";

interface SubmissionsToolbarProps {
    search: string;
    status: SubmissionFilterStatus;
    onSearchChange: (value: string) => void;
    onSearchSubmit: () => void;
    onStatusChange: (value: SubmissionFilterStatus) => void;
    viewMode?: "list" | "grid";
    onViewModeChange?: (mode: "list" | "grid") => void;
}

const STATUS_OPTIONS: { value: SubmissionFilterStatus; label: string; underlineClass: string }[] = [
    { value: "all", label: "All", underlineClass: "bg-foreground" },
    { value: "pending", label: "Pending", underlineClass: "bg-amber-400 dark:bg-amber-500" },
    { value: "approved", label: "Approved", underlineClass: "bg-emerald-400 dark:bg-emerald-500" },
    { value: "rejected", label: "Rejected", underlineClass: "bg-rose-400 dark:bg-rose-500" },
];

export default function SubmissionsToolbar({
    search,
    status,
    onSearchChange,
    onSearchSubmit,
    onStatusChange,
    viewMode,
    onViewModeChange,
}: SubmissionsToolbarProps) {
    const shouldReduceMotion = useReducedMotion();

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
            {/* Search */}
            <div className="relative min-w-0 flex-1 sm:max-w-72">
                <Search
                    className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    type="search"
                    placeholder="Search submissions…"
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    onKeyDown={(e) => e.key === "Enter" && onSearchSubmit()}
                    className="h-9 w-full pl-9 text-sm"
                    aria-label="Search submissions"
                    autoComplete="off"
                    spellCheck={false}
                />
            </div>

            {/* Status filter — underline tab style */}
            <div
                className="flex items-end border-b border-border/50"
                role="group"
                aria-label="Filter by status"
            >
                {STATUS_OPTIONS.map((opt) => (
                    <button
                        key={opt.value}
                        type="button"
                        onClick={() => onStatusChange(opt.value)}
                        aria-pressed={status === opt.value}
                        className={`relative h-9 px-3.5 text-xs font-medium transition-colors touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 ${
                            status === opt.value
                                ? "text-foreground"
                                : "text-muted-foreground hover:text-foreground"
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
                    className="flex items-center gap-0.5 rounded-lg border border-border/60 bg-muted/40 p-0.5"
                    role="group"
                    aria-label="View mode"
                >
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                onClick={() => onViewModeChange("list")}
                                className={`inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                                    viewMode === "list"
                                        ? "bg-background text-foreground shadow-sm"
                                        : "text-muted-foreground hover:text-foreground"
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
                                className={`inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                                    viewMode === "grid"
                                        ? "bg-background text-foreground shadow-sm"
                                        : "text-muted-foreground hover:text-foreground"
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
        </div>
    );
}
