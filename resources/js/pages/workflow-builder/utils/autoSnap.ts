import type { Edge, Node } from "reactflow";
import { isBranchContainer } from "./branchLayout";

const measure = (n: Node) => {
  const w =
    typeof (n as any).width === "number"
      ? (n as any).width
      : typeof (n as any).style?.width === "number"
      ? (n as any).style!.width
      : 160;
  const h =
    typeof (n as any).height === "number"
      ? (n as any).height
      : typeof (n as any).style?.height === "number"
      ? (n as any).style!.height
      : 40;
  return { w, h };
};

export const widthOf = (n: Node): number => {
  if (isBranchContainer(n)) {
    return Number((n as any).data?.w ?? (n as any).style?.width ?? 360);
  }
  return measure(n).w;
};

/** Centerline Y based on the START node (or median center). */
export function getLaneCenterY(nodes: Node[]): number {
  const start = nodes.find((n) => n.id === "start");
  if (start) {
    const { h } = measure(start);
    return start.position.y + h / 2;
  }
  const isLaneItem = (n: Node) =>
    !n.parentNode &&
    (n.type === "step" || n.type === "start" || n.type === "input" || n.type === "default" || isBranchContainer(n));
  const tops = nodes.filter(isLaneItem);
  if (tops.length === 0) return 100;
  const centers = tops
    .map((n) => n.position.y + measure(n).h / 2)
    .sort((a, b) => a - b);
  return centers[Math.floor(centers.length / 2)];
}

/**
 * Rebuild mainline edges strictly as start→i1→i2→… ;
 * mark edges virtual (dashed) when a branch container is involved.
 * FIXES:
 *  - Drop ALL edges whose endpoints are both in the mainline (even virtual).
 *  - Skip creating edges already present in preserved by (source,target).
 *  - Final de-dupe by edge.id to guarantee unique keys for React.
 */
export function rebuildMainlineEdges(
  edges: Edge[],
  orderedIds: string[],
  nodes: Node[],
  startId?: string
): Edge[] {
  const byId = new Map(nodes.map((n) => [n.id, n]));
  const normalizedOrderedIds = orderedIds.filter((id) => byId.has(id));
  const mainSet = new Set<string>([...(startId ? [startId] : []), ...normalizedOrderedIds]);

  // Keep only edges that are NOT between two mainline nodes; we'll rebuild those.
  const preserved = edges.filter((e: any) => {
    const sIn = mainSet.has(e.source);
    const tIn = mainSet.has(e.target);
    return !(sIn && tIn);
  });

  // Guard against re-adding an edge that already exists among preserved by (s,t)
const preservedKeys = new Set(preserved.map((e) => `e:${e.source}->${e.target}`));


  const rebuilt: Edge[] = [];
  const push = (s: string, t: string) => {
    const k = `e:${s}->${t}`;
    if (preservedKeys.has(k)) return; // avoid duplicates

    const sNode = byId.get(s);
    const tNode = byId.get(t);
    const virt = (sNode && isBranchContainer(sNode)) || (tNode && isBranchContainer(tNode));

    rebuilt.push(
      virt
        ? {
            id: k,
            source: s,
            target: t,
            data: { virtual: true },
            style: { strokeDasharray: "6 3" },
          }
        : { id: k, source: s, target: t }
    );
  };

  if (normalizedOrderedIds.length) {
    if (startId) push(startId, normalizedOrderedIds[0]);
    for (let i = 0; i < normalizedOrderedIds.length - 1; i++) {
      push(normalizedOrderedIds[i], normalizedOrderedIds[i + 1]);
    }
  }

  // Final de-dupe by edge.id
  const out = [...preserved, ...rebuilt];
  const seen = new Set<string>();
  return out.filter((e) => (seen.has(e.id) ? false : (seen.add(e.id), true)));
}
