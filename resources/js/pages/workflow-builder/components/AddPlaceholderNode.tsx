import * as React from "react";
import type { NodeProps } from "reactflow";
import { CheckCircle2, GitBranch, Plus } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

/**
 * Plus-button insertion point between workflow nodes.
 *
 * Supports two interactions:
 *  1. **Click** — calls `data.onAdd()` to insert a default step at this position.
 *  2. **Drag-over from palette** — parent toggles `data.active` to highlight, then
 *     inserts on drop via `addNode(type, data.insertAfterId)`.
 */
export default function AddPlaceholderNode({ data }: NodeProps<any>) {
  const active = Boolean(data?.active);
  const workflowType = String(data?.workflowType ?? "Sequential");
  const onAdd = data?.onAdd as (() => void) | undefined;
  const onAddAction = data?.onAddAction as (() => void) | undefined;
  const onAddBranch = data?.onAddBranch as (() => void) | undefined;
  const [open, setOpen] = React.useState(false);

  const triggerButtonClass = [
    "flex h-9 w-9 items-center justify-center rounded-full border-2 border-dashed transition-[border-color,background-color,color,transform,box-shadow] duration-150",
    "hover:border-primary hover:bg-primary/10 hover:text-primary hover:scale-110",
    "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50",
    active
      ? "scale-110 border-primary bg-primary/10 text-primary shadow-md"
      : "border-foreground/25 bg-card text-foreground/70",
  ].join(" ");

  if (workflowType === "Parallel") {
    return (
      <div className="h-full w-full flex items-center justify-center">
        <Popover open={open} onOpenChange={setOpen}>
          <PopoverTrigger asChild>
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
              }}
              className={triggerButtonClass}
              aria-label="Add step here"
              title="Choose Action or Branch"
            >
              <Plus className="h-4 w-4" />
            </button>
          </PopoverTrigger>
          <PopoverContent className="w-64 p-2" align="center" side="bottom" sideOffset={8}>
            <div className="space-y-1.5">
              <button
                type="button"
                onClick={() => {
                  onAddAction?.();
                  setOpen(false);
                }}
                className="group w-full rounded-lg border border-border/70 bg-background p-2.5 text-left transition-[border-color,background-color] hover:border-primary/60 hover:bg-muted/40"
              >
                <div className="flex items-start gap-2.5">
                  <div className="rounded-md border border-border/70 bg-card p-1.5 transition-[border-color,background-color] group-hover:border-primary/50">
                    <CheckCircle2 className="h-4 w-4" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="text-xs font-medium text-slate-900 dark:text-slate-100">Action</div>
                    <div className="mt-0.5 text-[10px] leading-tight text-muted-foreground">
                      Add an approval/action step
                    </div>
                  </div>
                </div>
              </button>

              <button
                type="button"
                onClick={() => {
                  onAddBranch?.();
                  setOpen(false);
                }}
                className="group w-full rounded-lg border border-border/70 bg-background p-2.5 text-left transition-[border-color,background-color] hover:border-primary/60 hover:bg-muted/40"
              >
                <div className="flex items-start gap-2.5">
                  <div className="rounded-md border border-border/70 bg-card p-1.5 transition-[border-color,background-color] group-hover:border-primary/50">
                    <GitBranch className="h-4 w-4" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="text-xs font-medium text-slate-900 dark:text-slate-100">Branch</div>
                    <div className="mt-0.5 text-[10px] leading-tight text-muted-foreground">
                      Create parallel paths
                    </div>
                  </div>
                </div>
              </button>
            </div>
          </PopoverContent>
        </Popover>
      </div>
    );
  }

  return (
    <div className="h-full w-full flex items-center justify-center">
      {/* Plus circle */}
      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation();
          onAdd?.();
        }}
        className={triggerButtonClass}
        aria-label="Add step here"
        title="Click to add a step, or drag a node here"
      >
        <Plus className="h-4 w-4" />
      </button>
    </div>
  );
}
