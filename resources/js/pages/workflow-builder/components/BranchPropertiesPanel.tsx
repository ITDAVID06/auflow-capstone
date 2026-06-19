import React, { useState } from "react";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Trash2, GitBranch, AlertTriangle } from "lucide-react";
import type { Node } from "reactflow";
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

export interface BranchPropertiesPanelProps {
  selectedNode: Node | null;
  updateNode: (id: string, updates: any) => void;
  removeNode: (id: string) => void;
  nodes: Node[];
}

export default function BranchPropertiesPanel({
  selectedNode,
  updateNode,
  removeNode,
  nodes,
}: BranchPropertiesPanelProps) {
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  
  if (!selectedNode || selectedNode.type !== "branchContainer") return null;

  const branchGroup = Number((selectedNode.data as any)?.group ?? 1);
  const branchLabel = String((selectedNode.data as any)?.label ?? "Branch");

  // Find child nodes
  const childNodes = nodes.filter(n => n.parentNode === selectedNode.id);
  const childCount = childNodes.length;

  const handleDelete = () => {
    setShowDeleteModal(true);
  };

  return (
    <div className="flex flex-col gap-4">
      {/* Header band */}
      <div className="flex items-center justify-between px-3 py-2 rounded-md dark:border dark:border-slate-700 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
        <div className="flex items-center gap-2 text-[11px] font-semibold tracking-wide">
          <GitBranch className="h-3.5 w-3.5" />
          <span className="uppercase">BRANCH CONTAINER</span>
        </div>
        <span className="text-[10px] font-medium opacity-80">
          Group: {branchGroup}
        </span>
      </div>

      {/* Label */}
      <div className="space-y-1.5">
        <Label htmlFor="branch-label">Branch Label</Label>
        <Input
          id="branch-label"
          value={branchLabel}
          onChange={(e) => {
            updateNode(selectedNode.id, { label: e.target.value });
          }}
          placeholder="e.g., Parallel Approvals"
          maxLength={50}
        />
      </div>

      {/* Group Number */}
      <div className="space-y-1.5">
        <Label htmlFor="branch-group">Group Number</Label>
        <Input
          id="branch-group"
          type="number"
          min={1}
          value={branchGroup}
          onChange={(e) => {
            const newGroup = parseInt(e.target.value) || 1;
            updateNode(selectedNode.id, { group: newGroup });
          }}
          placeholder="1"
        />
        <p className="text-xs text-muted-foreground dark:text-slate-500">
          Actions inside this branch will inherit this group number.
        </p>
      </div>

      {/* Child Count Info */}
      {childCount > 0 && (
        <div className="rounded-md border dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2">
          <p className="text-xs text-slate-600 dark:text-slate-400">
            Contains <strong>{childCount}</strong> action node{childCount !== 1 ? 's' : ''}
          </p>
        </div>
      )}

      {/* Sticky danger action */}
      <div className="sticky bottom-0 pt-1 bg-gradient-to-t from-white dark:from-slate-900">
        <button
          type="button"
          onClick={handleDelete}
          className="w-full inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
        >
          <Trash2 className="h-4 w-4" />
          Delete Branch {childCount > 0 && `(and ${childCount} child${childCount !== 1 ? 'ren' : ''})`}
        </button>
      </div>

      {/* Delete Confirmation Modal */}
      <Dialog open={showDeleteModal} onOpenChange={setShowDeleteModal}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                <AlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400" />
              </div>
              <div className="flex-1">
                <DialogTitle>Delete Branch Container</DialogTitle>
              </div>
            </div>
          </DialogHeader>
          <DialogDescription className="text-left">
            {childCount > 0 ? (
              <>
                Are you sure you want to delete <span className="font-semibold text-slate-900 dark:text-slate-100">"{branchLabel}"</span> and its <span className="font-semibold text-slate-900 dark:text-slate-100">{childCount} child node{childCount !== 1 ? 's' : ''}</span>? 
                This action cannot be undone and will permanently remove the branch container and all nodes inside it from the workflow.
              </>
            ) : (
              <>
                Are you sure you want to delete <span className="font-semibold text-slate-900 dark:text-slate-100">"{branchLabel}"</span>? 
                This action cannot be undone and will permanently remove this branch container from the workflow.
              </>
            )}
          </DialogDescription>
          <DialogFooter className="gap-2 sm:gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setShowDeleteModal(false)}
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={() => {
                removeNode(selectedNode.id);
                setShowDeleteModal(false);
              }}
              className="bg-red-600 hover:bg-red-700"
            >
              <Trash2 className="h-4 w-4 mr-2" />
              Delete Branch{childCount > 0 && ` & ${childCount} Child${childCount !== 1 ? 'ren' : ''}`}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
