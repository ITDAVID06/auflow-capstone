import React from "react";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";

interface KpiCardProps {
  label: string;
  value: string | number | null;
  loading?: boolean;
  className?: string;
}

export const KpiCard: React.FC<KpiCardProps> = ({ label, value, loading = false, className }) => (
  <Card className={cn("flex-1 min-w-[140px]", className)}>
    <CardContent className="pt-4 pb-3">
      <p className="text-xs text-muted-foreground uppercase tracking-wide mb-1">{label}</p>
      {loading ? (
        <div className="h-7 w-16 rounded bg-muted animate-pulse" />
      ) : (
        <p className="text-2xl font-semibold tabular-nums">{value ?? "—"}</p>
      )}
    </CardContent>
  </Card>
);
