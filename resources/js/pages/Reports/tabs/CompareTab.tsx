import React, { useEffect, useState } from "react";
import axios from "axios";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/components/ui/table";
import { Separator } from "@/components/ui/separator";
import {
  BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer,
} from "recharts";
import { ReportColumn, ReportForm } from "../types";

type CompareMetric = "submission_count" | "avg_completion_time" | "approval_rate";
type AggFunction = "count" | "sum" | "avg" | "min" | "max";

interface CompareResult {
  form_name: string;
  value: number | string;
}

interface AggResult {
  group_value: string | null;
  aggregate_value: number | string | null;
}

interface CompareTabProps {
  /** Top-level selected form (used by aggregation tool only). null means none selected. */
  formId: number | null;
}

const METRICS: { value: CompareMetric; label: string }[] = [
  { value: "submission_count",  label: "Submission Count" },
  { value: "avg_completion_time", label: "Avg. Completion Time" },
  { value: "approval_rate",     label: "Approval Rate" },
];

const AGG_FUNCTIONS: { value: AggFunction; label: string }[] = [
  { value: "count", label: "Count" },
  { value: "sum",   label: "Sum" },
  { value: "avg",   label: "Avg" },
  { value: "min",   label: "Min" },
  { value: "max",   label: "Max" },
];

export const CompareTab: React.FC<CompareTabProps> = ({ formId }) => {
  // Cross-form comparison state
  const [allForms, setAllForms] = useState<ReportForm[]>([]);
  const [selectedFormIds, setSelectedFormIds] = useState<number[]>([]);
  const [metric, setMetric] = useState<CompareMetric>("submission_count");
  const [compareFrom, setCompareFrom] = useState("");
  const [compareTo, setCompareTo] = useState("");
  const [compareResults, setCompareResults] = useState<CompareResult[] | null>(null);
  const [compareLoading, setCompareLoading] = useState(false);
  const [compareError, setCompareError] = useState<string | null>(null);

  // Aggregation state
  const [aggColumns, setAggColumns] = useState<ReportColumn[]>([]);
  const [groupBy, setGroupBy] = useState<string>("");
  const [aggFunction, setAggFunction] = useState<AggFunction>("count");
  const [aggColumn, setAggColumn] = useState<string>("");
  const [aggResults, setAggResults] = useState<AggResult[] | null>(null);
  const [aggLoading, setAggLoading] = useState(false);
  const [aggError, setAggError] = useState<string | null>(null);

  // Load all forms for comparison picker
  useEffect(() => {
    axios.get<ReportForm[]>(route("reports.forms")).then((r) => setAllForms(r.data)).catch(() => {});
  }, []);

  // Load filterable columns when formId changes (for aggregation)
  useEffect(() => {
    if (!formId) return;
    axios
      .get<{ builder: { filterable_columns: ReportColumn[] } }>(
        route("reports.form-submissions"),
        { params: { form_id: formId, per_page: 1 } }
      )
      .then((r) => setAggColumns(r.data.builder?.filterable_columns ?? []))
      .catch(() => {});
  }, [formId]);

  const toggleForm = (id: number) => {
    setSelectedFormIds((prev) =>
      prev.includes(id)
        ? prev.filter((f) => f !== id)
        : prev.length < 10 ? [...prev, id] : prev
    );
  };

  const runComparison = async () => {
    setCompareLoading(true);
    setCompareError(null);
    try {
      const { data } = await axios.get<{ data: CompareResult[] }>(route("reports.compare"), {
        params: {
          form_ids: selectedFormIds,
          metric,
          date_from: compareFrom || undefined,
          date_to: compareTo || undefined,
        },
      });
      setCompareResults(data.data);
    } catch {
      setCompareError("Could not run comparison.");
    } finally {
      setCompareLoading(false);
    }
  };

  const runAggregation = async () => {
    if (!formId || !groupBy) return;
    setAggLoading(true);
    setAggError(null);
    try {
      const { data } = await axios.get<{ data: AggResult[] }>(route("reports.aggregate"), {
        params: {
          form_id: formId,
          group_by: groupBy,
          function: aggFunction,
          column: aggFunction !== "count" ? aggColumn : undefined,
        },
      });
      setAggResults(data.data);
    } catch {
      setAggError("Could not compute aggregation.");
    } finally {
      setAggLoading(false);
    }
  };

  const numericFunctions: AggFunction[] = ["sum", "avg", "min", "max"];

  return (
    <div className="space-y-8">
      {/* Cross-form comparison */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium">Cross-Form Comparison</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <Label className="text-xs text-muted-foreground mb-2 block">
              Select forms to compare (max 10)
            </Label>
            <div className="flex flex-wrap gap-2 max-h-36 overflow-y-auto">
              {allForms.map((f) => (
                <button
                  key={f.id}
                  onClick={() => toggleForm(f.id)}
                  className={`rounded-full border px-3 py-1 text-sm transition-colors ${
                    selectedFormIds.includes(f.id)
                      ? "bg-primary text-primary-foreground border-primary"
                      : "hover:bg-muted"
                  }`}
                >
                  {f.form_name}
                </button>
              ))}
            </div>
          </div>

          <div className="flex flex-wrap items-end gap-3">
            <div className="flex flex-col gap-1">
              <Label className="text-xs text-muted-foreground">Metric</Label>
              <Select value={metric} onValueChange={(v) => setMetric(v as CompareMetric)}>
                <SelectTrigger className="w-48 h-8 text-sm">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {METRICS.map((m) => (
                    <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-1">
              <Label className="text-xs text-muted-foreground">From</Label>
              <Input type="date" className="w-36 h-8 text-sm" value={compareFrom} onChange={(e) => setCompareFrom(e.target.value)} />
            </div>
            <div className="flex flex-col gap-1">
              <Label className="text-xs text-muted-foreground">To</Label>
              <Input type="date" className="w-36 h-8 text-sm" value={compareTo} onChange={(e) => setCompareTo(e.target.value)} />
            </div>
            <Button
              size="sm"
              onClick={runComparison}
              disabled={selectedFormIds.length < 2 || compareLoading}
            >
              {compareLoading ? "Running…" : "Run Comparison"}
            </Button>
          </div>

          {compareError && (
            <Alert variant="destructive"><AlertDescription>{compareError}</AlertDescription></Alert>
          )}

          {compareResults && (
            <div className="space-y-4">
              <ResponsiveContainer width="100%" height={200}>
                <BarChart data={compareResults}>
                  <XAxis dataKey="form_name" tick={{ fontSize: 11 }} />
                  <YAxis tick={{ fontSize: 11 }} />
                  <Tooltip />
                  <Bar dataKey="value" fill="#3b82f6" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Form</TableHead>
                    <TableHead>{METRICS.find((m) => m.value === metric)?.label}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {compareResults.map((r) => (
                    <TableRow key={r.form_name}>
                      <TableCell>{r.form_name}</TableCell>
                      <TableCell>{r.value}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>

      <Separator />

      {/* Aggregation */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium">Aggregation</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {!formId ? (
            <p className="text-sm text-muted-foreground">Select a form using the top picker to use the aggregation tool.</p>
          ) : (
            <>
              <div className="flex flex-wrap items-end gap-3">
                <div className="flex flex-col gap-1">
                  <Label className="text-xs text-muted-foreground">Group by</Label>
                  <Select value={groupBy} onValueChange={setGroupBy}>
                    <SelectTrigger className="w-44 h-8 text-sm">
                      <SelectValue placeholder="Select column" />
                    </SelectTrigger>
                    <SelectContent>
                      {aggColumns.map((c) => (
                        <SelectItem key={c.key} value={c.key}>{c.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="flex flex-col gap-1">
                  <Label className="text-xs text-muted-foreground">Function</Label>
                  <Select value={aggFunction} onValueChange={(v) => setAggFunction(v as AggFunction)}>
                    <SelectTrigger className="w-28 h-8 text-sm">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {AGG_FUNCTIONS.map((f) => (
                        <SelectItem key={f.value} value={f.value}>{f.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                {numericFunctions.includes(aggFunction) && (
                  <div className="flex flex-col gap-1">
                    <Label className="text-xs text-muted-foreground">Column</Label>
                    <Select value={aggColumn} onValueChange={setAggColumn}>
                      <SelectTrigger className="w-44 h-8 text-sm">
                        <SelectValue placeholder="Select column" />
                      </SelectTrigger>
                      <SelectContent>
                        {aggColumns
                          .filter((c) => c.type === "number" || c.type === "integer")
                          .map((c) => (
                            <SelectItem key={c.key} value={c.key}>{c.label}</SelectItem>
                          ))}
                      </SelectContent>
                    </Select>
                  </div>
                )}

                <Button
                  size="sm"
                  onClick={runAggregation}
                  disabled={!groupBy || aggLoading || (numericFunctions.includes(aggFunction) && !aggColumn)}
                >
                  {aggLoading ? "Running…" : "Run Aggregation"}
                </Button>
              </div>

              {aggError && (
                <Alert variant="destructive"><AlertDescription>{aggError}</AlertDescription></Alert>
              )}

              {aggResults && (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Group</TableHead>
                      <TableHead>{aggFunction.toUpperCase()}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {aggResults.map((r, i) => (
                      <TableRow key={i}>
                        <TableCell>{r.group_value ?? "(empty)"}</TableCell>
                        <TableCell>{r.aggregate_value}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
};
