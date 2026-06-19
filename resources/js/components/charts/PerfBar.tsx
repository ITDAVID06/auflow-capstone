import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
} from "recharts";
import { axisTick, formatHours, palette, tooltipBox } from "./chartTheme";

type Props = {
  data: any[];
  xKey: string;              // "user" | "department" | "position"
  valueKey?: string;         // defaults to "avgTimeHours"
  color?: string;            // overrides gradient color
  height?: number;
};

function CustomTooltip({ active, payload, label }: any) {
  if (!active || !payload || !payload.length) return null;
  const d = payload[0].payload;
  return (
    <div className={tooltipBox}>
      <p className="font-medium">{label}</p>
      {d?.department && (
        <p className="text-xs text-muted-foreground">
          {d.department} {d.position ? `• ${d.position}` : ""}
        </p>
      )}
      <p className="text-xs">Avg time: {formatHours(Number(d.avgTimeHours || 0))}</p>
      {"approvals" in d && <p className="text-xs">Approvals: {d.approvals}</p>}
    </div>
  );
}

export default function PerfBar({
  data,
  xKey,
  valueKey = "avgTimeHours",
  color = palette.primary,
  height = 300,
}: Props) {
  const gradId = `grad-${xKey}`;

  return (
    <div className="w-full">
      <ResponsiveContainer width="100%" height={height}>
        <BarChart
          data={data}
          margin={{ top: 8, right: 12, bottom: 32, left: 8 }}
        >
          <defs>
            <linearGradient id={gradId} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity={0.9} />
              <stop offset="100%" stopColor={color} stopOpacity={0.55} />
            </linearGradient>
          </defs>

          <CartesianGrid strokeDasharray="3 3" stroke={palette.grid} />
          <XAxis
            dataKey={xKey}
            tick={axisTick}
            interval={0}
            angle={-35}
            textAnchor="end"
            height={48}
          />
          <YAxis
            tickFormatter={(v) => `${Math.round(v)}h`}
            tick={axisTick}
            width={40}
          />
          <Tooltip content={<CustomTooltip />} />
          <Bar
            dataKey={valueKey}
            fill={`url(#${gradId})`}
            radius={[8, 8, 0, 0]}
            maxBarSize={48}
            animationDuration={450}
          />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
