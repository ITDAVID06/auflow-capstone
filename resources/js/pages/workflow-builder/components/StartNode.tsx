import * as React from "react";
import { Handle, Position, type NodeProps } from "reactflow";
import { FileText } from "lucide-react";

/**
 * Branded Start/Trigger node:
 * - Same card language as StepCard (header strip + body line)
 * - Invisible handles so it doesn't look like React Flow
 * - Height is fixed (match node.style.height in data)
 */
export default function StartNode({ data }: NodeProps<any>) {
  // Visible, animated handles for better UX
  const handleStyle: React.CSSProperties = {
    width: 10,
    height: 10,
    borderRadius: 9999,
    background: "rgb(251 113 133)", // rose-400
    border: "2px solid white",
    boxShadow: "0 2px 8px rgba(0,0,0,0.2)",
    transition: "all 0.2s ease",
  };

  const title =
    typeof data?.label === "string" && data.label.trim().length
      ? data.label
      : "Form Submitted";

  return (
    <div className="rounded-xl border bg-card shadow-sm w-[240px] h-16 overflow-hidden transition-[box-shadow] duration-200">
      {/* Visible handles for better connection UX */}
      <Handle 
        type="target" 
        position={Position.Left} 
        id="in" 
        style={handleStyle}
        className="hover:!w-[14px] hover:!h-[14px] hover:!shadow-[0_4px_12px_rgba(251,113,133,0.4)]"
      />

      {/* header strip (like other nodes) */}
      <div className="flex items-center gap-2 px-3 py-1.5 rounded-t-xl bg-rose-100 text-rose-700 text-[11px] font-semibold tracking-wide">
        <FileText className="h-3.5 w-3.5" />
        <span className="uppercase">Form</span>
      </div>

      {/* body */}
      <div className="px-3 py-2">
        <div className="text-sm font-semibold leading-5 truncate text-foreground">
          {title}
        </div>
      </div>

      <Handle 
        type="source" 
        position={Position.Right} 
        id="out" 
        style={handleStyle}
        className="hover:!w-[14px] hover:!h-[14px] hover:!shadow-[0_4px_12px_rgba(251,113,133,0.4)]"
      />
    </div>
  );
}
