import React from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Save,
  Eye,
  ArrowLeft,
  Info,
  Undo2,
  Redo2,
  Cloud,
  CloudOff,
  Loader2,
} from "lucide-react";
import { Link } from "@inertiajs/react";
import { Badge } from "@/components/ui/badge";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { FormBuilderState } from "../types/formBuilderTypes";

type AutoSaveStatus = "idle" | "saving" | "saved" | "error";

interface FormBuilderHeaderProps {
  form: FormBuilderState;
  setForm: React.Dispatch<React.SetStateAction<FormBuilderState>>;
  isSaving: boolean;
  onSave: () => Promise<void>;
  onPreview: () => void;
  locked: boolean;
  // Undo / Redo
  canUndo?: boolean;
  canRedo?: boolean;
  onUndo?: () => void;
  onRedo?: () => void;
  // Auto-save
  autoSaveStatus?: AutoSaveStatus;
  lastSaved?: Date | null;
}

function AutoSaveIndicator({
  status,
  lastSaved,
}: {
  status: AutoSaveStatus;
  lastSaved?: Date | null;
}) {
  const formatTime = (d: Date) =>
    new Intl.DateTimeFormat("en-US", {
      timeZone: import.meta.env.VITE_DISPLAY_TIMEZONE || import.meta.env.VITE_APP_TIMEZONE || "Asia/Manila",
      hour: "numeric",
      minute: "2-digit",
    }).format(d);

  switch (status) {
    case "saving":
      return (
        <span className="flex items-center gap-1 text-xs text-muted-foreground animate-pulse">
          <Loader2 className="w-3 h-3 animate-spin" />
          Saving draft…
        </span>
      );
    case "saved":
      return (
        <span className="flex items-center gap-1 text-xs text-muted-foreground">
          <Cloud className="w-3 h-3 text-emerald-500" />
          {lastSaved ? `Draft saved ${formatTime(lastSaved)}` : "Draft saved"}
        </span>
      );
    case "error":
      return (
        <span className="flex items-center gap-1 text-xs text-destructive">
          <CloudOff className="w-3 h-3" />
          Draft save failed
        </span>
      );
    default:
      return null;
  }
}

export function FormBuilderHeader({
  form,
  setForm,
  isSaving,
  onSave,
  onPreview,
  locked,
  canUndo = false,
  canRedo = false,
  onUndo,
  onRedo,
  autoSaveStatus = "idle",
  lastSaved,
}: FormBuilderHeaderProps) {
  return (
    <>
      <div className="sticky top-0 z-50 border-b bg-background/95 backdrop-blur">
        <div className="mx-auto flex h-14 w-full max-w-[1520px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
          {/* Left: Back + Form Name */}
          <div className="flex items-center gap-4 flex-1 min-w-0">
            <Link
              href={route("admin.forms.index")}
              className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors shrink-0"
              aria-label="Back to forms"
            >
              <ArrowLeft className="w-4 h-4" aria-hidden="true" />
            </Link>

            <div className="flex items-center gap-2 flex-1 min-w-0 max-w-md">
              <Input
                value={form.form_name}
                onChange={(e) =>
                  setForm((f) => ({ ...f, form_name: e.target.value }))
                }
                placeholder="Untitled Form"
                disabled={locked}
                aria-label="Form name"
                className="h-8 text-sm font-medium border-none shadow-none bg-transparent hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:ring-1"
              />
            </div>

            {/* Status Badges */}
            <div className="flex items-center gap-2 shrink-0">
              {locked && (
                <Badge variant="secondary" className="h-6 text-xs">
                  <Info className="w-3 h-3 mr-1" />
                  Read Only
                </Badge>
              )}
              {form.status === "Active" && (
                <Badge className="bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 h-6 text-xs">
                  Active
                </Badge>
              )}
            </div>

            {/* Auto-save indicator */}
            <AutoSaveIndicator status={autoSaveStatus} lastSaved={lastSaved} />
          </div>

          {/* Right: Actions */}
          <div className="flex items-center gap-1.5 shrink-0">
            {/* Undo / Redo */}
            {!locked && (
              <TooltipProvider delayDuration={300}>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={onUndo}
                      disabled={!canUndo}
                    >
                      <Undo2 className="w-4 h-4" />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent side="bottom">
                    <p>Undo (Ctrl+Z)</p>
                  </TooltipContent>
                </Tooltip>

                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="h-8 w-8"
                      onClick={onRedo}
                      disabled={!canRedo}
                    >
                      <Redo2 className="w-4 h-4" />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent side="bottom">
                    <p>Redo (Ctrl+Shift+Z)</p>
                  </TooltipContent>
                </Tooltip>
              </TooltipProvider>
            )}

            <div className="w-px h-5 bg-border mx-1" />

            <Button
              variant="outline"
              size="sm"
              onClick={onPreview}
              className="h-8 text-xs"
            >
              <Eye className="w-3.5 h-3.5 mr-1.5" />
              Preview
            </Button>
            <Button
              onClick={onSave}
              disabled={isSaving || locked}
              size="sm"
              className="h-8 text-xs"
            >
              <Save className="w-3.5 h-3.5 mr-1.5" />
              {isSaving ? "Saving..." : form.id ? "Save Changes" : "Save Form"}
            </Button>
          </div>
        </div>
      </div>
    </>
  );
}
