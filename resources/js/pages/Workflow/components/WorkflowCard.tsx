import React from "react";
import { Link } from "@inertiajs/react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import {
  Eye,
  Pencil,
  Copy,
  Archive as ArchiveIcon,
  Play,
  RotateCcw,
  Settings,
  GitBranch,
  Link2,
} from "lucide-react";
import { Workflow } from "../types/workflow.types";
import StatusBadge from "./StatusBadge";
import { formatDate } from "@/utils/dateTime";

interface WorkflowCardProps {
  workflow: Workflow;
  onView: (workflow: Workflow) => void;
  onPublish: (id: number) => void;
  onEnable: (id: number) => void;
  onDuplicate: (id: number) => void;
  onArchive: (workflow: Workflow) => void;
  onRefresh: () => void;
}

export default function WorkflowCard({
  workflow,
  onView,
  onPublish,
  onEnable,
  onDuplicate,
  onArchive,
  onRefresh,
}: WorkflowCardProps) {
  const lowerStatus = String(workflow.status).toLowerCase();
  const hasDescription = (workflow.description ?? "").trim().length > 0;
  const description = hasDescription ? workflow.description!.trim() : null;
  const linkedFormLabel = (workflow.form?.form_name ?? "").trim() || "No form bound";
  const updatedLabel = formatDate(workflow.updated_at);

  return (
    <div
      className="group relative flex min-h-[168px] flex-col rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 transition-colors hover:border-gray-300 dark:hover:border-gray-600 hover:bg-gray-50/50 dark:hover:bg-gray-800/30"
      data-tour="workflows-card"
    >
      <div className="flex items-start gap-3 px-5 pt-5 pb-0">
        <div className="min-w-0 flex-1">
          <h3 className="line-clamp-1 text-base font-semibold leading-snug text-gray-900 dark:text-gray-100">
            {workflow.workflow_name}
          </h3>
          <div className="mt-1.5 flex items-center gap-2">
            <StatusBadge
              status={workflow.status}
              workflow={{ id: workflow.id, status: workflow.status }}
              onChanged={onRefresh}
            />
            <span className="text-[11px] text-gray-500 dark:text-gray-400">{workflow.workflow_type}</span>
          </div>
        </div>

        <div className="flex shrink-0 items-center gap-0.5">
          <Tooltip>
            <TooltipTrigger asChild>
              <button
                type="button"
                onClick={() => onView(workflow)}
                className="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 touch-manipulation"
                aria-label="Preview workflow"
              >
                <Eye className="h-[18px] w-[18px]" aria-hidden="true" />
              </button>
            </TooltipTrigger>
            <TooltipContent>Preview</TooltipContent>
          </Tooltip>

          {lowerStatus === "draft" && (
            <Tooltip>
              <TooltipTrigger asChild>
                <button
                  type="button"
                  onClick={() => onPublish(workflow.id)}
                  className="inline-flex h-9 w-9 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 transition-colors hover:bg-emerald-100 hover:text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:bg-emerald-500/20 dark:hover:text-emerald-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/30 touch-manipulation"
                  aria-label="Publish workflow"
                >
                  <Play className="h-[18px] w-[18px]" aria-hidden="true" />
                </button>
              </TooltipTrigger>
              <TooltipContent>Publish</TooltipContent>
            </Tooltip>
          )}

          {lowerStatus === "archived" && (
            <Tooltip>
              <TooltipTrigger asChild>
                <button
                  type="button"
                  onClick={() => onEnable(workflow.id)}
                  className="inline-flex h-9 w-9 items-center justify-center rounded-md bg-blue-50 text-blue-700 transition-colors hover:bg-blue-100 hover:text-blue-800 dark:bg-blue-500/10 dark:text-blue-400 dark:hover:bg-blue-500/20 dark:hover:text-blue-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/30 touch-manipulation"
                  aria-label="Enable workflow"
                >
                  <RotateCcw className="h-[18px] w-[18px]" aria-hidden="true" />
                </button>
              </TooltipTrigger>
              <TooltipContent>Re-enable</TooltipContent>
            </Tooltip>
          )}

          {lowerStatus === "draft" ? (
            <Tooltip>
              <TooltipTrigger asChild>
                <Link
                  href={`/admin/workflows/${workflow.id}/edit`}
                  className="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 touch-manipulation"
                  aria-label="Edit workflow"
                >
                  <Pencil className="h-[18px] w-[18px]" aria-hidden="true" />
                </Link>
              </TooltipTrigger>
              <TooltipContent>Edit</TooltipContent>
            </Tooltip>
          ) : (
            <Tooltip>
              <TooltipTrigger asChild>
                <span
                  className="inline-flex h-9 w-9 cursor-default items-center justify-center rounded-md text-gray-400/50 dark:text-gray-500/50"
                  aria-label={`Cannot edit — workflow is ${workflow.status.toLowerCase()}`}
                >
                  <Pencil className="h-[18px] w-[18px]" aria-hidden="true" />
                </span>
              </TooltipTrigger>
              <TooltipContent>Editing is only available for Draft workflows</TooltipContent>
            </Tooltip>
          )}

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-400 dark:text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 touch-manipulation"
                aria-label="Workflow actions"
              >
                <Settings className="h-[18px] w-[18px]" aria-hidden="true" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
              <DropdownMenuItem className="cursor-pointer" onClick={() => onDuplicate(workflow.id)}>
                <Copy className="mr-2 h-4 w-4" />
                Duplicate
              </DropdownMenuItem>

              {lowerStatus !== "archived" && (
                <DropdownMenuItem
                  variant="destructive"
                  className="cursor-pointer"
                  onClick={() => onArchive(workflow)}
                >
                  <ArchiveIcon className="mr-2 h-4 w-4" />
                  Archive
                </DropdownMenuItem>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <div className="flex flex-1 flex-col px-5 pt-3.5 pb-5">
        {description ? (
          <p className="line-clamp-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{description}</p>
        ) : (
          <p className="text-sm italic text-gray-400 dark:text-gray-500/70">No description</p>
        )}

        <div className="mt-auto mt-3.5 flex items-center gap-3 border-t border-gray-100 dark:border-gray-700/60 pt-3.5">
          <span className="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
            <Link2 className="h-3 w-3" aria-hidden="true" />
            {linkedFormLabel}
          </span>
          <span className="text-xs text-gray-300 dark:text-gray-600" aria-hidden="true">•</span>
          <span className="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
            <GitBranch className="h-3 w-3" aria-hidden="true" />
            Updated: {updatedLabel}
          </span>
        </div>
      </div>
    </div>
  );
}