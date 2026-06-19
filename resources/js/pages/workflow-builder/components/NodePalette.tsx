import * as React from "react";
import { Button } from "@/components/ui/button";
import type { Node } from "reactflow";
import { CheckCircle2, GitBranch } from "lucide-react";

export interface NodePaletteProps {
  paletteKeys: readonly string[];
  addNode: (type: string) => void;
  formState: any;
}

const nodeIcons: Record<string, React.ReactNode> = {
  approval: <CheckCircle2 className="h-4 w-4" />,
  branchContainer: <GitBranch className="h-4 w-4" />,
};

const labelFor: Record<string, string> = {
  approval: "Action",
  branchContainer: "Branch",
};

const descriptionFor: Record<string, string> = {
  approval: "Add an approval/action step",
  branchContainer: "Create parallel paths",
};

const DRAG_MIME = "application/reactflow";

export default function NodePalette({ paletteKeys, addNode, formState }: NodePaletteProps) {
  const workflowType = String(formState?.workflow_type ?? "Sequential");

  const allKeys = React.useMemo(() => {
    if (workflowType === "Sequential") return paletteKeys.filter((k) => k !== "branchContainer");
    return paletteKeys;
  }, [paletteKeys, workflowType]);

  const handleDragStart = (key: string) => (e: React.DragEvent<HTMLDivElement>) => {
    e.dataTransfer.setData(DRAG_MIME, key);
    e.dataTransfer.setData("text/plain", key);
    e.dataTransfer.effectAllowed = "move";
  };

  const handleClick = (key: string) => {
    addNode(key);
  };

  return (
    <div className="flex flex-col rounded-xl border border-border/70 bg-card/95 shadow-lg backdrop-blur-sm overflow-hidden">
      {/* Header */}
      <div className="border-b border-border/70 px-3 py-2">
        <h3 className="flex items-center gap-1.5 text-xs font-semibold tracking-tight text-foreground/90">
          <GitBranch className="h-3.5 w-3.5 text-primary" />
          Node Palette
        </h3>
      </div>

      {/* Node List */}
      <div className="max-h-[220px] overflow-y-auto p-2">
        <div className="space-y-1.5">
          {allKeys.map((key) => (
            <div
              key={key}
              role="button"
              tabIndex={0}
              draggable={true}
              onDragStart={handleDragStart(key)}
              onClick={() => handleClick(key)}
              onKeyDown={(e) => {
                if (e.key === "Enter" || e.key === " ") {
                  e.preventDefault();
                  handleClick(key);
                }
              }}
              className="group cursor-grab rounded-lg border border-border/70 bg-background p-2.5 transition-[border-color,background-color,box-shadow] hover:border-primary/60 hover:bg-muted/40 active:cursor-grabbing focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50"
              aria-label={`${descriptionFor[key] ?? labelFor[key] ?? key} — click or drag onto canvas`}
            >
              <div className="flex items-start gap-2.5">
                <div className="rounded-md border border-border/70 bg-card p-1.5 transition-[border-color,background-color] group-hover:border-primary/50">
                  {nodeIcons[key]}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="font-medium text-xs text-foreground">
                    {labelFor[key] ?? key.charAt(0).toUpperCase() + key.slice(1)}
                  </div>
                  <div className="text-[10px] text-muted-foreground mt-0.5 leading-tight">
                    {descriptionFor[key] ?? "Add to workflow"}
                  </div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Footer Hint */}
      <div className="border-t border-border/70 bg-muted/30 p-2">
        <p className="text-[10px] text-muted-foreground text-center leading-tight">
          Click or drag nodes to add them to the canvas
        </p>
      </div>
    </div>
  );
}
