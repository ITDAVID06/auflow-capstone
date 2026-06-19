import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
} from "recharts";
import { BarChart3 } from "lucide-react";
import type { FormPopularity } from "../AdminDashboardPage";

interface Props {
    data: FormPopularity[];
}

export function PopularFormsChart({ data }: Props) {
    return (
        <div className="rounded-xl border border-border/30 bg-card/50 p-5 shadow-sm hover:shadow-md hover:border-border/50 transition-[box-shadow,border-color] duration-200 h-full flex flex-col">
            <h2 className="text-sm font-semibold text-foreground mb-5">Most Popular Forms</h2>
            
            {data.length === 0 ? (
                <div className="flex-1 flex flex-col items-center justify-center min-h-32 text-center">
                    <div className="w-12 h-12 rounded-lg bg-foreground/5 flex items-center justify-center mb-4">
                        <BarChart3 className="w-6 h-6 text-muted-foreground/50" />
                    </div>
                    <p className="text-sm text-muted-foreground mb-1">No form data</p>
                    <p className="text-xs text-muted-foreground/60">Stats will appear here</p>
                </div>
            ) : (
                <div className="h-[200px]">
                    <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={data} layout="vertical">
                            <XAxis type="number" stroke="var(--muted-foreground)" strokeOpacity={0.5} fontSize={11} tickLine={false} axisLine={false} />
                            <YAxis
                                type="category"
                                dataKey="form_name"
                                stroke="var(--muted-foreground)"
                                strokeOpacity={0.5}
                                tick={{ fill: "var(--foreground)", fillOpacity: 0.7, fontSize: 11 }}
                                fontSize={11}
                                tickLine={false}
                                axisLine={false}
                                width={130}
                            />
                            <Tooltip
                                contentStyle={{
                                    backgroundColor: "var(--card)",
                                    border: "1px solid color-mix(in oklch, var(--border) 50%, transparent)",
                                    borderRadius: "8px",
                                    fontSize: "12px",
                                    color: "var(--card-foreground)",
                                }}
                                cursor={{ fill: "color-mix(in oklch, var(--chart-1) 8%, transparent)" }}
                            />
                            <Bar dataKey="submission_count" fill="var(--chart-1)" fillOpacity={0.85} radius={[0, 4, 4, 0]} />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}
