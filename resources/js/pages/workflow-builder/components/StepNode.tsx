import * as React from "react";
import { Handle, Position, type NodeProps } from "reactflow";

/** Our node just renders the label; the card visuals live in `data.label` (StepCard). */
export default function StepNode({ data, selected }: NodeProps<any>) {
  // Visible, animated handles for better UX
  const handleStyle: React.CSSProperties = {
    width: 10,
    height: 10,
    borderRadius: 9999,
    background: "rgb(16 185 129)", // emerald-500
    border: "2px solid white",
    boxShadow: "0 2px 8px rgba(0,0,0,0.2)",
    transition: "all 0.2s ease",
  };

  return (
    <div 
      style={{ background: "transparent" }}
      className={selected ? "ring-4 ring-primary/40 ring-offset-2 rounded-xl transition-[box-shadow] duration-200" : "transition-[box-shadow] duration-200"}
    >
      <Handle 
        type="target" 
        position={Position.Left} 
        id="in" 
        style={handleStyle}
        className="hover:!w-[14px] hover:!h-[14px] hover:!shadow-[0_4px_12px_rgba(16,185,129,0.4)]"
      />
      {/* The label is a React element (StepCard) injected by CanvasArea */}
      <div className="transition-[transform] duration-200">
        {data?.label}
      </div>
      <Handle 
        type="source" 
        position={Position.Right} 
        id="out" 
        style={handleStyle}
        className="hover:!w-[14px] hover:!h-[14px] hover:!shadow-[0_4px_12px_rgba(16,185,129,0.4)]"
      />
    </div>
  );
}
