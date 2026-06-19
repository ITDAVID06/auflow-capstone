import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from "@/components/ui/select";
import TruncateTooltip from "@/components/TruncateTooltip";
import { Save, Loader2, CheckCircle2, AlertCircle, ArrowLeft } from "lucide-react";
import { Link } from "@inertiajs/react";
import { toast } from "sonner";
import type { Node } from "reactflow";

interface WorkflowHeaderProps {
  formState: any;
  setFormState: (state: any) => void;
  forms: { id: number; form_name: string }[];
  nodes: Node[];
  workflowId?: number;
  onSave: () => void;
  saveStatus: 'saved' | 'saving' | 'unsaved';
  isLoadingCanvas: boolean;
}

export default function WorkflowHeader({
  formState,
  setFormState,
  forms,
  nodes,
  workflowId,
  onSave,
  saveStatus,
  isLoadingCanvas,
}: WorkflowHeaderProps) {
  const selectedFormName =
    forms.find((f) => String(f.id) === String(formState.form_id))?.form_name ?? "";

  const handleWorkflowTypeChange = (newType: string) => {
    // Check if switching from Parallel to Sequential with existing branch nodes
    if (formState.workflow_type === "Parallel" && newType === "Sequential") {
      const branchNodes = nodes.filter((node) => node.type === "branchContainer");

      if (branchNodes.length > 0) {
        const branchLabels = branchNodes.map((n) => n.data?.label || "Unnamed Branch").join(", ");
        toast.error(
          `Cannot switch to Sequential workflow type`,
          {
            description: `Your workflow contains ${branchNodes.length} branch node(s): ${branchLabels}. Please remove all branch nodes before switching to Sequential.`,
            duration: 6000,
          }
        );
        return; // Prevent the change
      }
    }

    setFormState({ ...formState, workflow_type: newType });
  };

  return (
    <div className="sticky top-0 z-30 border-b bg-background/95 backdrop-blur-sm shadow-sm">
      <div className="flex items-center justify-between gap-3 px-4 py-2">
        {/* Back navigation */}
        <Link
          href={route("admin.workflows.index")}
          className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
          aria-label="Back to workflows"
        >
          <ArrowLeft className="h-4 w-4" />
        </Link>

        {/* Left: Workflow Settings */}
        <div className="flex items-center gap-3 flex-1 min-w-0">
          <div className="flex-1 min-w-[180px] max-w-[240px]">
            <Label htmlFor="workflow-name" className="sr-only">Workflow Name</Label>
            <Input
              id="workflow-name"
              aria-label="Workflow name"
              placeholder="Workflow Name"
              value={formState.workflow_name}
              onChange={(e) => setFormState({ ...formState, workflow_name: e.target.value })}
              className="h-8 text-sm font-semibold"
            />
          </div>

          <div className="w-[140px]">
            <Label htmlFor="workflow-type" className="sr-only">Workflow Type</Label>
            <Select value={formState.workflow_type} onValueChange={handleWorkflowTypeChange}>
              <SelectTrigger id="workflow-type" aria-label="Workflow type" className="h-8 text-sm">
                <SelectValue placeholder="Type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="Sequential">Sequential</SelectItem>
                <SelectItem value="Parallel">Parallel</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="w-[180px]">
            <Label htmlFor="linked-form" className="sr-only">Linked Form</Label>
            <Select
              value={String(formState.form_id)}
              onValueChange={(v) => setFormState({ ...formState, form_id: Number(v) })}
            >
              <SelectTrigger id="linked-form" aria-label="Linked form" className="h-8 text-sm truncate">
                <SelectValue placeholder="Select Form" />
              </SelectTrigger>
              <SelectContent>
                {forms.length === 0 ? (
                  <div className="px-2 py-6 text-center">
                    <p className="text-sm text-muted-foreground">No forms available</p>
                    <p className="text-xs text-muted-foreground mt-1">Create a form first to associate with this workflow</p>
                  </div>
                ) : (
                  forms.map((f) => (
                    <SelectItem key={f.id} value={String(f.id)}>
                      <div className="truncate max-w-[240px]">
                        <TruncateTooltip text={f.form_name} />
                      </div>
                    </SelectItem>
                  ))
                )}
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Right: Save Button + Status */}
        <div className="flex items-center gap-3">
          {/* Save Status Indicator — always visible */}
          <div className="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-md border border-border">
            {saveStatus === 'saved' && (
              <>
                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                <span className="text-emerald-700 dark:text-emerald-400 font-medium">Saved</span>
              </>
            )}
            {saveStatus === 'saving' && (
                <>
                  <Loader2 className="h-3.5 w-3.5 animate-spin text-primary" />
                  <span className="text-primary font-medium">Saving...</span>
                </>
              )}
              {saveStatus === 'unsaved' && (
                <>
                  <AlertCircle className="h-3.5 w-3.5 text-amber-500" />
                  <span className="text-amber-700 dark:text-amber-400 font-medium">Unsaved</span>
                </>
              )}
              {!workflowId && saveStatus !== 'saving' && (
                <span className="text-muted-foreground font-medium">New</span>
              )}
            </div>

          <Button size="sm" onClick={onSave} disabled={isLoadingCanvas || saveStatus === 'saving'}>
            {saveStatus === 'saving' ? (
              <Loader2 className="w-3.5 h-3.5 mr-1.5 animate-spin" />
            ) : (
              <Save className="w-3.5 h-3.5 mr-1.5" />
            )}
            {workflowId ? "Update" : "Save"}
          </Button>
        </div>
      </div>
    </div>
  );
}
