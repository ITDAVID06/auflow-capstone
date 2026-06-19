import React from "react";
import { CheckCircle2, Clock, XCircle, CircleDashed, CircleAlert } from "lucide-react";

const statusConfig = {
  Approved: {
    icon: CheckCircle2,
    text: "text-emerald-700 dark:text-emerald-400",
    bg: "bg-emerald-50 dark:bg-emerald-500/10",
    border: "border-emerald-200 dark:border-emerald-500/30",
    iconColor: "text-emerald-600 dark:text-emerald-400",
  },
  Pending: {
    icon: Clock,
    text: "text-amber-700 dark:text-amber-400",
    bg: "bg-amber-50 dark:bg-amber-500/10",
    border: "border-amber-200 dark:border-amber-500/30",
    iconColor: "text-amber-600 dark:text-amber-400",
  },
  Rejected: {
    icon: XCircle,
    text: "text-rose-700 dark:text-rose-400",
    bg: "bg-rose-50 dark:bg-rose-500/10",
    border: "border-rose-200 dark:border-rose-500/30",
    iconColor: "text-rose-600 dark:text-rose-400",
  },
  Waiting: {
    icon: CircleAlert,
    text: "text-blue-700 dark:text-blue-400",
    bg: "bg-blue-50 dark:bg-blue-500/10",
    border: "border-blue-200 dark:border-blue-500/30",
    iconColor: "text-blue-600 dark:text-blue-400",
  },
  Default: {
    icon: CircleDashed,
    text: "text-zinc-600 dark:text-zinc-400",
    bg: "bg-zinc-100 dark:bg-zinc-500/10",
    border: "border-zinc-200 dark:border-zinc-500/30",
    iconColor: "text-zinc-500 dark:text-zinc-400",
  },
} as const;

interface SubmissionStatusBadgeProps {
  status: string;
}

export default function SubmissionStatusBadge({
  status,
}: SubmissionStatusBadgeProps) {
  const normalized = String(status || "").toLowerCase();

  const key =
    normalized === "approved"
      ? "Approved"
      : normalized === "pending"
      ? "Pending"
      : normalized === "rejected"
      ? "Rejected"
      : normalized === "waiting"
      ? "Waiting"
      : "Default";

  const badge = statusConfig[key];
  const Icon = badge.icon;

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium leading-none ${badge.bg} ${badge.text} ${badge.border}`}
    >
      <Icon className={`h-3.5 w-3.5 shrink-0 ${badge.iconColor}`} aria-hidden="true" />
      {status}
    </span>
  );
}
