import { TrendingUp, TrendingDown } from "lucide-react";
import type { ReactNode } from "react";

interface Props {
    title: string;
    value: string | number;
    change: number;
    icon?: ReactNode;
    sparkline?: number[];
}

function Sparkline({ data }: { data: number[] }) {
    if (data.length < 2) return null;
    const max = Math.max(...data);
    const min = Math.min(...data);
    const range = max - min || 1;
    const W = 56, H = 22;
    const pts = data
        .map((v, i) => {
            const x = (i / (data.length - 1)) * W;
            const y = H - ((v - min) / range) * (H - 4) - 2;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(" L ");
    return (
        <svg
            width={W}
            height={H}
            viewBox={`0 0 ${W} ${H}`}
            className="shrink-0"
            style={{ color: "var(--chart-1)" }}
            aria-hidden="true"
        >
            <path
                d={`M ${pts}`}
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

export function StatCard({ title, value, change, icon, sparkline }: Props) {
    const isPositive = change >= 0;

    return (
        <div className="rounded-xl border border-border/30 bg-card/50 p-5 shadow-sm hover:shadow-md hover:border-border/50 transition-[box-shadow,border-color] duration-200 flex flex-col justify-between min-h-[136px]">
            <div className="flex items-start justify-between">
                <p className="text-xs font-medium text-muted-foreground">{title}</p>
                {icon && (
                    <div className="w-7 h-7 rounded-lg bg-foreground/5 flex items-center justify-center text-foreground/60 shrink-0">
                        {icon}
                    </div>
                )}
            </div>

            <div>
                <div className="flex items-end justify-between gap-2">
                    <p className="text-2xl font-semibold text-foreground tabular-nums leading-none">
                        {value}
                    </p>
                    {sparkline && sparkline.length > 1 && (
                        <Sparkline data={sparkline} />
                    )}
                </div>
                <div className="flex items-center gap-1.5 mt-2">
                    <span className="inline-flex items-center gap-0.5 text-xs font-medium text-foreground/60">
                        {isPositive ? (
                            <TrendingUp className="w-3 h-3" aria-hidden="true" />
                        ) : (
                            <TrendingDown className="w-3 h-3" aria-hidden="true" />
                        )}
                        {Math.abs(change).toFixed(1)}%
                    </span>
                    <span className="text-xs text-muted-foreground/50">vs last week</span>
                </div>
            </div>
        </div>
    );
}


