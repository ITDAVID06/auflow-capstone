import React, { useState } from "react";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from "@/components/ui/select";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Trash2, User2, Plus, X, Clock, Info, AlertTriangle, GitBranch, CheckSquare } from "lucide-react";
import type { Node } from "reactflow";
import type { ApproverCondition, BranchCondition } from "../types/workflowBuilderTypes";
import { isBranchContainer } from "../utils/branchLayout";

/** Build a compact interval string like "30min", "2hours", "1day". */
function buildReminderInterval(value: number | null, unit: string): string | null {
  if (!value || !unit) return null;
  const suffix = unit === "minutes" ? "min" : unit === "hours" ? "hour" : "day";
  const plural = value > 1 && unit !== "minutes" ? "s" : "";
  return `${value}${suffix}${plural}`;
}

export interface StepPropertiesPanelProps {
  selectedNode: Node | null;
  updateNode: (id: string, updates: any) => void;
  removeNode: (id: string) => void;
  users: Array<{ id: number | string; name: string }>;
  workflowType: string;
  formFields: { id: number; field_name: string; label: string; data_type: string }[];
}

const typeBadge: Record<
  string,
  { label: string; header: string; dot: string; bg: string }
> = {
  approval: {
    label: "ACTION",
    header: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300",
    dot: "bg-emerald-500 dark:bg-emerald-400",
  },
};

export default function StepPropertiesPanel({
  selectedNode,
  updateNode,
  removeNode,
  users,
  workflowType: _workflowType,
  formFields,
}: StepPropertiesPanelProps) {
  const [showDeleteModal, setShowDeleteModal] = useState(false);

  // Derive initial approvers — must be called before any early return (Rules of Hooks).
  const deriveApprovers = (): ApproverCondition[] => {
    if (selectedNode?.data?.approvers && Array.isArray(selectedNode.data.approvers)) {
      return selectedNode.data.approvers;
    }
    if (selectedNode?.data?.assigned_account_id) {
      return [{
        account_id: selectedNode.data.assigned_account_id,
        user_name: selectedNode.data?.assigned_user_name || '',
        condition: 'primary',
        order: 0
      }];
    }
    return [{ account_id: null, user_name: '', condition: 'primary', order: 0 }];
  };

  const [approvers, setApprovers] = React.useState<ApproverCondition[]>(deriveApprovers);
  const [showAddCondition, setShowAddCondition] = React.useState(approvers.length === 1);

  // Re-sync approver state whenever the selected node changes.
  React.useEffect(() => {
    const initial = deriveApprovers();
    setApprovers(initial);
    setShowAddCondition(initial.length === 1);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedNode?.id]);

  if (!selectedNode || selectedNode.id === "start" || isBranchContainer(selectedNode)) return null;

  const t = String(selectedNode.data?.type || "approval");
  const badge = typeBadge[t] || typeBadge.approval;

  const watched: string[] = Array.isArray(selectedNode.data?.watch_fields)
    ? (selectedNode.data?.watch_fields as string[])
    : [];

  // Update approvers in node data
  const updateApprovers = (newApprovers: ApproverCondition[]) => {
    setApprovers(newApprovers);
    updateNode(selectedNode.id, {
      approvers: newApprovers,
      // Backwards compatibility: set assigned_account_id to first approver
      assigned_account_id: newApprovers[0]?.account_id || null,
      assigned_user_name: newApprovers[0]?.user_name || '',
    });
    setShowAddCondition(newApprovers.length === 1);
  };

  const handlePrimaryApproverChange = (accountId: string, userName: string) => {
    const newApprovers = [...approvers];
    newApprovers[0] = {
      ...newApprovers[0],
      account_id: accountId === "none" ? null : Number(accountId),
      user_name: accountId === "none" ? '' : userName,
      condition: 'primary',
      order: 0
    };
    updateApprovers(newApprovers);
  };

  const addOrCondition = () => {
    const newApprovers = [
      ...approvers,
      {
        account_id: null,
        user_name: '',
        condition: 'or' as const,
        order: approvers.length
      }
    ];
    updateApprovers(newApprovers);
  };

  const removeOrCondition = (index: number) => {
    if (index === 0) return; // Can't remove primary
    const newApprovers = approvers.filter((_, i) => i !== index)
      .map((a, i) => ({ ...a, order: i })); // Reorder
    updateApprovers(newApprovers);
  };

  const updateOrApprover = (index: number, accountId: string, userName: string) => {
    const newApprovers = [...approvers];
    newApprovers[index] = {
      ...newApprovers[index],
      account_id: accountId === "none" ? null : Number(accountId),
      user_name: accountId === "none" ? '' : userName,
    };
    updateApprovers(newApprovers);
  };

  return (
    <div className="flex flex-col gap-0">

      {/* ── Identity ── */}
      <section className="space-y-3 pb-4">
        {/* Type pill + group */}
        <div className="flex items-center justify-between">
          <span className="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-muted/50 px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
            <span className={`h-1.5 w-1.5 rounded-full ${badge.dot}`} />
            {badge.label}
          </span>
          <span className="text-[11px] text-muted-foreground">
            Group <span className="font-medium text-foreground">{(selectedNode.data as any)?.step_group ?? "—"}</span>
          </span>
        </div>

        {/* Step Name */}
        <div className="space-y-1.5">
          <Label htmlFor="step-name" className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
            Step Name
          </Label>
          <Input
            id="step-name"
            value={selectedNode.data?.step_name || ""}
            onChange={(e) => {
              const value = e.target.value;
              updateNode(selectedNode.id, { step_name: value, label: value });
            }}
            placeholder="e.g., Department Review"
            maxLength={100}
          />
        </div>

        {/* Description */}
        <div className="space-y-1.5">
          <Label htmlFor="step-desc" className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
            Description
          </Label>
          <Textarea
            id="step-desc"
            rows={3}
            value={selectedNode.data?.description || ""}
            onChange={(e) => updateNode(selectedNode.id, { description: e.target.value })}
            placeholder="Optional notes or instructions for this step…"
            className="resize-none text-sm"
          />
        </div>
      </section>

      <div className="border-t border-border/60" />

      {/* ── Approvers ── */}
      <section className="space-y-3 py-4">
        <div className="flex items-center gap-2">
          <User2 className="h-3.5 w-3.5 text-muted-foreground" />
          <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Approvers</span>
        </div>

        {/* Primary Approver */}
        <div className="space-y-1.5">
          <Label htmlFor="primary-approver" className="text-xs text-foreground">
            Assign To
          </Label>
          <Select
            value={approvers[0]?.account_id ? String(approvers[0].account_id) : "none"}
            onValueChange={(v) => {
              const name = users.find((u) => String(u.id) === v)?.name || "";
              handlePrimaryApproverChange(v, name);
            }}
          >
            <SelectTrigger id="primary-approver" className="h-9">
              <SelectValue placeholder="Select user" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">— Unassigned —</SelectItem>
              {users
                .filter((u) => u.id !== null && u.id !== undefined)
                .map((u) => (
                  <SelectItem key={String(u.id)} value={String(u.id)}>
                    {u.name}
                  </SelectItem>
                ))}
            </SelectContent>
          </Select>
        </div>

        {/* OR Conditions */}
        {approvers.slice(1).map((approver, idx) => {
          const actualIndex = idx + 1;
          return (
            <div key={actualIndex} className="rounded-lg border border-border/60 bg-muted/30 p-3 space-y-2">
              <div className="flex items-center justify-between">
                <span className="rounded border border-border/60 bg-background px-2 py-0.5 text-[11px] font-bold tracking-wider text-muted-foreground">
                  OR
                </span>
                <button
                  type="button"
                  onClick={() => removeOrCondition(actualIndex)}
                  className="flex h-6 w-6 items-center justify-center rounded text-muted-foreground motion-safe:transition-colors hover:bg-destructive/10 hover:text-destructive"
                  aria-label="Remove OR condition"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              </div>
              <Select
                value={approver.account_id ? String(approver.account_id) : "none"}
                onValueChange={(v) => {
                  const name = users.find((u) => String(u.id) === v)?.name || "";
                  updateOrApprover(actualIndex, v, name);
                }}
              >
                <SelectTrigger className="h-9">
                  <SelectValue placeholder="Select user" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">— Unassigned —</SelectItem>
                  {users
                    .filter((u) => u.id !== null && u.id !== undefined)
                    .map((u) => (
                      <SelectItem key={String(u.id)} value={String(u.id)}>
                        {u.name}
                      </SelectItem>
                    ))}
                </SelectContent>
              </Select>
            </div>
          );
        })}

        {/* Add condition button */}
        {approvers[0]?.account_id && (
          <button
            type="button"
            onClick={addOrCondition}
            className="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground motion-safe:transition-colors hover:bg-muted hover:text-foreground"
          >
            <Plus className="h-3.5 w-3.5" />
            Add OR condition
          </button>
        )}

        {/* Multi-approver summary */}
        {approvers.length > 1 && (
          <p className="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
            <span className="font-semibold text-foreground">{approvers.filter((a) => a.account_id).length}</span> approver{approvers.filter((a) => a.account_id).length !== 1 ? "s" : ""} assigned —{" "}
            <span className="font-medium text-foreground">any one</span> can complete this step.
          </p>
        )}
      </section>

      <div className="border-t border-border/60" />

      {/* ── Reminders ── */}
      <section className="space-y-3 py-4">
        <div className="flex items-center gap-2">
          <Clock className="h-3.5 w-3.5 text-muted-foreground" />
          <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Reminders</span>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="reminder-mode" className="text-xs text-foreground">Frequency</Label>
          <Select
            value={selectedNode.data?.reminder_mode || "default"}
            onValueChange={(v) => {
              if (v === "default" || v === "none") {
                updateNode(selectedNode.id, {
                  reminder_mode: v,
                  reminder_interval: v,
                  reminder_value: null,
                  reminder_unit: null,
                });
              } else {
                updateNode(selectedNode.id, {
                  reminder_mode: v,
                  reminder_value: null,
                  reminder_unit: "hours",
                });
              }
            }}
          >
            <SelectTrigger id="reminder-mode" className="h-9">
              <SelectValue placeholder="Use default settings" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="default">System Default (1 day interval)</SelectItem>
              <SelectItem value="custom">Custom interval</SelectItem>
              <SelectItem value="none">No reminders</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Custom interval inputs */}
        {selectedNode.data?.reminder_mode === "custom" && (
          <div className="space-y-1.5">
            <Label className="text-xs text-foreground">Custom Interval</Label>
            <div className="flex items-center gap-2">
              <Input
                type="number"
                min="1"
                step="1"
                value={selectedNode.data?.reminder_value || ""}
                onChange={(e) => {
                  const value = e.target.value === "" ? null : Number(e.target.value);
                  const unit = selectedNode.data?.reminder_unit || "hours";
                  updateNode(selectedNode.id, {
                    reminder_value: value,
                    reminder_interval: buildReminderInterval(value, unit),
                  });
                }}
                placeholder="e.g., 30"
                className="h-9 flex-1"
              />
              <Select
                value={selectedNode.data?.reminder_unit || "hours"}
                onValueChange={(unit) => {
                  const value = selectedNode.data?.reminder_value;
                  updateNode(selectedNode.id, {
                    reminder_unit: unit,
                    reminder_interval: buildReminderInterval(value, unit),
                  });
                }}
              >
                <SelectTrigger className="h-9 w-28">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="minutes">Minutes</SelectItem>
                  <SelectItem value="hours">Hours</SelectItem>
                  <SelectItem value="days">Days</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        )}

        {/* Summary note */}
        <p className="flex items-start gap-1.5 rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
          <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
          <span>
            {selectedNode.data?.reminder_mode === "none"
              ? "No reminder emails will be sent for this step."
              : selectedNode.data?.reminder_mode === "custom" && selectedNode.data?.reminder_value
              ? <>Reminders sent every <strong className="text-foreground">{selectedNode.data.reminder_value} {selectedNode.data.reminder_unit || "hours"}</strong>, up to 3 total.</>
              : <>Reminders sent at <strong className="text-foreground">1, 2 and 3 days</strong> after the step becomes pending.</>}
          </span>
        </p>
      </section>

      <div className="border-t border-border/60" />

      {/* ── Fields to Watch ── */}
      <section className="space-y-3 py-4">
        <div className="flex items-center gap-2">
          <CheckSquare className="h-3.5 w-3.5 text-muted-foreground" />
          <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Fields to Watch</span>
          <span className="ml-auto text-[10px] text-muted-foreground">optional</span>
        </div>

        <div className="rounded-lg border border-border/60 bg-background">
          <div className="max-h-40 overflow-y-auto p-2 space-y-0.5">
            {formFields.length === 0 ? (
              <p className="px-2 py-2 text-xs text-muted-foreground">
                Associate a form with this workflow to list its fields.
              </p>
            ) : (
              formFields.map((f) => {
                const checked = watched.includes(f.field_name);
                return (
                  <label
                    key={f.id}
                    className="flex cursor-pointer items-center gap-2.5 rounded px-2 py-1.5 text-sm motion-safe:transition-colors hover:bg-muted/50"
                  >
                    <input
                      type="checkbox"
                      className="h-3.5 w-3.5 accent-foreground"
                      checked={checked}
                      onChange={(e) => {
                        const next = new Set<string>(watched);
                        e.target.checked ? next.add(f.field_name) : next.delete(f.field_name);
                        updateNode(selectedNode.id, { watch_fields: Array.from(next) });
                      }}
                    />
                    <span className="min-w-0 flex-1 truncate">
                      {f.label}
                      <span className="ml-1 text-xs text-muted-foreground">({f.field_name})</span>
                    </span>
                  </label>
                );
              })
            )}
          </div>
        </div>
      </section>

      <div className="border-t border-border/60" />

      {/* ── Branch Condition ── */}
      <section className="space-y-3 py-4">
        <div className="flex items-center gap-2">
          <GitBranch className="h-3.5 w-3.5 text-muted-foreground" />
          <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Branch Condition</span>
          <span className="ml-auto text-[10px] text-muted-foreground">optional</span>
        </div>
        <p className="text-xs text-muted-foreground">
          Skip this step when the condition is <strong className="text-foreground">not</strong> met.
        </p>

        {(() => {
          const cond: BranchCondition | null = (selectedNode.data as any)?.branch_condition ?? null;

          const updateCondition = (patch: Partial<BranchCondition> | null) => {
            if (patch === null) {
              updateNode(selectedNode.id, { branch_condition: null });
              return;
            }
            const next: BranchCondition = {
              field: cond?.field ?? "",
              operator: cond?.operator ?? ">",
              value: cond?.value ?? "",
              ...patch,
            };
            updateNode(selectedNode.id, { branch_condition: next });
          };

          if (!cond) {
            return (
              <button
                type="button"
                onClick={() => updateCondition({ field: "", operator: ">", value: "" })}
                className="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground motion-safe:transition-colors hover:bg-muted hover:text-foreground"
              >
                <Plus className="h-3.5 w-3.5" />
                Add condition
              </button>
            );
          }

          return (
            <div className="space-y-2 rounded-lg border border-border/60 bg-muted/30 p-3">
              <div className="space-y-1.5">
                <Label className="text-xs text-foreground">Field name</Label>
                <Input
                  value={cond.field}
                  onChange={(e) => updateCondition({ field: e.target.value })}
                  placeholder="e.g. amount"
                  className="h-8 text-sm"
                />
              </div>
              <div className="flex items-end gap-2">
                <div className="flex-1 space-y-1.5">
                  <Label className="text-xs text-foreground">Operator</Label>
                  <Select
                    value={cond.operator}
                    onValueChange={(v) => updateCondition({ operator: v as BranchCondition["operator"] })}
                  >
                    <SelectTrigger className="h-8 text-sm">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="=">=  (equals)</SelectItem>
                      <SelectItem value="!=">!=  (not equals)</SelectItem>
                      <SelectItem value=">">&gt;  (greater than)</SelectItem>
                      <SelectItem value=">=">&gt;=  (greater or equal)</SelectItem>
                      <SelectItem value="<">&lt;  (less than)</SelectItem>
                      <SelectItem value="<=">&lt;=  (less or equal)</SelectItem>
                      <SelectItem value="contains">contains</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex-1 space-y-1.5">
                  <Label className="text-xs text-foreground">Value</Label>
                  <Input
                    value={String(cond.value)}
                    onChange={(e) => updateCondition({ value: e.target.value })}
                    placeholder="e.g. 500"
                    className="h-8 text-sm"
                  />
                </div>
                <button
                  type="button"
                  onClick={() => updateCondition(null)}
                  className="flex h-8 w-8 shrink-0 items-center justify-center rounded text-muted-foreground motion-safe:transition-colors hover:bg-destructive/10 hover:text-destructive"
                  aria-label="Remove condition"
                >
                  <X className="h-3.5 w-3.5" />
                </button>
              </div>
              {cond.field && cond.value !== "" && (
                <p className="rounded bg-muted/50 px-2 py-1.5 text-xs text-muted-foreground">
                  Skip if <strong className="text-foreground">{cond.field}</strong>{" "}
                  {cond.operator}{" "}
                  <strong className="text-foreground">{String(cond.value)}</strong> is false.
                </p>
              )}
            </div>
          );
        })()}
      </section>

      <div className="border-t border-border/60" />

      {/* ── Danger zone ── */}
      <section className="pt-4 pb-1">
        <button
          type="button"
          onClick={() => setShowDeleteModal(true)}
          className="inline-flex w-full items-center justify-center gap-2 rounded-md border border-destructive/30 px-3 py-2 text-sm font-medium text-destructive motion-safe:transition-colors hover:bg-destructive/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-destructive"
        >
          <Trash2 className="h-4 w-4" />
          Delete Step
        </button>
      </section>

      {/* Delete Confirmation Modal */}
      <Dialog open={showDeleteModal} onOpenChange={setShowDeleteModal}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                <AlertTriangle className="h-5 w-5 text-destructive" />
              </div>
              <DialogTitle>Delete Step</DialogTitle>
            </div>
          </DialogHeader>
          <DialogDescription className="text-left">
            Are you sure you want to delete{" "}
            <span className="font-semibold text-foreground">
              "{selectedNode.data?.step_name || "this step"}"
            </span>?{" "}
            This action cannot be undone.
          </DialogDescription>
          <DialogFooter className="gap-2 sm:gap-2">
            <Button type="button" variant="outline" onClick={() => setShowDeleteModal(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={() => {
                removeNode(selectedNode.id);
                setShowDeleteModal(false);
              }}
            >
              <Trash2 className="mr-2 h-4 w-4" />
              Delete Step
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

