import React from "react";
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import { TrendingUp } from "lucide-react";

export interface TrendPoint {
  date: string;
  count: number;
}

interface Props {
  data: TrendPoint[];
  /** Height of the chart area in pixels. Defaults to 220. */
  height?: number;
}

const formatDate = (dateStr: string): string => {
  try {
    const d = new Date(dateStr + "T00:00:00");
    return d.toLocaleDateString("en-US", { month: "short", day: "numeric" });
  } catch {
    return dateStr;
  }
};

const SubmissionTrendChart: React.FC<Props> = ({ data, height = 220 }) => {
  if (data.length === 0) {
    return (
      <div
        className="flex flex-col items-center justify-center text-center text-muted-foreground"
        style={{ height }}
      >
        <TrendingUp className="mb-2 h-8 w-8 opacity-30" />
        <p className="text-sm">No submissions in this date range</p>
      </div>
    );
  }

  const formatted = data.map((d) => ({ ...d, label: formatDate(d.date) }));

  return (
    <div style={{ height }}>
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={formatted} margin={{ top: 4, right: 8, left: 0, bottom: 0 }}>
          <defs>
            <linearGradient id="sharedTrendGradient" x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.15} />
              <stop offset="95%" stopColor="#3b82f6" stopOpacity={0} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke="oklch(0.5 0 0 / 0.08)" vertical={false} />
          <XAxis
            dataKey="label"
            stroke="oklch(0.5 0 0 / 0.25)"
            fontSize={11}
            tickLine={false}
            axisLine={false}
            interval="preserveStartEnd"
          />
          <YAxis
            stroke="oklch(0.5 0 0 / 0.25)"
            fontSize={11}
            tickLine={false}
            axisLine={false}
            width={28}
            allowDecimals={false}
          />
          <Tooltip
            contentStyle={{
              backgroundColor: "hsl(var(--card))",
              border: "1px solid hsl(var(--border) / 0.4)",
              borderRadius: "8px",
              fontSize: "12px",
            }}
            labelFormatter={(label) => String(label)}
            formatter={(value: number) => [value, "Submissions"]}
            cursor={{ stroke: "oklch(0.5 0 0 / 0.08)", strokeWidth: 1 }}
          />
          <Area
            type="monotone"
            dataKey="count"
            stroke="#3b82f6"
            strokeWidth={2}
            fill="url(#sharedTrendGradient)"
            dot={false}
            activeDot={{ r: 4, fill: "#3b82f6" }}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
};

export default SubmissionTrendChart;
