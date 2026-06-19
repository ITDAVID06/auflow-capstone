import React, { useEffect, useState } from "react";
import axios from "axios";
import { FileText, Clock, CheckCircle2, XCircle } from "lucide-react";

interface Metrics {
  total: number;
  pending: number;
  approved: number;
  rejected: number;
}

interface DashboardMetricsProps {
  metricsEndpoint: string;
  tourId?: string;
  title?: string;
  subtitle?: string;
}

type IconCmp = React.ComponentType<React.SVGProps<SVGSVGElement>>;

function MetricCardSkeleton() {
  return (
    <div className="p-5 sm:p-6 animate-pulse flex flex-col gap-2">
      <div className="flex items-center justify-between gap-2">
        <div className="h-2 w-20 rounded bg-muted" />
        <div className="w-7 h-7 rounded-lg bg-muted flex-shrink-0" />
      </div>
      <div className="h-9 w-12 rounded bg-muted" />
      <div className="h-2 w-16 rounded bg-muted" />
    </div>
  );
}

export default function DashboardMetrics({ metricsEndpoint, tourId, title, subtitle }: DashboardMetricsProps) {
  const [metrics, setMetrics] = useState<Metrics | null>(null);
  const [fetchError, setFetchError] = useState(false);
  const [retryKey, setRetryKey] = useState(0);

  useEffect(() => {
    setFetchError(false);
    axios
      .get(metricsEndpoint)
      .then((res) => setMetrics(res.data))
      .catch(() => setFetchError(true));
  }, [metricsEndpoint, retryKey]);

  const items: ReadonlyArray<{
    title: string;
    value: number;
    subtitle: string;
    Icon: IconCmp;
    iconClass: string;
    iconBg: string;
  }> = [
    {
      title: "Total Submissions",
      value: metrics?.total ?? 0,
      subtitle: "All submissions",
      Icon: FileText,
      iconClass: "text-muted-foreground",
      iconBg: "bg-muted/50",
    },
    {
      title: "Pending Review",
      value: metrics?.pending ?? 0,
      subtitle: "Awaiting review",
      Icon: Clock,
      iconClass: "text-amber-400",
      iconBg: "bg-amber-500/10",
    },
    {
      title: "Approved",
      value: metrics?.approved ?? 0,
      subtitle: "Successfully approved",
      Icon: CheckCircle2,
      iconClass: "text-emerald-400",
      iconBg: "bg-emerald-500/10",
    },
    {
      title: "Rejected",
      value: metrics?.rejected ?? 0,
      subtitle: "Needs revision",
      Icon: XCircle,
      iconClass: "text-rose-400",
      iconBg: "bg-rose-500/10",
    },
  ] as const;

  const cellBorderClasses = [
    "border-r border-b border-border/60 lg:border-b-0",
    "border-b border-border/60 lg:border-b-0 lg:border-r",
    "border-r border-border/60",
    "",
  ];

  if (fetchError) {
    return (
      <div data-tour={tourId} className="space-y-2.5">
        <div className="border border-border/60 rounded-xl p-6 flex flex-col items-center gap-3 text-center">
          <p className="text-sm text-muted-foreground">Failed to load metrics.</p>
          <button
            onClick={() => setRetryKey((k) => k + 1)}
            className="text-xs text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 rounded"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  if (!metrics) {
    return (
      <div aria-busy="true" data-tour={tourId} className="space-y-2.5">
        {(title || subtitle) && (
          <div className="mb-1">
            {title && <p className="text-sm font-medium text-foreground">{title}</p>}
            {subtitle && <p className="text-xs text-muted-foreground">{subtitle}</p>}
          </div>
        )}
        <div className="md:hidden flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
          {[0, 1, 2, 3].map((idx) => (
            <div key={idx} className="min-w-[150px] rounded-xl border border-border/60 bg-card">
              <MetricCardSkeleton />
            </div>
          ))}
        </div>

        <div className="hidden md:block border border-border/60 rounded-xl overflow-hidden">
          <div className="grid grid-cols-2 lg:grid-cols-4">
            {[0, 1, 2, 3].map((idx) => (
              <div
                key={idx}
                className={[
                  idx === 0 ? "border-r border-b border-border/60 lg:border-b-0" : "",
                  idx === 1 ? "border-b border-border/60 lg:border-b-0 lg:border-r" : "",
                  idx === 2 ? "border-r border-border/60" : "",
                ].filter(Boolean).join(" ")}
              >
                <MetricCardSkeleton />
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div aria-busy="false" data-tour={tourId} className="space-y-2.5 motion-safe:animate-in motion-safe:fade-in duration-300">
      {(title || subtitle) && (
        <div className="mb-1">
          {title && <p className="text-sm font-medium text-foreground">{title}</p>}
          {subtitle && <p className="text-xs text-muted-foreground">{subtitle}</p>}
        </div>
      )}
      <div className="md:hidden flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
        {items.map(({ title: cardTitle, value, subtitle: cardSubtitle, Icon, iconClass, iconBg }) => (
          <div key={cardTitle} className="min-w-[150px] rounded-xl border border-border/60 bg-card p-4 flex flex-col gap-2">
            <div className="flex items-center justify-between gap-2">
              <p className="text-[10px] font-medium text-muted-foreground uppercase tracking-wider leading-snug">
                {cardTitle}
              </p>
              <div className={`flex items-center justify-center w-6 h-6 rounded-md ${iconBg} flex-shrink-0`}>
                <Icon aria-hidden="true" className={`h-3.5 w-3.5 ${iconClass}`} />
              </div>
            </div>
            <p className="text-2xl font-bold leading-none tracking-tight tabular-nums text-foreground">
              {value}
            </p>
            <p className="text-[10px] text-muted-foreground">
              {cardSubtitle}
            </p>
          </div>
        ))}
      </div>

      <div className="hidden md:block border border-border/60 rounded-xl overflow-hidden">
        <div className="grid grid-cols-2 lg:grid-cols-4">
          {items.map(({ title: cardTitle, value, subtitle: cardSubtitle, Icon, iconClass, iconBg }, idx) => (
            <div
              key={cardTitle}
              className={`p-5 sm:p-6 flex flex-col gap-2 ${cellBorderClasses[idx]}`}
            >
              <div className="flex items-center justify-between gap-2">
                <p className="text-[10px] sm:text-xs font-medium text-muted-foreground uppercase tracking-wider leading-snug">
                  {cardTitle}
                </p>
                <div className={`flex items-center justify-center w-7 h-7 rounded-lg ${iconBg} flex-shrink-0`}>
                  <Icon aria-hidden="true" className={`h-4 w-4 ${iconClass}`} />
                </div>
              </div>
              <p className="text-3xl sm:text-4xl font-bold leading-none tracking-tight tabular-nums text-foreground">
                {value}
              </p>
              <p className="text-[10px] sm:text-xs text-muted-foreground">
                {cardSubtitle}
              </p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
