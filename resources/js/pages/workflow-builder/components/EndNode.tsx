import * as React from "react";
import { Handle, Position, type NodeProps } from "reactflow";
import { FlagTriangleRight } from "lucide-react";

/**
 * Terminal "End" node — a small pill that visually closes the workflow path.
 * Non-draggable, non-selectable. Has a left target handle to receive the last edge.
 */
export default function EndNode(_props: NodeProps<any>) {
  return (
    <div className="flex h-11 w-11 items-center justify-center rounded-full border border-foreground/25 bg-card shadow-md">
      <FlagTriangleRight className="h-4 w-4 text-foreground/70" />

      <Handle
        type="target"
        position={Position.Left}
        id="in"
        style={{
          width: 9,
          height: 9,
          borderRadius: 9999,
          background: "hsl(var(--foreground))",
          border: "2px solid hsl(var(--background))",
        }}
      />
    </div>
  );
}
