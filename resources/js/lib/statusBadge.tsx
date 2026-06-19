import { cn } from "@/lib/utils";
import { JSX } from "react";

export function getStatusBadge(status: string): JSX.Element {
  const base =
    "text-xs px-2 py-0.5 rounded-full font-medium inline-flex items-center gap-1 border";

  switch (status) {
    case "Approved":
      return (
        <span
          className={cn(
            base,
            "text-emerald-500 border-emerald-500"
          )}
        >
          Approved
        </span>
      );
    case "Pending":
      return (
        <span
          className={cn(
            base,
            "text-amber-500 border-amber-500"
          )}
        >
          Pending
        </span>
      );
    case "Rejected":
      return (
        <span
          className={cn(
            base,
            "text-rose-500 border-rose-500"
          )}
        >
          Rejected
        </span>
      );
    default:
      return (
        <span
          className={cn(
            base,
            "text-gray-400 border-gray-400"
          )}
        >
          {status}
        </span>
      );
  }
}
