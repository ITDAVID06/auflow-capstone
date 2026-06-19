import React from "react";
import { StatusPill, statusTone } from "./StatusPill";
import type { ApprovalRecord } from "./SnapshotTypes";
import { Calendar, Clock } from "lucide-react";

function rel(date?: string | null) {
  if (!date) return "—";
  const d = new Date(date).getTime();
  const diff = Date.now() - d;
  const mins = Math.round(diff / 60000);
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.round(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.round(hrs / 24);
  return `${days}d ago`;
}

export function ApprovalTimeline({ approvals }: { approvals: ApprovalRecord[] }) {
  if (!approvals?.length) {
    return (
      <div className="text-center py-12 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
        <div className="flex flex-col items-center gap-3">
          <div className="flex items-center justify-center w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800">
            <svg className="h-6 w-6 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <p className="text-sm text-zinc-500 dark:text-zinc-400">No approval history recorded yet</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {approvals.map((a, i) => {
        const tone = statusTone(a.status);
        const isApproved = a.status === "Approved" || a.status === "Completed";
        const isRejected = a.status === "Rejected";

        return (
          <div
            key={i}
            className={`
              relative rounded-lg border p-4 transition-all
              ${isApproved 
                ? "border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-950/20" 
                : isRejected 
                  ? "border-red-500/30 bg-red-50/50 dark:bg-red-950/20"
                  : "border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-900"
              }
            `}
          >
            {/* Status Indicator Bar */}
            <div 
              className={`
                absolute left-0 top-0 bottom-0 w-1 rounded-l-lg
                ${isApproved 
                  ? "bg-emerald-500" 
                  : isRejected 
                    ? "bg-red-500" 
                    : "bg-yellow-500"
                }
              `}
            />

            {/* Content */}
            <div className="pl-3 space-y-3">
              {/* Header */}
              <div className="flex items-start justify-between gap-3">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-zinc-200 dark:bg-zinc-700 text-xs font-semibold">
                      {i + 1}
                    </span>
                    <h3 className="font-semibold text-sm text-zinc-900 dark:text-zinc-100">
                      {a.step || "—"}
                    </h3>
                  </div>
                  <StatusPill status={a.status} />
                </div>
              </div>

              {/* Details */}
              <div className="space-y-2 text-sm">
                {/* Actor */}
                <div className="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                  <svg className="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                  <span>{a.actor || "—"}</span>
                </div>

                {/* Timestamp */}
                {a.acted_at && (
                  <div className="flex items-center gap-2 text-zinc-600 dark:text-zinc-400">
                    <Calendar className="h-4 w-4 flex-shrink-0" />
                    <span className="text-xs">
                      {new Date(a.acted_at).toLocaleString()} ({rel(a.acted_at)})
                    </span>
                  </div>
                )}

                {/* Action Hash */}
                {a.action_hash && (
                  <div className="flex items-start gap-2 text-zinc-600 dark:text-zinc-400">
                    <svg className="h-4 w-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <div className="flex-1 min-w-0">
                      <div className="text-xs font-medium mb-1">Action Hash (SHA-256)</div>
                      <code className="text-[10px] font-mono text-zinc-700 dark:text-zinc-300 break-all bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded">
                        {a.action_hash}
                      </code>
                    </div>
                  </div>
                )}
              </div>

              {/* Comment */}
              {a.comment && (
                <div className="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                  <p className="whitespace-pre-wrap text-[13px] leading-relaxed text-zinc-800 dark:text-zinc-200">
                    {a.comment}
                  </p>
                </div>
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}