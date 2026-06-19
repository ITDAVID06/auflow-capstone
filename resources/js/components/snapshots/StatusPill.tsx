import React from "react";
import { cn } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";

export function statusTone(status: string) {
  const s = (status || "").toLowerCase();
  if (s === "approved") {
    return {
      pill: "border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700/50 dark:bg-emerald-900/20 dark:text-emerald-300",
      borderText: "border-emerald-300 text-emerald-800 dark:border-emerald-700/50 dark:text-emerald-300",
      cardBorder: "border-emerald-200 dark:border-emerald-800/60",
      watermark: "text-emerald-600/10 dark:text-emerald-400/10",
    };
  }
  if (s === "rejected") {
    return {
      pill: "border-red-300 bg-red-100 text-red-800 dark:border-red-700/50 dark:bg-red-900/20 dark:text-red-300",
      borderText: "border-red-300 text-red-800 dark:border-red-700/50 dark:text-red-300",
      cardBorder: "border-red-200 dark:border-red-800/60",
      watermark: "text-red-600/10 dark:text-red-400/10",
    };
  }
  return {
    pill: "border-yellow-300 bg-yellow-100 text-yellow-800 dark:border-yellow-700/50 dark:bg-yellow-900/20 dark:text-yellow-300",
    borderText: "border-yellow-300 text-yellow-800 dark:border-yellow-700/50 dark:text-yellow-300",
    cardBorder: "border-yellow-200 dark:border-yellow-800/60",
    watermark: "text-yellow-600/10 dark:text-yellow-400/10",
  };
}

export function StatusPill({ status }: { status: string }) {
  const tone = statusTone(status);
  return (
    <Badge
      variant="outline"
      className={cn(
        "inline-flex items-center rounded-md border px-2.5 py-0.5 text-[11px] font-medium",
        tone.pill
      )}
      aria-label={`Status: ${status}`}
    >
      {status}
    </Badge>
  );
}
