import React, { useEffect, useState } from "react";
import axios from "axios";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { toast } from "sonner";
import { ReportFiltersState, ScheduledExport, FilterStateSnapshot } from "../types";

type Frequency = "daily" | "weekly" | "monthly";
type ExportType = "csv" | "pdf";

interface ScheduledExportModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  formId: number;
  existing?: ScheduledExport | null;
  prefillFilters?: ReportFiltersState | null;
  onSaved: (export_: ScheduledExport) => void;
}

export const ScheduledExportModal: React.FC<ScheduledExportModalProps> = ({
  open,
  onOpenChange,
  formId,
  existing,
  prefillFilters,
  onSaved,
}) => {
  const [email, setEmail] = useState("");
  const [frequency, setFrequency] = useState<Frequency>("weekly");
  const [exportType, setExportType] = useState<ExportType>("csv");
  const [isActive, setIsActive] = useState(true);
  const [filterSnapshot, setFilterSnapshot] = useState<FilterStateSnapshot | null>(null);
  const [saving, setSaving] = useState(false);

  // Populate form when modal opens
  useEffect(() => {
    if (!open) return;
    if (existing) {
      setEmail(existing.recipient_email);
      setFrequency(existing.frequency);
      setExportType(existing.export_type);
      setIsActive(existing.is_active);
      setFilterSnapshot(existing.filter_state as FilterStateSnapshot | null);
    } else {
      setEmail("");
      setFrequency("weekly");
      setExportType("csv");
      setIsActive(true);
      // Copy current Data tab filters as snapshot (if provided)
      if (prefillFilters) {
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        const { form_id, page, per_page, ...rest } = prefillFilters;
        setFilterSnapshot(rest as FilterStateSnapshot);
      } else {
        setFilterSnapshot(null);
      }
    }
  }, [open, existing, prefillFilters]);

  const handleSave = async () => {
    if (!email.trim()) {
      toast.error("Recipient email is required.");
      return;
    }
    setSaving(true);
    try {
      const payload = {
        form_id: formId,
        recipient_email: email.trim(),
        frequency,
        export_type: exportType,
        is_active: isActive,
        filter_state: filterSnapshot,
      };

      const { data } = existing
        ? await axios.put<ScheduledExport>(route("reports.scheduled-exports.update", existing.id), payload)
        : await axios.post<ScheduledExport>(route("reports.scheduled-exports.store"), payload);

      onSaved(data);
      onOpenChange(false);
      toast.success(existing ? "Schedule updated." : "Schedule created.");
    } catch {
      toast.error("Could not save schedule. Please check your inputs.");
    } finally {
      setSaving(false);
    }
  };

  const hasFilters =
    filterSnapshot !== null &&
    (filterSnapshot.filters.length > 0 ||
      filterSnapshot.date_from ||
      filterSnapshot.submission_status);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{existing ? "Edit schedule" : "New scheduled export"}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-1">
            <Label>Recipient email</Label>
            <Input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="reports@company.com"
              autoFocus
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label>Frequency</Label>
              <Select value={frequency} onValueChange={(v) => setFrequency(v as Frequency)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="daily">Daily</SelectItem>
                  <SelectItem value="weekly">Weekly</SelectItem>
                  <SelectItem value="monthly">Monthly</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1">
              <Label>Format</Label>
              <Select value={exportType} onValueChange={(v) => setExportType(v as ExportType)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="csv">CSV</SelectItem>
                  <SelectItem value="pdf">PDF</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          {hasFilters && (
            <div className="rounded border bg-muted/40 px-3 py-2 text-sm text-muted-foreground flex items-center justify-between">
              <span>Filters from current view will be saved with this schedule.</span>
              <Button variant="ghost" size="sm" onClick={() => setFilterSnapshot(null)}>
                Clear
              </Button>
            </div>
          )}

          {existing && (
            <div className="flex items-center justify-between">
              <Label>Active</Label>
              <Switch checked={isActive} onCheckedChange={setIsActive} />
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saving}>
            {saving ? "Saving…" : existing ? "Update" : "Create"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};
