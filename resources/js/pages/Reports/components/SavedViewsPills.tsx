import React, { useState } from "react";
import axios from "axios";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from "@/components/ui/dialog";
import { Trash2 } from "lucide-react";
import { toast } from "sonner";
import { ReportFiltersState, ReportView } from "../types";

interface SavedViewsPillsProps {
  views: ReportView[];
  currentFilters: ReportFiltersState;
  onLoad: (filters: ReportFiltersState) => void;
  onViewsChange: (views: ReportView[]) => void;
}

export const SavedViewsPills: React.FC<SavedViewsPillsProps> = ({
  views,
  currentFilters,
  onLoad,
  onViewsChange,
}) => {
  const [saveOpen, setSaveOpen] = useState(false);
  const [name, setName] = useState("");
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    if (!name.trim()) return;
    setSaving(true);
    try {
      const { data } = await axios.post<ReportView>(route("reports.views.store"), {
        form_id: currentFilters.form_id,
        name: name.trim(),
        filter_state: currentFilters,
      });
      onViewsChange([...views, data]);
      setSaveOpen(false);
      setName("");
      toast.success("View saved.");
    } catch {
      toast.error("Could not save view.");
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (view: ReportView) => {
    try {
      await axios.delete(route("reports.views.destroy", view.id));
      onViewsChange(views.filter((v) => v.id !== view.id));
      toast.success("View deleted.");
    } catch {
      toast.error("Could not delete view.");
    }
  };

  return (
    <>
      <div className="flex flex-wrap items-center gap-2">
        {views.map((view) => (
          <Badge
            key={view.id}
            variant="secondary"
            className="cursor-pointer flex items-center gap-1 pr-1"
          >
            <span onClick={() => onLoad(view.filter_state)} className="px-1">
              {view.name}
            </span>
            <button
              onClick={() => handleDelete(view)}
              className="hover:text-destructive ml-0.5"
              aria-label={`Delete view "${view.name}"`}
            >
              <Trash2 className="h-3 w-3" />
            </button>
          </Badge>
        ))}
        <Button variant="outline" size="sm" onClick={() => setSaveOpen(true)}>
          Save current view
        </Button>
      </div>

      <Dialog open={saveOpen} onOpenChange={setSaveOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>Save view</DialogTitle>
          </DialogHeader>
          <Input
            placeholder="View name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            onKeyDown={(e) => e.key === "Enter" && handleSave()}
            autoFocus
          />
          <DialogFooter>
            <Button variant="outline" onClick={() => setSaveOpen(false)}>
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={saving || !name.trim()}>
              Save
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
};
