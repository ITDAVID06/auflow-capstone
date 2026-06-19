import * as React from "react";
import {
  CheckCircle2,
  User as UserIcon,
} from "lucide-react";

type StepKind = "approval" | string;

export type StepCardProps = {
  type: StepKind;
  title: string;
  assignee?: string | null;
  description?: string | null;
  compact?: boolean; // used for children inside Branch containers
  hasError?: boolean;
  selected?: boolean; // indicates if this node is currently selected
};

const CONFIG: Record<
  string,
  {
    headerBg: string;
    headerText: string;
    badgeText: string;
    icon: React.ReactNode;
  }
> = {
  approval: {
    headerBg: "bg-emerald-100 dark:bg-emerald-900/30",
    headerText: "text-emerald-700 dark:text-emerald-300",
    badgeText: "ACTION",
    icon: <CheckCircle2 className="h-3.5 w-3.5" />,
  },
};

export default function StepCard({
  type,
  title,
  assignee,
  description,
  compact = false,
  hasError = false,
  selected = false,
}: StepCardProps) {
  const cfg =
    CONFIG[type] ??
    ({
      headerBg: "bg-slate-100",
      headerText: "text-slate-700",
      badgeText: String(type || "STEP").toUpperCase(),
      icon: null,
    } as const);

  if (compact) {
    return (
      <div
        className={[
          "h-[72px] w-[200px] overflow-hidden rounded-xl border bg-card shadow-sm",
          "hover:shadow-md transition-[box-shadow] duration-200",
          hasError ? "ring-2 ring-red-500 ring-offset-1" : "",
        ].join(" ")}
      >
        <div
          className={[
            "flex items-center gap-1.5 px-2.5 py-1 rounded-t-xl text-[10px] font-semibold tracking-wide",
            cfg.headerBg,
            cfg.headerText,
          ].join(" ")}
        >
          {cfg.icon}
          <span className="uppercase">{cfg.badgeText}</span>
        </div>

        <div className="px-2.5 py-1.5">
          <div className="text-sm font-semibold leading-5 truncate text-foreground">
            {title || "Untitled step"}
          </div>
          {assignee ? (
            <div className="mt-0.5 text-[10px] text-muted-foreground truncate">
              To: {assignee}
            </div>
          ) : null}
        </div>
      </div>
    );
  }

  return (
    <div
      className={[
        "rounded-xl border bg-card shadow-sm",
          "hover:shadow-md transition-[box-shadow] duration-200",
        "w-[240px]",
        hasError ? "ring-2 ring-red-500 ring-offset-1" : "",
      ].join(" ")}
    >
      <div
        className={[
          "flex items-center gap-2 px-3 py-1.5 rounded-t-xl text-[11px] font-semibold tracking-wide",
          cfg.headerBg,
          cfg.headerText,
        ].join(" ")}
      >
        {cfg.icon}
        <span className="uppercase">{cfg.badgeText}</span>
      </div>

      <div className="px-3 py-2">
        <div className="text-sm font-semibold leading-5 line-clamp-2 text-foreground">
          {title || "Untitled step"}
        </div>

        {assignee ? (
          <div className="mt-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
            <UserIcon className="h-3.5 w-3.5" />
            <span className="truncate">To: {assignee}</span>
          </div>
        ) : null}

        <div className="mt-2">
          {description ? (
            <p className="text-[11px] leading-4 text-muted-foreground line-clamp-3">
              {description}
            </p>
          ) : (
            <p className="text-[11px] italic text-muted-foreground/60">Add details…</p>
          )}
        </div>
      </div>
    </div>
  );
}
