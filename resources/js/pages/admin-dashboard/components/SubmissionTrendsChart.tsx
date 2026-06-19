import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
    AreaChart,
    Area,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
} from "recharts";
import type { TrendPoint } from "../AdminDashboardPage";

interface Props {
    data: TrendPoint[];
}

type Filter = "7d" | "14d" | "30d";

export function SubmissionTrendsChart({ data }: Props) {
    const [filter, setFilter] = useState<Filter>("30d");

    const filtered = filter === "7d" ? data.slice(-7) : filter === "14d" ? data.slice(-14) : data;

    return (
        <div className="rounded-xl border border-border/30 bg-card/50 p-5 shadow-sm hover:shadow-md hover:border-border/50 transition-[box-shadow,border-color] duration-200 h-full flex flex-col">
            <div className="flex items-center justify-between mb-5">
                <h2 className="text-sm font-semibold text-foreground">Submission Trends</h2>
                <div className="flex gap-1">
                    {(["7d", "14d", "30d"] as Filter[]).map((f) => (
                        <Button
                            key={f}
                            variant={filter === f ? "default" : "ghost"}
                            size="sm"
                            onClick={() => setFilter(f)}
                            className="h-7 px-2 text-xs"
                        >
                            {f}
                        </Button>
                    ))}
                </div>
            </div>
            <div className="flex-1 min-h-0">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={filtered} margin={{ top: 4, right: 4, bottom: 0, left: -10 }}>
                        <defs>
                            <linearGradient id="colorSubs" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor="var(--chart-1)" stopOpacity={0.2} />
                                <stop offset="95%" stopColor="var(--chart-1)" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <XAxis
                            dataKey="date"
                            stroke="var(--muted-foreground)"
                            strokeOpacity={0.5}
                            tick={{ fill: "var(--foreground)", fillOpacity: 0.5, fontSize: 11 }}
                            tickLine={false}
                            axisLine={false}
                            interval="preserveStartEnd"
                        />
                        <YAxis
                            stroke="var(--muted-foreground)"
                            strokeOpacity={0.5}
                            tick={{ fill: "var(--foreground)", fillOpacity: 0.5, fontSize: 11 }}
                            tickLine={false}
                            axisLine={false}
                            width={28}
                            domain={[0, (dataMax: number) => dataMax === 0 ? 5 : Math.ceil(dataMax * 1.15)]}
                            allowDataOverflow={false}
                        />
                        <Tooltip
                            contentStyle={{
                                backgroundColor: "var(--card)",
                                border: "1px solid color-mix(in oklch, var(--border) 50%, transparent)",
                                borderRadius: "8px",
                                fontSize: "12px",
                                color: "var(--card-foreground)",
                            }}
                            cursor={{ stroke: "var(--chart-1)", strokeOpacity: 0.15, strokeWidth: 1 }}
                        />
                        <Area
                            type="monotone"
                            dataKey="submissions"
                            stroke="var(--chart-1)"
                            strokeWidth={2}
                            fill="url(#colorSubs)"
                            dot={false}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
