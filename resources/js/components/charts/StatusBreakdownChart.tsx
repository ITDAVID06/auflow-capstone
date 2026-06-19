import React from "react";
import {
  PieChart,
  Pie,
  Cell,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from "recharts";
import { PieChart as PieIcon } from "lucide-react";

export interface StatusPoint {
  status: string;
  count: number;
}

interface Props {
  data: StatusPoint[];
  /** Height of the chart area in pixels. Defaults to 220. */
  height?: number;
  emptyMessage?: string;
}

const STATUS_COLORS: Record<string, string> = {
  approved:  "#22c55e",
  rejected:  "#ef4444",
  pending:   "#f59e0b",
  completed: "#3b82f6",
  unknown:   "#94a3b8",
};

const FALLBACK_COLORS = ["#a855f7", "#06b6d4", "#f97316", "#84cc16"];

const getColor = (status: string, index: number): string =>
  STATUS_COLORS[status.toLowerCase()] ?? FALLBACK_COLORS[index % FALLBACK_COLORS.length];

const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

const StatusBreakdownChart: React.FC<Props> = ({
  data,
  height = 220,
  emptyMessage = "No submissions for this filter set",
}) => {
  if (data.length === 0) {
    return (
      <div
        className="flex flex-col items-center justify-center text-center text-muted-foreground"
        style={{ height }}
      >
        <PieIcon className="mb-2 h-8 w-8 opacity-30" />
        <p className="text-sm">{emptyMessage}</p>
      </div>
    );
  }

  const formatted = data.map((d) => ({ ...d, name: capitalize(d.status) }));

  return (
    <div style={{ height }}>
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={formatted}
            dataKey="count"
            nameKey="name"
            cx="50%"
            cy="50%"
            innerRadius="40%"
            outerRadius="70%"
            paddingAngle={2}
          >
            {formatted.map((entry, index) => (
              <Cell key={entry.status} fill={getColor(entry.status, index)} />
            ))}
          </Pie>
          <Tooltip
            contentStyle={{
              backgroundColor: "hsl(var(--card))",
              border: "1px solid hsl(var(--border) / 0.4)",
              borderRadius: "8px",
              fontSize: "12px",
            }}
            formatter={(value: number, name: string) => [value, name]}
          />
          <Legend
            iconType="circle"
            iconSize={8}
            formatter={(value) => (
              <span className="text-xs text-muted-foreground">{value}</span>
            )}
          />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
};

export default StatusBreakdownChart;
