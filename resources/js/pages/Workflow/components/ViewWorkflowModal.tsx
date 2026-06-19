import React from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogTitle,
} from "@/components/ui/dialog";
import { GitBranch, LayoutList } from "lucide-react";
import ReactFlow, { Background, Controls, Node, Edge } from "reactflow";
import "reactflow/dist/style.css";
import type { Workflow, WorkflowStep } from "../types/workflow.types";

// same renderers as the builder
import StartNode from "@/pages/workflow-builder/components/StartNode";
import StepNode from "@/pages/workflow-builder/components/StepNode";
import BranchContainerNode from "@/pages/workflow-builder/components/BranchContainerNode";
import StepCard from "@/pages/workflow-builder/components/StepCard";

// bring in the builder’s layout logic
import { layoutContainers } from "@/pages/workflow-builder/utils/branchLayout";

const STEP_W_TOP = 240;
const STEP_W_CHILD = 200;
const STEP_H = 104; // approximate; only used for legacy hit-test

function measureNode(n: any): { w: number; h: number } {
  const w =
    typeof n.width === "number"
      ? n.width
      : typeof n.style?.width === "number"
      ? n.style.width
      : (n.parentNode ? STEP_W_CHILD : STEP_W_TOP);
  const h =
    typeof n.height === "number"
      ? n.height
      : typeof n.style?.height === "number"
      ? n.style.height
      : STEP_H;
  return { w: Number(w), h: Number(h) };
}

const getApproverName = (s?: WorkflowStep) => {
  // Check if step has multiple approvers via approvers array
  const approvers = (s as any)?.approvers || [];
  const hasApprovers = approvers.length > 0 && approvers.some((a: any) => a.account_id);

  if (hasApprovers) {
    const assignedApprovers = approvers.filter((a: any) => a.account_id);
    if (assignedApprovers.length === 1) {
      // Get user_name from approver record or construct from user.profile
      const approver = assignedApprovers[0];
      const userName = approver.user_name ||
        (approver.user?.profile?.first_name || approver.user?.profile?.last_name
          ? `${approver.user.profile.first_name ?? ""} ${approver.user.profile.last_name ?? ""}`.trim()
          : "Assigned");
      return userName;
    } else if (assignedApprovers.length > 1) {
      // Multiple approvers - show all names with (OR)
      const names = assignedApprovers.map((a: any) => {
        return a.user_name ||
          (a.user?.profile?.first_name || a.user?.profile?.last_name
            ? `${a.user.profile.first_name ?? ""} ${a.user.profile.last_name ?? ""}`.trim()
            : "Unknown");
      }).join(", ");
      return `${names} (OR)`;
    }
  }

  // Fallback to legacy assigned_user for backwards compatibility
  const p = s?.assigned_user?.profile;
  return p?.first_name || p?.last_name
    ? `${p?.first_name ?? ""} ${p?.last_name ?? ""}`.trim()
    : "Unassigned";
};

const norm = (s: string) => s.trim().toLowerCase();

const findStepBy = (n: Node, steps: WorkflowStep[]) => {
  const d: any = n.data ?? {};
  const byId = new Map<number, WorkflowStep>();
  const byName = new Map<string, WorkflowStep>();
  for (const s of steps) {
    byId.set(Number(s.id), s);
    byName.set(norm(s.step_name), s);
  }
  const stepId = Number((d as any)?.step_id ?? (d as any)?.stepId);
  if (!Number.isNaN(stepId) && byId.has(stepId)) return byId.get(stepId);

  const idNum = Number(n.id);
  if (!Number.isNaN(idNum) && byId.has(idNum)) return byId.get(idNum);

  const label =
    (typeof (d as any).step_name === "string" && (d as any).step_name) ||
    (typeof (d as any).label === "string" && (d as any).label) ||
    "";
  return byName.get(norm(label));
};

const dedupeById = <T extends { id: string }>(arr: T[]) => {
  const seen = new Set<string>();
  const out: T[] = [];
  for (const it of arr) if (!seen.has(it.id)) (seen.add(it.id), out.push(it));
  return out;
};

const isUiOnlyNode = (node: any): boolean => {
  const id = String(node?.id ?? "");
  const type = String(node?.type ?? "");

  return (
    id.startsWith("__plus__") ||
    id.startsWith("__end__") ||
    type === "addPlaceholder" ||
    type === "endNode"
  );
};

export default function ViewWorkflowModal({
  open,
  onClose,
  workflow,
}: {
  open: boolean;
  onClose: () => void;
  workflow: Workflow | null;
}) {
  if (!workflow) return null;

  const ws = workflow.workflow_settings ?? { nodes: [], edges: [] };
  const steps = workflow.steps ?? [];
  const containers = ws.containers ?? [];

  const canvasNodes = React.useMemo(
    () => (ws.nodes ?? []).filter((node: any) => !isUiOnlyNode(node)),
    [ws.nodes]
  );

  // containers as nodes (authoring metadata)
  const containerNodes: Node[] = React.useMemo(
    () =>
      containers.map((c) => ({
        id: c.id,
        type: "branchContainer",
        position: { x: c.rect?.x ?? 80, y: c.rect?.y ?? 420 },
        data: {
          label: c.label ?? "Branch",
          type: "branchContainer",
          group: c.group ?? 1,
          w: c.rect?.w ?? 360,
          h: c.rect?.h ?? 200,
        },
        selectable: false,
        style: { zIndex: 0 },
      })),
    [containers]
  );

  const fallbackBranchNodes: Node[] = React.useMemo(
    () =>
      canvasNodes
        .filter(
          (node: any) =>
            node.type === "branchContainer" ||
            String(node.data?.type ?? "") === "branchContainer"
        )
        .map((node: any) => ({
          ...node,
          id: String(node.id),
          type: "branchContainer",
          data: {
            ...(node.data ?? {}),
            type: "branchContainer",
            w:
              typeof node.data?.w === "number"
                ? node.data.w
                : typeof node.style?.width === "number"
                ? node.style.width
                : 360,
            h:
              typeof node.data?.h === "number"
                ? node.data.h
                : typeof node.style?.height === "number"
                ? node.style.height
                : 200,
          },
          selectable: false,
          style: { ...(node.style ?? {}), zIndex: 0 },
        })),
    [canvasNodes]
  );

  const previewContainers = containerNodes.length > 0 ? containerNodes : fallbackBranchNodes;

  // parent inference (prefer explicit authoring children; fallback center-in-rect hit-test)
  const parentFor = React.useCallback(
    (node: any): string | undefined => {
      const nid = String(node.id);

      // explicit link from authoring metadata
      const explicit = containers.find((c) => (c.children ?? []).some((cid) => String(cid) === nid));
      if (explicit) return explicit.id;

      // legacy fallback: hit-test the node CENTER against container rects
      const { w, h } = measureNode(node);
      const cx = (node.position?.x ?? 0) + w / 2;
      const cy = (node.position?.y ?? 0) + h / 2;

      for (const c of containers) {
        const r = c.rect ?? { x: 0, y: 0, w: 0, h: 0 };
        if (cx > r.x && cx < r.x + r.w && cy > r.y && cy < r.y + r.h) {
          return c.id;
        }
      }
      return undefined;
    },
    [containers]
  );

  // start + steps (re-parent + width normalization + assignee enrichment)
  const stepAndStart: Node[] = React.useMemo(() => {
    return canvasNodes.flatMap((node: any) => {
      const isStart =
        node.id === "start" ||
        node.type === "start" ||
        node.type === "input" ||
        String(node.data?.type || "") === "form_submitted";

      const isBranch =
        node.type === "branchContainer" ||
        String(node.data?.type || "") === "branchContainer";

      if (isBranch) {
        return [];
      }

      if (isStart) {
        return [{
          id: "start",
          type: "start",
          position: node.position ?? { x: 100, y: 100 },
          data: {
            ...node.data,
            type: "form_submitted",
            label:
              (node.data?.label && String(node.data.label).trim()) ||
              (workflow.workflow_name || "Form Submitted"),
          },
          style: { width: 240, height: 64, zIndex: 1 },
        } as Node];
      }

      const pid = parentFor(node);
      const parent = containers.find((c) => c.id === pid);

      const position = parent
        ? {
            x: (node.position?.x ?? 0) - (parent.rect?.x ?? 0),
            y: (node.position?.y ?? 0) - (parent.rect?.y ?? 0),
          }
        : node.position;

      const matched = findStepBy(node, steps);
      const assigned_name = getApproverName(matched);

      return [{
        ...node,
        id: String(node.id || `node-${Date.now()}-${Math.random()}`),
        type: "step",
        ...(parent ? { parentNode: parent.id, extent: "parent" as const } : {}),
        position,
        data: { ...node.data, assigned_name },
        style: { width: parent ? STEP_W_CHILD : STEP_W_TOP, zIndex: 1 },
      } as Node];
    });
  }, [canvasNodes, containers, parentFor, steps, workflow?.workflow_name]);

  // Apply the SAME layout passes as the builder
  const stagedNodes: Node[] = React.useMemo(() => {
    const base = dedupeById([...previewContainers, ...stepAndStart]);
    const withContainers = layoutContainers(base);
    return withContainers;
  }, [previewContainers, stepAndStart]);

  // Inject StepCard labels like the builder’s CanvasArea
  const nodesWithLabels: Node[] = React.useMemo(() => {
    const isBranch = (n: Node) =>
      n.type === "branchContainer" || (n as any)?.data?.type === "branchContainer";
    const isStart = (n: Node) => n.id === "start" || n.type === "start";

    return stagedNodes.map((n) => {
      if (isBranch(n) || isStart(n)) return n;

      const d: any = n.data ?? {};
      if (d.label && typeof d.label !== "string") return n;

      const stepName =
        d.step_name || (typeof d.label === "string" ? d.label : undefined) || "Step";
      const assigned = d.assigned_user_name || d.assigned_name || null;
      const desc = d.description || null;
      const kind = d.type || "approval";
      const compact = Boolean(n.parentNode);
      const width = compact ? STEP_W_CHILD : STEP_W_TOP;

      return {
        ...n,
        style: { ...(n.style || {}), width },
        data: {
          ...d,
          label: (
            <StepCard
              type={kind}
              title={stepName}
              assignee={assigned}
              description={desc}
              compact={compact}
            />
          ),
        },
      };
    });
  }, [stagedNodes]);

  // Prefer virtual (dashed) container edges if saved, else expanded runtime edges
  const edges: Edge[] = React.useMemo(() => {
    const raw = (ws.virtual_edges && ws.virtual_edges.length ? ws.virtual_edges : ws.edges) ?? [];
    const nodeIds = new Set(stagedNodes.map((node) => String(node.id)));

    return dedupeById(
      raw.filter((edge: any) => nodeIds.has(String(edge.source)) && nodeIds.has(String(edge.target)))
    );
  }, [ws.virtual_edges, ws.edges, stagedNodes]);

  const NODE_TYPES = React.useMemo(
    () => ({ start: StartNode, step: StepNode, branchContainer: BranchContainerNode }),
    []
  );

  const visibleSteps = React.useMemo(
    () =>
      (workflow.steps ?? [])
        .filter((s) => !["Form Submitted", "Notify Completion"].includes(s.step_name))
        .sort((a, b) => a.step_order - b.step_order),
    [workflow.steps]
  );

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="flex max-h-[92vh] w-full max-w-[920px] flex-col gap-0 overflow-hidden rounded-xl p-0 shadow-2xl">
        <DialogTitle className="sr-only">{workflow.workflow_name}</DialogTitle>
        <DialogDescription className="sr-only">
          Preview of the workflow canvas and its steps.
        </DialogDescription>

        {/* ── Header ── */}
        <div className="flex items-center gap-3 border-b border-border/60 bg-card px-6 py-4 pr-14">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <GitBranch className="h-4 w-4" />
          </div>
          <div>
            <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
              Workflow Preview
            </p>
            <h2 className="text-base font-bold leading-tight text-foreground">
              {workflow.workflow_name}
            </h2>
          </div>
        </div>

        {/* ── Meta pills ── */}
        <div className="flex flex-wrap items-center gap-2 border-b border-border/40 bg-muted/30 px-6 py-2.5">
          <span className="text-xs text-muted-foreground">Type</span>
          <span className="rounded-full border border-border bg-background px-2.5 py-0.5 text-xs font-medium text-foreground">
            {workflow.workflow_type}
          </span>
          <span className="mx-1 h-3.5 w-px bg-border" />
          <span className="text-xs text-muted-foreground">Status</span>
          <span
            className={[
              "rounded-full border px-2.5 py-0.5 text-xs font-semibold",
              workflow.status === "Active"
                ? "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400"
                : workflow.status === "Draft"
                ? "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400"
                : "border-zinc-200 bg-zinc-100 text-zinc-600 dark:border-zinc-500/30 dark:bg-zinc-500/10 dark:text-zinc-400",
            ].join(" ")}
          >
            {workflow.status}
          </span>
          {workflow.form?.form_name && (
            <>
              <span className="mx-1 h-3.5 w-px bg-border" />
              <span className="text-xs text-muted-foreground">Form</span>
              <span className="rounded-full border border-border bg-background px-2.5 py-0.5 text-xs font-medium text-foreground">
                {workflow.form.form_name}
              </span>
            </>
          )}
        </div>

        {/* ── Canvas ── */}
        <div className="relative h-[360px] w-full overflow-hidden">
          <ReactFlow
            nodes={nodesWithLabels}
            edges={edges}
            nodeTypes={NODE_TYPES}
            fitView
            fitViewOptions={{ padding: 0.35 }}
            minZoom={0.25}
            maxZoom={2}
            nodesDraggable={false}
            nodesConnectable={false}
            elementsSelectable={false}
            panOnDrag={true}
            zoomOnScroll={true}
            zoomOnPinch={true}
            zoomOnDoubleClick={true}
            defaultEdgeOptions={{
              type: "straight",
              animated: false,
              style: {
                strokeWidth: 2,
                strokeLinejoin: "round",
                strokeLinecap: "round",
                shapeRendering: "geometricPrecision",
              },
            }}
            proOptions={{ hideAttribution: true }}
          >
            <Background gap={16} size={1} color="var(--border)" />
            <Controls showInteractive={false} />
          </ReactFlow>
        </div>

        {/* ── Steps list ── */}
        <div className="border-t border-border/60 bg-card px-6 py-4" style={{ maxHeight: 220, overflowY: "auto" }}>
          <div className="mb-3 flex items-center gap-2">
            <LayoutList className="h-3.5 w-3.5 text-muted-foreground" />
            <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
              Steps · {visibleSteps.length}
            </p>
          </div>
          {visibleSteps.length === 0 ? (
            <p className="text-sm text-muted-foreground">No steps defined.</p>
          ) : (
            <ol className="space-y-1.5">
              {visibleSteps.map((s, i) => (
                <li key={s.id} className="flex items-center gap-3">
                  <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold tabular-nums text-primary">
                    {i + 1}
                  </span>
                  <span className="flex-1 truncate text-sm font-medium text-foreground">
                    {s.step_name}
                  </span>
                  <span className="shrink-0 text-xs text-muted-foreground">
                    → {getApproverName(s)}
                  </span>
                </li>
              ))}
            </ol>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
