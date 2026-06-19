import React from "react";
import { sanitizeValue } from "../../utils/valueFormatters";

interface LabelValueProps {
  label: string;
  value: string;
  className?: string;
}

export const LabelValue: React.FC<LabelValueProps> = ({ label, value, className }) => (
  <div className={className}>
    <p className="mb-1 text-xs max-[420px]:text-[11px] font-medium text-muted-foreground/80">{label}</p>
    <div className="break-words rounded-lg border border-border/50 bg-muted/40 px-3 py-2 text-sm max-[420px]:text-[13px]">
      {sanitizeValue(value)}
    </div>
  </div>
);
