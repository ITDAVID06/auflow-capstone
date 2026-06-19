import React, { useEffect, useState } from "react";
import axios from "axios";
import { Loader2, AlertCircle, TrendingUp, PieChart } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import SubmissionTrendChart from "@/components/charts/SubmissionTrendChart";
import StatusBreakdownChart from "@/components/charts/StatusBreakdownChart";
import type { TrendPoint } from "@/components/charts/SubmissionTrendChart";
import type { StatusPoint } from "@/components/charts/StatusBreakdownChart";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const Spinner: React.FC = () => (
  <div className="flex h-[220px] items-center justify-center">
    <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
  </div>
);

const ErrorSlot: React.FC<{ message: string }> = ({ message }) => (
  <div className="flex h-[220px] flex-col items-center justify-center gap-2 text-destructive">
    <AlertCircle className="h-5 w-5" />
    <span className="text-xs">{message}</span>
  </div>
);

// ---------------------------------------------------------------------------
// Individual widget cards
// ---------------------------------------------------------------------------

const TrendWidget: React.FC = () => {
  const [data, setData] = useState<TrendPoint[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    axios
      .get<TrendPoint[]>(route("dashboard.widgets.submission-trend"))
      .then((res) => {
        if (!cancelled) setData(res.data);
      })
      .catch(() => {
        if (!cancelled) setError("Failed to load trend data.");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <Card className="col-span-2 lg:col-span-1">
      <CardHeader className="pb-2">
        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
          <TrendingUp className="h-4 w-4 text-muted-foreground" />
          Submission Trend (Last 30 Days)
        </CardTitle>
      </CardHeader>
      <CardContent>
        {error && <ErrorSlot message={error} />}
        {!error && data === null && <Spinner />}
        {!error && data !== null && <SubmissionTrendChart data={data} />}
      </CardContent>
    </Card>
  );
};

const StatusWidget: React.FC = () => {
  const [data, setData] = useState<StatusPoint[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    axios
      .get<StatusPoint[]>(route("dashboard.widgets.status-breakdown"))
      .then((res) => {
        if (!cancelled) setData(res.data);
      })
      .catch(() => {
        if (!cancelled) setError("Failed to load status data.");
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <Card className="col-span-2 lg:col-span-1">
      <CardHeader className="pb-2">
        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
          <PieChart className="h-4 w-4 text-muted-foreground" />
          Submissions by Status (This Month)
        </CardTitle>
      </CardHeader>
      <CardContent>
        {error && <ErrorSlot message={error} />}
        {!error && data === null && <Spinner />}
        {!error && data !== null && <StatusBreakdownChart data={data} />}
      </CardContent>
    </Card>
  );
};

// ---------------------------------------------------------------------------
// Row export — default export so React.lazy can load this chunk on demand
// ---------------------------------------------------------------------------

const ReportWidgetRow: React.FC = () => (
  <>
    <TrendWidget />
    <StatusWidget />
  </>
);

export default ReportWidgetRow;
