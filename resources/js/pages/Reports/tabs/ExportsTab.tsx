import React, { useCallback, useEffect, useRef, useState } from "react";
import axios from "axios";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/components/ui/table";
import { Switch } from "@/components/ui/switch";
import { Download, Pencil, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { AsyncExportStatus, ReportFiltersState, ScheduledExport } from "../types";
import { ScheduledExportModal } from "../components/ScheduledExportModal";

const POLL_INTERVAL_MS = 3000;

const STATUS_BADGE: Record<string, { label: string; variant: "default" | "secondary" | "outline" | "destructive" }> = {
  queued:     { label: "Queued",     variant: "secondary" },
  processing: { label: "Processing", variant: "default" },
  completed:  { label: "Ready",      variant: "outline" },
  failed:     { label: "Failed",     variant: "destructive" },
};

interface ExportsTabProps {
  formId: number;
  activeExportId: string | null;
  onExportIdChange: (id: string | null) => void;
  dataTabFilters?: ReportFiltersState | null;
}

export const ExportsTab: React.FC<ExportsTabProps> = ({
  formId,
  activeExportId,
  onExportIdChange,
  dataTabFilters,
}) => {
  const [exportStatus, setExportStatus] = useState<AsyncExportStatus | null>(null);
  const [scheduledExports, setScheduledExports] = useState<ScheduledExport[]>([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingExport, setEditingExport] = useState<ScheduledExport | null>(null);
  const pollTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Poll active export
  const pollExport = useCallback((exportId: string) => {
    axios
      .get<AsyncExportStatus>(route("reports.exports.status", exportId))
      .then((r) => {
        setExportStatus(r.data);
        if (r.data.status === "queued" || r.data.status === "processing") {
          pollTimerRef.current = setTimeout(() => pollExport(exportId), POLL_INTERVAL_MS);
        }
      })
      .catch(() => {
        setExportStatus(null);
        onExportIdChange(null);
      });
  }, [onExportIdChange]);

  useEffect(() => {
    if (activeExportId) {
      pollExport(activeExportId);
    } else {
      setExportStatus(null);
    }
    return () => {
      if (pollTimerRef.current) clearTimeout(pollTimerRef.current);
    };
  }, [activeExportId, pollExport]);

  // Load scheduled exports
  useEffect(() => {
    axios
      .get<ScheduledExport[]>(route("reports.scheduled-exports.index"), { params: { form_id: formId } })
      .then((r) => setScheduledExports(r.data))
      .catch(() => {/* non-fatal */});
  }, [formId]);

  const handleToggleActive = async (export_: ScheduledExport) => {
    try {
      const { data } = await axios.put<ScheduledExport>(
        route("reports.scheduled-exports.update", export_.id),
        { is_active: !export_.is_active }
      );
      setScheduledExports((prev) => prev.map((e) => (e.id === data.id ? data : e)));
    } catch {
      toast.error("Could not update schedule.");
    }
  };

  const handleDelete = async (export_: ScheduledExport) => {
    if (!window.confirm(`Delete schedule "${export_.recipient_email} (${export_.frequency})"?`)) return;
    try {
      await axios.delete(route("reports.scheduled-exports.destroy", export_.id));
      setScheduledExports((prev) => prev.filter((e) => e.id !== export_.id));
      toast.success("Schedule deleted.");
    } catch {
      toast.error("Could not delete schedule.");
    }
  };

  const handleSaved = (saved: ScheduledExport) => {
    setScheduledExports((prev) => {
      const idx = prev.findIndex((e) => e.id === saved.id);
      return idx >= 0 ? prev.map((e) => (e.id === saved.id ? saved : e)) : [...prev, saved];
    });
  };

  return (
    <div className="space-y-6">
      {/* Active async export — only shown when in-flight or ready */}
      {activeExportId && exportStatus && (
        <Alert variant={exportStatus.status === "failed" ? "destructive" : "default"} className="flex items-center gap-4">
          <AlertDescription className="flex items-center gap-3 w-full">
            <Badge variant={STATUS_BADGE[exportStatus.status]?.variant ?? "outline"}>
              {STATUS_BADGE[exportStatus.status]?.label ?? exportStatus.status}
            </Badge>
            {exportStatus.filename && (
              <span className="text-sm text-muted-foreground flex-1">{exportStatus.filename}</span>
            )}
            {exportStatus.status === "completed" && (
              <Button asChild size="sm" variant="outline" className="ml-auto">
                <a href={route("reports.exports.download", exportStatus.export_id)}>
                  <Download className="h-4 w-4 mr-1" /> Download
                </a>
              </Button>
            )}
            {exportStatus.status === "failed" && (
              <span className="text-sm ml-auto">{exportStatus.error ?? "Export failed."}</span>
            )}
          </AlertDescription>
        </Alert>
      )}

      {/* Scheduled exports */}
      <Card>
        <CardHeader className="pb-2 flex flex-row items-center justify-between">
          <CardTitle className="text-sm font-medium">Scheduled Exports</CardTitle>
          <Button
            size="sm"
            variant="outline"
            className="gap-1"
            onClick={() => { setEditingExport(null); setModalOpen(true); }}
          >
            <Plus className="h-4 w-4" /> New schedule
          </Button>
        </CardHeader>
        <CardContent className="p-0">
          {scheduledExports.length === 0 ? (
            <p className="text-sm text-muted-foreground p-4">No scheduled exports for this form.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Email</TableHead>
                  <TableHead>Frequency</TableHead>
                  <TableHead>Format</TableHead>
                  <TableHead>Last sent</TableHead>
                  <TableHead>Active</TableHead>
                  <TableHead className="w-16" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {scheduledExports.map((e) => (
                  <TableRow key={e.id}>
                    <TableCell>{e.recipient_email}</TableCell>
                    <TableCell>
                      <Badge variant="secondary">{e.frequency}</Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline">{e.export_type.toUpperCase()}</Badge>
                    </TableCell>
                    <TableCell className="text-muted-foreground text-sm">
                      {e.last_sent_at ? new Date(e.last_sent_at).toLocaleDateString() : "Never"}
                    </TableCell>
                    <TableCell>
                      <Switch
                        checked={e.is_active}
                        onCheckedChange={() => handleToggleActive(e)}
                        aria-label={`Toggle ${e.recipient_email} schedule`}
                      />
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7"
                          onClick={() => { setEditingExport(e); setModalOpen(true); }}
                          aria-label="Edit schedule"
                        >
                          <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7 text-destructive hover:text-destructive"
                          onClick={() => handleDelete(e)}
                          aria-label="Delete schedule"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <ScheduledExportModal
        open={modalOpen}
        onOpenChange={setModalOpen}
        formId={formId}
        existing={editingExport}
        prefillFilters={editingExport ? null : dataTabFilters}
        onSaved={handleSaved}
      />
    </div>
  );
};
