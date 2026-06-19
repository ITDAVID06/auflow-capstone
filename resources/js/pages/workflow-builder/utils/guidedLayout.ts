import type { Node, Edge } from "reactflow";
import { isBranchContainer, layoutContainers } from "./branchLayout";
import { rebuildMainlineEdges, widthOf, getLaneCenterY } from "./autoSnap";

// ─── Layout constants ───────────────────────────────────────────────────────
export const START_X = 100;
export const LANE_Y = 200;
export const STEP_GAP = 120; // horizontal gap between nodes (includes plus-button space)
export const PLUS_SIZE = 48;

export const START_W = 240;
export const START_H = 64;
export const STEP_W = 240;
export const STEP_H = 120;
export const END_W = 100;
export const END_H = 48;

// ─── Types ──────────────────────────────────────────────────────────────────
export interface LayoutResult {
  nodes: Node[];
  edges: Edge[];
  placeholders: Node[];
  endNode: Node;
}

// ─── Core layout engine ─────────────────────────────────────────────────────

/**
 * Compute deterministic node positions from a stepOrder array.
 *
 * Sequential mode: Start → steps in order → End  (single horizontal lane)
 * Parallel mode:   same mainline, but branch containers offset vertically
 *
 * Returns positioned nodes, rebuilt edges, plus-placeholder nodes, and the end node.
 */
export function computeLayout(
  stepOrder: string[],
  allNodes: Node[],
  mode: "Sequential" | "Parallel"
): LayoutResult {
  const nodeMap = new Map(allNodes.map((n) => [n.id, n]));

  // ── 1. Position the Start node ──────────────────────────────────────────
  const startNode = nodeMap.get("start");
  const laneY = LANE_Y;
  const startCenterY = laneY;

  const positionedNodes: Node[] = [];

  if (startNode) {
    positionedNodes.push({
      ...startNode,
      position: { x: START_X, y: startCenterY - START_H / 2 },
      style: { ...(startNode.style || {}), width: START_W, height: START_H },
      draggable: false,
    });
  }

  // ── 2. Pre-compute container sizes via layoutContainers ─────────────────
  // We need accurate container dimensions before positioning on the mainline.
  // Build a temporary list with containers + their children so layoutContainers
  // can compute w/h. We'll use those dimensions for positioning.
  const tempContainerNodes: Node[] = [];
  for (const id of stepOrder) {
    const node = nodeMap.get(id);
    if (!node) continue;
    if (isBranchContainer(node)) {
      tempContainerNodes.push({ ...node });
      const children = allNodes.filter((n) => n.parentNode === id);
      for (const child of children) {
        tempContainerNodes.push({ ...child });
      }
    }
  }
  const preLayouted = mode === "Parallel" ? layoutContainers(tempContainerNodes) : tempContainerNodes;
  const preLayoutMap = new Map(preLayouted.map((n) => [n.id, n]));

  // ── 3. Walk stepOrder and position each node ────────────────────────────
  let cursorX = START_X + START_W + STEP_GAP;
  const placeholders: Node[] = [];

  // Plus placeholder between Start and first step (or trailing if empty)
  const firstPlaceholderId = "__plus__start";
  placeholders.push(makePlaceholder(firstPlaceholderId, "start", {
    x: START_X + START_W + (STEP_GAP - PLUS_SIZE) / 2,
    y: startCenterY - PLUS_SIZE / 2,
  }));

  for (let i = 0; i < stepOrder.length; i++) {
    const id = stepOrder[i];
    const node = nodeMap.get(id);
    if (!node) continue;

    const isContainer = isBranchContainer(node);

    // Use pre-layouted dimensions for containers (accurate after layoutContainers)
    const preNode = preLayoutMap.get(id);
    const nodeW = isContainer
      ? Number((preNode as any)?.data?.w ?? (node as any).data?.w ?? (node as any).style?.width ?? 360)
      : STEP_W;
    const nodeH = isContainer
      ? Number((preNode as any)?.data?.h ?? (node as any).data?.h ?? (node as any).style?.height ?? 280)
      : STEP_H;

    // Vertical position: all mainline nodes centered on the lane
    const yPos = startCenterY - nodeH / 2;

    const containerData = isContainer && preNode
      ? { ...(preNode.data || {}), w: nodeW, h: nodeH }
      : node.data;

    positionedNodes.push({
      ...node,
      position: { x: cursorX, y: yPos },
      data: containerData,
      style: { ...(node.style || {}), width: nodeW, height: nodeH, zIndex: isContainer ? 0 : 10 },
      draggable: false,
      parentNode: undefined,
      extent: undefined,
    });

    // Also include children of branch containers
    if (isContainer) {
      const children = allNodes.filter((n) => n.parentNode === id);
      for (const child of children) {
        if (!positionedNodes.some((n) => n.id === child.id)) {
          // Use pre-layouted position if available
          const preChild = preLayoutMap.get(child.id);
          positionedNodes.push({
            ...child,
            ...(preChild ? { position: preChild.position } : {}),
            draggable: false,
          });
        }
      }
    }

    // Plus placeholder after this node
    const afterX = cursorX + nodeW;
    const phId = `__plus__${id}`;
    placeholders.push(makePlaceholder(phId, id, {
      x: afterX + (STEP_GAP - PLUS_SIZE) / 2,
      y: startCenterY - PLUS_SIZE / 2,
    }));

    cursorX += nodeW + STEP_GAP;
  }

  // ── 4. End node ─────────────────────────────────────────────────────────
  const endNode: Node = {
    id: "__end__",
    type: "endNode",
    position: { x: cursorX, y: startCenterY - END_H / 2 },
    draggable: false,
    selectable: false,
    connectable: false,
    focusable: false,
    data: { label: "End" },
    style: { width: END_W, height: END_H, zIndex: 5 },
  };

  // ── 5. Rebuild edges following the mainline chain ───────────────────────
  // (layoutContainers already applied above during pre-computation)
  const withContainerLayout = positionedNodes;
  const edges = rebuildMainlineEdges([], stepOrder, withContainerLayout, "start");

  // Add edge from last step (or start) to end node
  const lastSourceId = stepOrder.length > 0 ? stepOrder[stepOrder.length - 1] : "start";
  edges.push({
    id: `e:${lastSourceId}->__end__`,
    source: lastSourceId,
    target: "__end__",
    style: { strokeWidth: 3, stroke: "#334155", opacity: 0.9 },
  });

  return { nodes: withContainerLayout, edges, placeholders, endNode };
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function makePlaceholder(
  id: string,
  insertAfterId: string,
  position: { x: number; y: number }
): Node {
  return {
    id,
    type: "addPlaceholder",
    position,
    draggable: false,
    selectable: false,
    connectable: false,
    focusable: false,
    data: { insertAfterId, active: false },
    style: { width: PLUS_SIZE, height: PLUS_SIZE, zIndex: 5 },
  };
}

/**
 * Derive a stepOrder array from existing positioned nodes (for backwards-compatible hydration).
 * Sorts top-level non-start, non-end, non-placeholder nodes by x position.
 */
export function deriveStepOrder(nodes: Node[]): string[] {
  return nodes
    .filter(
      (n) =>
        n.id !== "start" &&
        !n.id.startsWith("__plus__") &&
        !n.id.startsWith("__end__") &&
        !n.parentNode &&
        n.type !== "addPlaceholder" &&
        n.type !== "endNode"
    )
    .sort((a, b) => a.position.x - b.position.x)
    .map((n) => n.id);
}

/**
 * Calculate step_group for nodes based on workflow type and edge topology.
 * Uses BFS from the start node to assign sequential group numbers.
 */
export function calculateStepGroups(nodes: Node[], edges: Edge[], workflowType: string): Node[] {
  if (workflowType !== "Sequential") return nodes;

  const adjacency = new Map<string, string[]>();
  for (const edge of edges) {
    if (!adjacency.has(edge.source)) adjacency.set(edge.source, []);
    adjacency.get(edge.source)!.push(edge.target);
  }

  const stepGroups = new Map<string, number>();
  const queue: Array<{ nodeId: string; group: number }> = [{ nodeId: "start", group: 0 }];
  const visited = new Set<string>(["start"]);

  while (queue.length > 0) {
    const { nodeId, group } = queue.shift()!;
    const neighbors = adjacency.get(nodeId) || [];
    for (const neighbor of neighbors) {
      if (!visited.has(neighbor)) {
        visited.add(neighbor);
        stepGroups.set(neighbor, group + 1);
        queue.push({ nodeId: neighbor, group: group + 1 });
      }
    }
  }

  return nodes.map((node) => {
    if (node.type !== "step" || node.parentNode) return node;
    const g = stepGroups.get(node.id);
    return g !== undefined ? { ...node, data: { ...node.data, step_group: g } } : node;
  });
}
