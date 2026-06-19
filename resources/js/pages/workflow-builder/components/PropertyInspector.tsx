import * as React from "react";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { MousePointerClick, Settings2 } from "lucide-react";
import type { Node } from "reactflow";
import StepPropertiesPanel from "./StepPropertiesPanel";
import BranchPropertiesPanel from "./BranchPropertiesPanel";
import { isBranchContainer } from "../utils/branchLayout";

interface PropertyInspectorProps {
  selectedNode: Node | null;
  updateNode: (id: string, updates: any) => void;
  removeNode: (id: string) => void;
  users: any[];
  workflowType: string;
  formFields: any[];
  formState: any;
  setFormState: (state: any) => void;
  nodes: Node[];
}

export default function PropertyInspector({
  selectedNode,
  updateNode,
  removeNode,
  users,
  workflowType,
  formFields,
  formState,
  setFormState,
  nodes,
}: PropertyInspectorProps) {
  const isBranch = isBranchContainer(selectedNode);
  const isStartNode = selectedNode?.id === "start";

  if (!selectedNode) {
    return (
      <div className="flex h-full flex-col items-center justify-center gap-3 p-8 text-center">
        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted/60">
          <MousePointerClick className="h-5 w-5 text-muted-foreground" />
        </div>
        <div>
          <p className="text-sm font-medium text-foreground">No step selected</p>
          <p className="mt-1 text-xs text-muted-foreground leading-relaxed">
            Click a step on the canvas to configure it here.
          </p>
        </div>
      </div>
    );
  }

  if (isStartNode) {
    return (
      <div className="flex h-full flex-col">
        {/* Panel header */}
        <div className="flex items-center gap-2 border-b border-border/70 px-4 py-3">
          <Settings2 className="h-4 w-4 text-muted-foreground" />
          <h3 className="text-sm font-semibold text-foreground">Workflow Properties</h3>
        </div>

        <div className="flex-1 overflow-y-auto p-4">
          <div className="space-y-1.5">
            <Label htmlFor="workflow-description" className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
              Description
            </Label>
            <Textarea
              id="workflow-description"
              value={formState.description}
              onChange={(e) => setFormState({ ...formState, description: e.target.value })}
              placeholder="Describe the purpose and flow of this workflow…"
              className="min-h-[120px] resize-none text-sm"
            />
          </div>
        </div>
      </div>
    );
  }

  // When node is selected, show node-specific properties
  return (
    <div className="flex h-full flex-col">
      {/* Panel header */}
      <div className="flex items-center gap-2 border-b border-border/70 px-4 py-3">
        <Settings2 className="h-4 w-4 text-muted-foreground" />
        <div className="min-w-0 flex-1">
          <h3 className="text-sm font-semibold text-foreground">
            {isBranch ? "Branch Properties" : "Step Properties"}
          </h3>
          <p className="truncate text-xs text-muted-foreground">
            {selectedNode.data?.label || "Unnamed"}
          </p>
        </div>
      </div>

      {/* Node Properties */}
      <div className="flex-1 overflow-y-auto p-4">
        {isBranch ? (
          <BranchPropertiesPanel
            selectedNode={selectedNode}
            updateNode={updateNode}
            removeNode={removeNode}
            nodes={nodes}
          />
        ) : (
          <StepPropertiesPanel
            selectedNode={selectedNode}
            updateNode={updateNode}
            removeNode={removeNode}
            users={users}
            workflowType={workflowType}
            formFields={formFields}
          />
        )}
      </div>
    </div>
  );
}
