import * as React from "react";
import { useCallback, useRef, useState, useMemo } from "react";
import ReactFlow, {
  Background,
  useReactFlow,
  type Node,
  type Edge,
  type NodeTypes,
} from "reactflow";
import "reactflow/dist/style.css";
import { ZoomIn, ZoomOut, Maximize2, Lock, Unlock } from "lucide-react";
import { Button } from "@/components/ui/button";
import EmptyState from "@/components/EmptyState";
import { isBranchContainer } from "../utils/branchLayout";
import StepCard from "./StepCard";
import { PLUS_SIZE } from "../utils/guidedLayout";

export interface CanvasAreaProps {
  /** Positioned workflow nodes (Start + steps, from computeLayout) */
  nodes: Node[];
  edges: Edge[];
  /** Plus-button placeholder nodes (from computeLayout) */
  placeholders: Node[];
  /** End marker node (from computeLayout) */
  endNode: Node;
  setSelectedNode: (node: Node | null) => void;
  nodeTypes?: NodeTypes;
  className?: string;
  /** Add a node (type, insertAfterId?) */
  addNode: (type: string, insertAfterId?: string | null) => void;
  /** Callback wired to each placeholder's onAdd */
  onPlaceholderAdd: (insertAfterId: string) => void;
  workflowType: "Sequential" | "Parallel";
  /** Add a child step inside a branch container */
  addChildNode: (containerId: string) => void;
}

interface ApproverData {
  account_id?: number | null;
  user_name?: string | null;
}

interface WorkflowNodeData {
  step_name?: string;
  label?: string;
  description?: string | null;
  type?: string;
  approvers?: ApproverData[];
  assigned_user_name?: string | null;
  insertAfterId?: string;
  childCount?: number;
  onAddChild?: () => void;
  active?: boolean;
  workflowType?: "Sequential" | "Parallel";
  onAdd?: () => void;
  onAddAction?: () => void;
  onAddBranch?: () => void;
  [key: string]: unknown;
}

/** Branded card widths (numeric for stable rendering) */
const CARD_W_TOP = 240;
const CARD_W_CHILD = 200;
const CARD_H_CHILD = 72;

function cn(...cls: Array<string | false | null | undefined>) {
  return cls.filter(Boolean).join(" ");
}

export default function CanvasArea({
  nodes,
  edges,
  placeholders,
  endNode,
  setSelectedNode,
  nodeTypes,
  className,
  addNode,
  onPlaceholderAdd,
  workflowType,
  addChildNode,
}: CanvasAreaProps) {
  const rf = useReactFlow();
  const [isLocked, setIsLocked] = useState(false);
  const [activePlaceholderId, setActivePlaceholderId] = useState<string | null>(null);
  const flowWrapperRef = useRef<HTMLDivElement>(null);

  // Custom control handlers
  const handleZoomIn = useCallback(() => rf.zoomIn({ duration: 200 }), [rf]);
  const handleZoomOut = useCallback(() => rf.zoomOut({ duration: 200 }), [rf]);
  const handleFitView = useCallback(() => rf.fitView({ padding: 0.2, duration: 300 }), [rf]);
  const handleToggleLock = useCallback(() => setIsLocked((prev) => !prev), []);

  // ── Render nodes with branded StepCard labels ──────────────────────────
  const nodesWithLabels = useMemo(
    () =>
      nodes.map((n) => {
        const isContainer = isBranchContainer(n);
        const isStart = n.id === "start" || n.type === "start";
        if (isStart) return n;
        const nodeData = (n.data ?? {}) as WorkflowNodeData;

        // Wire addChildNode callback and child count into branch container data
        if (isContainer) {
          const childCount = nodes.filter((c) => c.parentNode === n.id).length;
          return {
            ...n,
            data: {
              ...nodeData,
              onAddChild: () => addChildNode(n.id),
              childCount,
            },
          };
        }

        const stepName = nodeData.step_name || nodeData.label || "Step";
        const desc = nodeData.description || null;
        const kind = nodeData.type || "approval";
        const compact = Boolean(n.parentNode);
        const width = compact ? CARD_W_CHILD : CARD_W_TOP;

        const approvers = nodeData.approvers ?? [];
        const hasApprovers = approvers.length > 0 && approvers.some((approver) => approver.account_id);

        let assignedDisplay: string | null = null;
        if (hasApprovers) {
          const assigned = approvers.filter((approver) => approver.account_id);
          if (assigned.length === 1) {
            assignedDisplay = assigned[0].user_name || "Assigned";
          } else if (assigned.length > 1) {
            assignedDisplay = `${assigned[0]?.user_name || "Assigned"} +${assigned.length - 1} more OR`;
          }
        } else {
          assignedDisplay = nodeData.assigned_user_name || null;
        }

        const needsAssignment = kind === "approval" || kind === "task";
        const hasError = needsAssignment && !assignedDisplay;

        return {
          ...n,
          style: { ...(n.style || {}), width, ...(compact ? { height: CARD_H_CHILD } : {}) },
          data: {
            ...nodeData,
            label: (
              <StepCard
                type={kind}
                title={stepName}
                assignee={assignedDisplay}
                description={desc}
                compact={compact}
                hasError={hasError}
              />
            ),
          },
        };
      }),
    [nodes, addChildNode]
  );

  // ── Wire onAdd callbacks into placeholder data ─────────────────────────
  const wiredPlaceholders = useMemo(
    () =>
      placeholders.map((p) => ({
        ...p,
        data: {
          ...((p.data ?? {}) as WorkflowNodeData),
          active: activePlaceholderId === p.id,
          workflowType,
          onAdd: () => onPlaceholderAdd(((p.data ?? {}) as WorkflowNodeData).insertAfterId as string),
          onAddAction: () => addNode("approval", ((p.data ?? {}) as WorkflowNodeData).insertAfterId as string),
          onAddBranch: () => addNode("branchContainer", ((p.data ?? {}) as WorkflowNodeData).insertAfterId as string),
        },
      })),
    [placeholders, activePlaceholderId, onPlaceholderAdd, workflowType, addNode]
  );

  // ── Combine all display nodes ──────────────────────────────────────────
  const displayNodes = useMemo(
    () => [...nodesWithLabels, ...wiredPlaceholders, endNode],
    [nodesWithLabels, wiredPlaceholders, endNode]
  );

  const hasNoWorkflowSteps = useMemo(
    () => nodes.every((node) => node.id === "start" || node.type === "start"),
    [nodes],
  );

  // ── Find nearest placeholder for drag-over highlight ───────────────────
  const findNearestPlaceholder = useCallback(
    (point: { x: number; y: number }) => {
      const threshold = 80;
      let nearest: Node | null = null;
      let nearestDist = Number.POSITIVE_INFINITY;

      for (const ph of placeholders) {
        const cx = ph.position.x + PLUS_SIZE / 2;
        const cy = ph.position.y + PLUS_SIZE / 2;
        const dist = Math.hypot(point.x - cx, point.y - cy);
        if (dist < threshold && dist < nearestDist) {
          nearest = ph;
          nearestDist = dist;
        }
      }
      return nearest;
    },
    [placeholders]
  );

  // ── Palette DnD → canvas ───────────────────────────────────────────────
  const handleDragOver = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = "move";

      const bounds = flowWrapperRef.current?.getBoundingClientRect();
      const point = rf.project({
        x: e.clientX - (bounds?.left ?? 0),
        y: e.clientY - (bounds?.top ?? 0),
      });

      const nearest = findNearestPlaceholder(point);
      setActivePlaceholderId(nearest?.id ?? null);
    },
    [rf, findNearestPlaceholder]
  );

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      const type =
        e.dataTransfer.getData("application/reactflow") ||
        e.dataTransfer.getData("text/plain");
      if (!type) return;

      const bounds = flowWrapperRef.current?.getBoundingClientRect();
      const point = rf.project({
        x: e.clientX - (bounds?.left ?? 0),
        y: e.clientY - (bounds?.top ?? 0),
      });

      const nearest = findNearestPlaceholder(point);
      const insertAfterId = ((nearest?.data ?? {}) as WorkflowNodeData).insertAfterId ?? null;
      addNode(type, insertAfterId);

      setActivePlaceholderId(null);
    },
    [addNode, rf, findNearestPlaceholder]
  );

  return (
    <div ref={flowWrapperRef} className={cn("absolute inset-0", className)}>
      <ReactFlow
        nodes={displayNodes}
        edges={edges}
        nodeTypes={nodeTypes}
        selectNodesOnDrag={false}
        deleteKeyCode="Delete"
        multiSelectionKeyCode="Shift"
        onNodeClick={(_, node) => {
          // Ignore clicks on placeholders and end node
          if (String(node.id).startsWith("__plus__") || node.id === "__end__") return;
          setSelectedNode(node);
        }}
        onPaneClick={() => {
          setSelectedNode(null);
          setActivePlaceholderId(null);
        }}
        onDragOver={handleDragOver}
        onDrop={handleDrop}
        onInit={(instance) => {
          requestAnimationFrame(() => {
            instance.fitView({ padding: 0.35 });
            const z = instance.getZoom();
            instance.zoomTo(Math.max(0.2, z * 0.9), { duration: 0 });
          });
        }}
        minZoom={0.2}
        defaultEdgeOptions={{
          type: "smoothstep",
          animated: false,
          style: {
            strokeWidth: 3,
            strokeLinejoin: "round",
            strokeLinecap: "round",
            shapeRendering: "geometricPrecision",
            stroke: "#334155",
          },
        }}
        nodesDraggable={false}
        nodesConnectable={false}
        elementsSelectable={!isLocked}
      >
        <Background gap={18} size={1} className="bg-muted/20" />
      </ReactFlow>

      {/* Custom Controls - Top Right */}
      <div className="absolute top-3 right-3 z-10 flex flex-col gap-1.5">
        <Button
          size="sm"
          variant="secondary"
          onClick={handleZoomIn}
          className="h-8 w-8 p-0 border border-border/70 bg-card shadow-md hover:shadow-lg"
          title="Zoom In"
        >
          <ZoomIn className="h-4 w-4" />
        </Button>
        <Button
          size="sm"
          variant="secondary"
          onClick={handleZoomOut}
          className="h-8 w-8 p-0 border border-border/70 bg-card shadow-md hover:shadow-lg"
          title="Zoom Out"
        >
          <ZoomOut className="h-4 w-4" />
        </Button>
        <Button
          size="sm"
          variant="secondary"
          onClick={handleFitView}
          className="h-8 w-8 p-0 border border-border/70 bg-card shadow-md hover:shadow-lg"
          title="Fit View"
        >
          <Maximize2 className="h-4 w-4" />
        </Button>
        <div className="h-px bg-border/70 my-0.5" />
        <Button
          size="sm"
          variant={isLocked ? "default" : "secondary"}
          onClick={handleToggleLock}
          className={cn(
            "h-8 w-8 p-0 shadow-md hover:shadow-lg border",
            isLocked
              ? "bg-blue-600 hover:bg-blue-700 text-white border-blue-700"
              : "border-border/70 bg-card"
          )}
          title={isLocked ? "Unlock Canvas" : "Lock Canvas"}
        >
          {isLocked ? <Lock className="h-4 w-4" /> : <Unlock className="h-4 w-4" />}
        </Button>
      </div>

    </div>
  );
}
