import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import { axisTick, formatHours, palette, tooltipBox } from "./chartTheme";

type Props = {
  data: { month: string; avgTimeHours: number }[];
  color?: string;
  height?: number;
};

function TrendTooltip({ active, payload, label }: any) {
  if (!active || !payload || !payload.length) return null;
  const d = payload[0].payload;
  return (
    <div className={tooltipBox}>
      <p className="font-medium">{label}</p>
      <p className="text-xs">Avg time: {formatHours(Number(d.avgTimeHours || 0))}</p>
    </div>
  );
}

export default function TrendLine({
  data,
  color = palette.primary,
  height = 300,
}: Props) {
  return (
    <div className="w-full">
      <ResponsiveContainer width="100%" height={height}>
        <LineChart data={data} margin={{ top: 12, right: 16, bottom: 8, left: 8 }}>
          <defs>
            <linearGradient id="lineGrad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity={0.95} />
              <stop offset="100%" stopColor={color} stopOpacity={0.5} />
            </linearGradient>
          </defs>
          <CartesianGrid strokeDasharray="3 3" stroke={palette.grid} />
          <XAxis dataKey="month" tick={axisTick} />
          <YAxis tick={axisTick} tickFormatter={(v) => `${Math.round(v)}h`} width={40} />
          <Tooltip content={<TrendTooltip />} />
          <Line
            type="monotone"
            dataKey="avgTimeHours"
            stroke="url(#lineGrad)"
            strokeWidth={3}
            dot={{ r: 3 }}
            activeDot={{ r: 5 }}
            animationDuration={450}
          />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}
