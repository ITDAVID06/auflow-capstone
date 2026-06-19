import React from "react";

interface ChipProps {
  children: React.ReactNode;
  variant?: "approved" | "rejected" | "auto-rejected" | "pending" | "revision" | "latest";
}

export const Chip: React.FC<ChipProps> = ({ children, variant = "pending" }) => {
  const variants: Record<string, string> = {
    approved: "bg-emerald-50 border-emerald-500/40 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400",
    rejected: "bg-red-50 border-red-500/40 text-red-700 dark:bg-red-950/30 dark:text-red-400",
    "auto-rejected": "bg-amber-50 border-amber-500/40 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400",
    pending: "bg-yellow-50 border-yellow-500/40 text-yellow-700 dark:bg-yellow-950/30 dark:text-yellow-400",
    revision: "bg-yellow-50 border-yellow-500/40 text-yellow-700 dark:bg-yellow-950/30 dark:text-yellow-400",
    latest: "bg-emerald-50 border-emerald-500/40 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400",
  };
  
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium max-[420px]:text-[11px] ${variants[variant]}`}
    >
      {children}
    </span>
  );
};
