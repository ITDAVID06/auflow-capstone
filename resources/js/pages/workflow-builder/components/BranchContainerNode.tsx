import * as React from "react";
import { Handle, Position, type NodeProps } from "reactflow";
import { GitBranch, Plus } from "lucide-react";

const ACCENT = "#1551f1";

export default function BranchContainerNode({ data, selected }: NodeProps<any>) {
  const w = Number(data?.w ?? 360);
  const h = Number(data?.h ?? 200);
  const label = data?.label ?? "Branch";
  const childCount = Number(data?.childCount ?? 0);
  const onAddChild = data?.onAddChild as (() => void) | undefined;
  const [dragOver, setDragOver] = React.useState(false);

  const handleStyle: React.CSSProperties = {
    width: 10,
    height: 10,
    borderRadius: 9999,
    background: ACCENT,
    border: "2px solid #fff",
    boxShadow: "0 0 0 2px rgba(21,81,241,0.25)",
  };

  const handleContainerDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = "move";
    setDragOver(true);
  };

  const handleContainerDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    setDragOver(false);
  };

  const handleContainerDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setDragOver(false);
    const type = e.dataTransfer.getData("application/reactflow") || e.dataTransfer.getData("text/plain");
    if (type && type !== "branchContainer" && onAddChild) {
      onAddChild();
    }
  };

  return (
    <div
      className={[
        "rounded-xl border bg-card shadow-sm transition-[box-shadow,border-color] duration-150",
        selected
          ? "ring-2 ring-offset-1 ring-[--accent] shadow-md border-[--accent]/40"
          : "border-border/70",
        dragOver
          ? "ring-2 ring-primary/30 border-primary/30 shadow-lg"
          : "",
      ].join(" ")}
      style={
        {
          width: w,
          height: h,
          ["--accent" as any]: ACCENT,
        } as React.CSSProperties
      }
      onDragOver={handleContainerDragOver}
      onDragLeave={handleContainerDragLeave}
      onDrop={handleContainerDrop}
    >
      {/* ── Header ─────────────────────────────────────────────────── */}
      <div className="flex items-center justify-between rounded-t-xl border-b border-border/70 bg-gradient-to-r from-indigo-50/80 to-blue-50/60 dark:from-indigo-950/30 dark:to-blue-950/20 px-3 py-2">
        <div className="flex items-center gap-2">
          <div className="flex h-5 w-5 items-center justify-center rounded bg-indigo-100 dark:bg-indigo-900/50">
            <GitBranch className="h-3 w-3 text-indigo-600 dark:text-indigo-400" />
          </div>
          <span className="text-[11px] font-semibold tracking-wide uppercase text-foreground/90">
            {label || "Branch"}
          </span>
          {childCount > 0 && (
            <span className="ml-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/50 px-1 text-[9px] font-bold text-indigo-600 dark:text-indigo-400">
              {childCount}
            </span>
          )}
        </div>
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            onAddChild?.();
          }}
          className="flex h-6 w-6 items-center justify-center rounded-md border border-border/70 bg-background/80 text-muted-foreground transition-colors hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/30 dark:hover:text-indigo-400"
          title="Add action step"
        >
          <Plus className="h-3.5 w-3.5" />
        </button>
      </div>

      {/* ── Body / drop zone ───────────────────────────────────────── */}
      <div className="relative" style={{ height: h - 36 }}>
        {/* Dashed inner zone (visual only — children overlay via ReactFlow) */}
        <div
          className={[
            "absolute inset-3 rounded-lg border border-dashed transition-colors",
            dragOver
              ? "border-primary/50 bg-primary/5"
              : "border-border/50 bg-muted/20",
          ].join(" ")}
        />

        {/* Bottom add-step bar — always visible, sits below child grid area */}
        <div className="absolute inset-x-3 bottom-5 flex items-center justify-center">
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onAddChild?.();
            }}
            className={[
              "flex items-center gap-1.5 rounded-md px-3 py-1 text-[10px] font-medium transition-all",
              "border border-dashed border-border/60 bg-background/70 backdrop-blur-sm",
              "text-muted-foreground hover:text-indigo-600 hover:border-indigo-400/60 hover:bg-indigo-50/60",
              "dark:hover:text-indigo-400 dark:hover:bg-indigo-950/30",
            ].join(" ")}
          >
            <Plus className="h-3 w-3" />
            {childCount === 0 ? "Add step" : "Add another"}
          </button>
        </div>

        {/* Centered empty-state hint when no children */}
        {childCount === 0 && (
          <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
            <p className="text-[11px] text-muted-foreground/60">Drag an action here or click +</p>
          </div>
        )}
      </div>

      <Handle aria-label="Branch In" type="target" position={Position.Left} id="in" style={handleStyle} />
      <Handle aria-label="Branch Out" type="source" position={Position.Right} id="out" style={handleStyle} />
    </div>
  );
}
