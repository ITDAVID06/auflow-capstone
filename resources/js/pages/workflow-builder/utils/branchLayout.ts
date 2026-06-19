import type { Node, Edge } from "reactflow";

/** Authoring constants (match your UI): */
const HEADER_H = 36;
const PAD = 16;
const GAP = 12;
const FOOTER_H = 40; // space for "Add Step" button at bottom
const MIN_W = 320;
const MIN_H = 180;
const DEFAULT_CHILD_W = 200;
const DEFAULT_CHILD_H = 72;

export const isBranchContainer = (n?: Pick<Node, "type" | "data"> | null): boolean =>
  !!n && (n.type === "branchContainer" || (n as any).data?.type === "branchContainer");

/** Best-effort measurement of a step node (React Flow sets width/height post-mount). */
const measureStep = (n: Node): { w: number; h: number } => {
  const w =
    typeof (n as any).width === "number"
      ? (n as any).width
      : typeof (n as any)?.style?.width === "number"
      ? (n as any).style.width
      : DEFAULT_CHILD_W;

  const h =
    typeof (n as any).height === "number"
      ? (n as any).height
      : typeof (n as any)?.style?.height === "number"
      ? (n as any).style.height
      : DEFAULT_CHILD_H;

  return { w, h };
};

/**
 * Auto-layout children inside each Branch container in a compact grid and
 * resize the container to fit. Returns a NEW nodes array (non-mutating).
 */
export function layoutContainers(prevNodes: Node[]): Node[] {
  const nodes = [...prevNodes];
  const byId = new Map(nodes.map((n) => [n.id, n]));
  const containers = nodes.filter((n) => isBranchContainer(n));

  if (containers.length === 0) return nodes;

  for (const container of containers) {
    const containerId = container.id;

    // Direct children (already parented)
    const children = nodes.filter((n) => n.parentNode === containerId && !isBranchContainer(n));

    // Nothing to do – enforce minimum size
    if (children.length === 0) {
      if (container.data?.w !== MIN_W || container.data?.h !== MIN_H) {
        container.data = { ...(container.data || {}), w: MIN_W, h: MIN_H } as any;
      }
      continue;
    }

    // Determine a uniform child size (use max to avoid overlap)
    let childW = DEFAULT_CHILD_W;
    let childH = DEFAULT_CHILD_H;
    for (const c of children) {
      const m = measureStep(c);
      childW = Math.max(childW, m.w);
      childH = Math.max(childH, m.h);
    }

    // Choose columns: 1..3 (looks good and compact)
    const count = children.length;
    const cols = Math.min(3, Math.max(1, Math.ceil(Math.sqrt(count))));
    const rows = Math.ceil(count / cols);

    // Compute container size
    const innerW = cols * childW + (cols - 1) * GAP;
    const innerH = rows * childH + (rows - 1) * GAP;

    const targetW = Math.max(MIN_W, PAD * 2 + innerW);
    const targetH = Math.max(MIN_H, HEADER_H + PAD + innerH + PAD + FOOTER_H);

    // Position children in a grid (relative to parent)
    children.forEach((c, i) => {
      const r = Math.floor(i / cols);
      const cIdx = i % cols;
      const x = PAD + cIdx * (childW + GAP);
      const y = HEADER_H + PAD + r * (childH + GAP);

      if (c.position.x !== x || c.position.y !== y) {
        const next = { ...c, position: { x, y } };
        const idx = nodes.findIndex((n) => n.id === c.id);
        nodes[idx] = next;
        byId.set(c.id, next);
      }
    });

    // Apply container width/height to data
    const nextData = { ...(container.data || {}), w: targetW, h: targetH } as any;
    if (nextData.w !== (container.data as any)?.w || nextData.h !== (container.data as any)?.h) {
      const updated = { ...container, data: nextData };
      const idx = nodes.findIndex((n) => n.id === containerId);
      nodes[idx] = updated;
      byId.set(containerId, updated);
    }
  }

  return nodes;
}


function dedupeByPair(edges: Edge[]): Edge[] {
  const seen = new Set<string>();
  const out: Edge[] = [];
  for (const e of edges) {
    const k = `${e.source}->${e.target}`;
    if (seen.has(k)) continue;
    seen.add(k);
    out.push(e);
  }
  return out;
}

/* ============================ SAVE COMPILATION ============================ */

/** Collect authoring-time containers with their rect and children placeholder. */
function extractContainers(all: Node[]) {
  return all
    .filter((n) => isBranchContainer(n))
    .map((c) => {
      const w = Number((c as any).data?.w ?? (c as any).style?.width ?? 360);
      const h = Number((c as any).data?.h ?? (c as any).style?.height ?? 200);
      return {
        id: c.id,
        label: (c as any).data?.label ?? "Branch",
        group: Number((c as any).data?.group ?? 1),
        rect: { x: c.position.x, y: c.position.y, w, h },
        children: [] as string[],
      };
    });
}

/** Hit-test: is node center inside rectangle? */
function isInside(node: Node, rect: { x: number; y: number; w: number; h: number }): boolean {
  const { w, h } = measureStep(node);
  const cx = node.position.x + w / 2;
  const cy = node.position.y + h / 2;
  return cx > rect.x && cx < rect.x + rect.w && cy > rect.y && cy < rect.y + rect.h;
}

/** Expand edges that connect containers into step→step edges. */
function expandEdges(edges: Edge[], containers: any[]): Edge[] {
  const isContainer = (id?: string | null) => !!id && containers.some((c) => c.id === id);
  const kidsOf = (id: string) => containers.find((c) => c.id === id)?.children ?? [];
  const out: Edge[] = [];

  for (const e of edges) {
    const srcC = isContainer(e.source);
    const tgtC = isContainer(e.target);

    if (!srcC && !tgtC) {
      out.push({ ...e, data: undefined, style: (e as any).data?.virtual ? undefined : e.style });
      continue;
    }
    if (srcC && !tgtC) {
      for (const k of kidsOf(e.source!)) {
        out.push({ ...e, id: `${k}->${e.target}`, source: k, target: e.target!, data: undefined, style: undefined });
      }
      continue;
    }
    if (!srcC && tgtC) {
      for (const k of kidsOf(e.target!)) {
        out.push({ ...e, id: `${e.source}->${k}`, source: e.source!, target: k, data: undefined, style: undefined });
      }
      continue;
    }
    // srcC && tgtC
    for (const a of kidsOf(e.source!)) {
      for (const b of kidsOf(e.target!)) {
        out.push({ ...e, id: `${a}->${b}`, source: a, target: b, data: undefined, style: undefined });
      }
    }
  }

  return dedupeByPair(out);
}

/** Public: turn authoring nodes/edges into runtime graph + keep authoring meta. */
export function compileForSave(
  rfNodes: Node[],
  rfEdges: Edge[],
  formState?: {
    workflow_name: string;
    workflow_type: "Sequential" | "Parallel";
    description: string;
    form_id: string | number;
    status: "draft" | "active" | "archive";
  }
) {
  const containers = extractContainers(rfNodes);
  // Filter out "start" node - it's a UI helper, not an actual workflow step
  const steps = rfNodes.filter((n) => !isBranchContainer(n) && n.id !== "start");

  // assign children by parenting or geometry
  for (const c of containers) {
    for (const s of steps) {
      const inByParent = (s as any).parentNode === c.id;
      const sAbs: Node = (s as any).parentNode
        ? ({
            ...s,
            position: {
              x: s.position.x + c.rect.x,
              y: s.position.y + c.rect.y,
            },
          } as Node)
        : s;

      const inByGeom = isInside(sAbs, c.rect);
      if (inByParent || inByGeom) c.children.push(s.id);
    }
  }

  const nodesOut: Node[] = steps.map((n) => {
    const parent = containers.find((c) => c.children.includes(n.id));
    return {
      ...n,
      parentNode: undefined,
      extent: undefined,
      data: {
        ...(n.data as any),
        assigned_account_id: (n as any).data?.assigned_account_id ?? null,
        step_group: parent ? parent.group : (n as any).data?.step_group ?? null,
        watch_fields: Array.isArray((n as any).data?.watch_fields) ? (n as any).data.watch_fields : [],
      },
    };
  });

  const edgesOut = dedupeByPair(expandEdges(rfEdges, containers));

  const authoring = {
    containers,
    virtual_edges: rfEdges.filter((e: any) => e?.data?.virtual),
  };

  // Build steps array for backend API
  const workflowSteps = nodesOut.map((n, i) => {
    const data = n.data as any;
    return {
      step_name: data.step_name || data.label || "Step",
      step_order: i + 1,
      step_type: data.type || "task",
      assigned_account_id: data.assigned_account_id || null,
      step_group: data.step_group || null,
      step_settings: {
        duration_days: typeof data.duration_days === "number" ? data.duration_days : 0,
        watch_fields: Array.isArray(data.watch_fields) ? data.watch_fields : [],
        branch_condition: data.branch_condition ?? null,
        description: data.description || null,
        reminder_interval: data.reminder_interval || "default",
        max_duration_hours:
          typeof data.max_duration_hours === "number" ? data.max_duration_hours : null,
      },
    };
  });

  // Convert form_id to number or null
  const formId =
    typeof formState?.form_id === "number"
      ? formState.form_id
      : formState?.form_id
        ? parseInt(String(formState.form_id), 10) || null
        : null;

  // Return API-compatible payload structure matching WorkflowBuilderState
  return {
    workflow_name: formState?.workflow_name || "",
    workflow_type: (formState?.workflow_type || "Sequential") as "Sequential" | "Parallel",
    description: formState?.description || "",
    form_id: formId,
    status: (formState?.status || "draft") as "draft" | "active" | "archive",
    steps: workflowSteps,
    workflow_settings: {
      nodes: rfNodes as any,
      edges: rfEdges as any,
      authoring,
    },
  };
}
