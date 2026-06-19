import React, { useState } from "react";
import axios from "axios";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Download, ChevronDown } from "lucide-react";
import { toast } from "sonner";
import { buildReportQueryParams } from "../queryBuilder";
import { ReportFiltersState } from "../types";

type ExportLimit = 100 | 500 | 1000 | 5000 | "all";

interface ExportButtonProps {
  filters: ReportFiltersState;
  onAsyncExport: (exportId: string) => void;
}

export const ExportButton: React.FC<ExportButtonProps> = ({ filters, onAsyncExport }) => {
  const [limit, setLimit] = useState<ExportLimit>(1000);
  const [busy, setBusy] = useState(false);

  const doExport = async (type: "csv" | "pdf-print" | "pdf-server") => {
    setBusy(true);
    try {
      const params = {
        ...buildReportQueryParams(filters),
        export_limit: limit,
      };

      // Build safe query string from params
      const searchParams = new URLSearchParams();
      Object.entries(params).forEach(([k, v]) => {
        if (v !== null && v !== undefined) {
          const strValue = String(v);
          if (strValue !== "") {
            searchParams.append(k, strValue);
          }
        }
      });
      const queryString = searchParams.toString();

      if (type === "pdf-print") {
        window.open(route("reports.export-pdf") + "?" + queryString, "_blank");
        return;
      }

      if (type === "pdf-server") {
        window.location.href = route("reports.export-pdf-download") + "?" + queryString;
        return;
      }

      // CSV — may return 202 for async, 200 for immediate
      const response = await axios.get<{ export_id?: string } | Blob>(route("reports.export-csv"), {
        params,
        responseType: "json",
        validateStatus: (s) => s === 200 || s === 202,
      });

      if (response.status === 202) {
        const exportId = (response.data as { export_id?: string }).export_id;
        if (exportId) {
          onAsyncExport(exportId);
          toast.info("Large export queued. Check the Exports tab for download status.");
        }
        return;
      }

      // 200 response: trigger browser download via temporary link
      const csvUrl = route("reports.export-csv") + "?" + queryString;
      const a = document.createElement("a");
      a.href = csvUrl;
      a.download = `report-${Date.now()}.csv`;
      a.click();
    } catch {
      toast.error("Export failed. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="flex items-center gap-2">
      <Select
        value={String(limit)}
        onValueChange={(v) => setLimit(v === "all" ? "all" : (Number(v) as ExportLimit))}
      >
        <SelectTrigger className="w-28 h-8 text-sm">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {([100, 500, 1000, 5000] as const).map((n) => (
            <SelectItem key={n} value={String(n)}>
              {n.toLocaleString()} rows
            </SelectItem>
          ))}
          <SelectItem value="all">All rows</SelectItem>
        </SelectContent>
      </Select>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" size="sm" disabled={busy} className="gap-1">
            <Download className="h-4 w-4" />
            Export
            <ChevronDown className="h-3 w-3" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem onClick={() => doExport("csv")}>Download CSV</DropdownMenuItem>
          <DropdownMenuItem onClick={() => doExport("pdf-print")}>Download PDF (print)</DropdownMenuItem>
          <DropdownMenuItem onClick={() => doExport("pdf-server")}>Download PDF (server)</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
};
