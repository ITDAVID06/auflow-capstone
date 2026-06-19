import React, { useEffect, useState, useCallback, useRef, useMemo } from "react";
import { Head, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import { api } from "./api/workflowBuilderApi";
import type { Node, Edge } from "reactflow";
import "reactflow/dist/style.css";
import { Loader2, GitMerge } from "lucide-react";

import WorkflowHeader from "./components/WorkflowHeader";
import NodePalette from "./components/NodePalette";
import PropertyInspector from "./components/PropertyInspector";
import CanvasArea from "./components/CanvasArea";
import BranchContainerNode from "./components/BranchContainerNode";
import StepNode from "./components/StepNode";
import AddPlaceholderNode from "./components/AddPlaceholderNode";
import EndNode from "./components/EndNode";
import StartNode from "./components/StartNode";
import { compileForSave } from "./utils/branchLayout";
import { ReactFlowProvider } from "reactflow";
import { uid } from "./utils/uid";
import { computeLayout, deriveStepOrder, STEP_W } from "./utils/guidedLayout";
import { calculateStepGroups } from "./utils/guidedLayout";
import type { FormFieldLite, WorkflowStep } from "./types/workflowBuilderTypes";

// ---------- Page Props Types ----------
interface FormOption {
  id: number;
  form_name: string;
}

interface UserOption {
  id: number | string;
  name: string;
}

interface PageProps {
  workflowId?: number;
  initialFormId?: number | null;
  workflowBasic?: {
    id: number;
    workflow_name: string;
    workflow_type: string;
    description: string | null;
    form_id: number | null;
    status: string;
  };
  forms: FormOption[];
  users: UserOption[];
  canvasDataUrl?: string;
}

// ---------- Node type registry ----------
const NODE_TYPES = {
  start: StartNode,
  step: StepNode,
  branchContainer: BranchContainerNode,
  addPlaceholder: AddPlaceholderNode,
  endNode: EndNode,
};

/** Available node types for the palette. branchContainer is only shown in Parallel mode. */
const PALETTE_NODE_KEYS = ["approval", "branchContainer"] as const;

/** Default data for a new step node. */
const DEFAULT_STEP_DATA = {
  label: "New Step",
  step_name: "New Step",
  type: "approval" as const,
  assigned_account_id: null,
  step_group: null as number | null,
  description: null,
  duration_days: 0,
  watch_fields: [] as string[],
  reminder_interval: "default",
  max_duration_hours: null,
};

// ---------- Initial start node ----------
const initialStartNode: Node = {
  id: "start",
  type: "start",
  position: { x: 100, y: 100 },
  data: {
    label: "New Workflow",
    type: "form_submitted",
    assigned_account_id: null,
    step_group: 1,
    description: null,
    duration_days: null,
    conditions: null,
    notifications: null,
    criteria: null,
    message: null,
    watch_fields: [],
  },
  style: { width: 240, height: 64, zIndex: 1 },
};

// ─────────────────────────────────────────────────────────────────────────────
export default function WorkflowBuilderPage({
  workflowId,
  initialFormId,
  workflowBasic,
  forms = [],
  users = [],
  canvasDataUrl,
}: PageProps) {
  // ── Async loading state ─────────────────────────────────────────────────
  const [isLoadingCanvas, setIsLoadingCanvas] = useState(!!workflowId);

  // ── Core authoring state ────────────────────────────────────────────────
  // Raw node data (without computed positions — positions come from computeLayout)
  const [rawNodes, setRawNodes] = useState<Node[]>([initialStartNode]);
  // Ordered IDs of mainline steps (source of truth for sequence)
  const [stepOrder, setStepOrder] = useState<string[]>([]);
  // Track selected node by ID (derived from rawNodes to stay in sync)
  const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
  const selectedNode = useMemo(() => {
    if (!selectedNodeId) return null;
    return rawNodes.find((n) => n.id === selectedNodeId) ?? null;
  }, [selectedNodeId, rawNodes]);

  const [formState, setFormState] = useState<{
    workflow_name: string;
    workflow_type: "Sequential" | "Parallel";
    description: string;
    form_id: string | number;
    status: "draft" | "active" | "archive";
  }>({
    workflow_name: workflowBasic?.workflow_name || "New Workflow",
    workflow_type: (workflowBasic?.workflow_type as "Sequential" | "Parallel") || "Sequential",
    description: workflowBasic?.description || "",
    form_id: workflowBasic?.form_id ?? initialFormId ?? "",
    status: (workflowBasic?.status as "draft" | "active" | "archive") || "draft",
  });

  const [formFields, setFormFields] = useState<FormFieldLite[]>([]);
  const [saveStatus, setSaveStatus] = useState<"saved" | "saving" | "unsaved">("saved");
  const autoSaveTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  const normalizedStepOrder = useMemo(() => {
    const topLevelStepIds = rawNodes
      .filter(
        (node) =>
          node.id !== "start" &&
          !node.parentNode &&
          node.type !== "addPlaceholder" &&
          node.type !== "endNode"
      )
      .map((node) => node.id);

    const validStepIds = new Set(topLevelStepIds);
    const filteredOrder = stepOrder.filter((id) => validStepIds.has(id));
    const missingIds = topLevelStepIds.filter((id) => !filteredOrder.includes(id));

    return [...filteredOrder, ...missingIds];
  }, [rawNodes, stepOrder]);

  // ── Derived layout (computed from stepOrder + rawNodes) ─────────────────
  const layout = useMemo(
    () => computeLayout(normalizedStepOrder, rawNodes, formState.workflow_type),
    [normalizedStepOrder, rawNodes, formState.workflow_type]
  );

  // ── Fetch canvas data asynchronously ────────────────────────────────────
  useEffect(() => {
    if (!workflowId || !canvasDataUrl) {
      setIsLoadingCanvas(false);
      return;
    }

    const fetchCanvasData = async () => {
      try {
        setIsLoadingCanvas(true);
        const response = await fetch(canvasDataUrl, {
          headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        });
        if (!response.ok) throw new Error(`Failed to load canvas data: ${response.statusText}`);
        const data = await response.json();

        const workflowData = data?.workflow ?? {};

        // Prefer current storage format (workflow_settings), fallback to legacy canvas_data.
        let loadedCanvas: any = workflowData.workflow_settings ?? null;

        if (!loadedCanvas && workflowData.canvas_data) {
          try {
            loadedCanvas =
              typeof workflowData.canvas_data === "string"
                ? JSON.parse(workflowData.canvas_data)
                : workflowData.canvas_data;
          } catch (err) {
            console.warn("Failed to parse legacy canvas_data JSON:", err);
          }
        }

        if (loadedCanvas?.nodes && Array.isArray(loadedCanvas.nodes)) {
          // Filter out UI-only nodes (placeholders, end markers)
          const realNodes = loadedCanvas.nodes.filter(
            (n: Node) =>
              typeof n?.id === "string" &&
              !n.id.startsWith("__plus__") &&
              !n.id.startsWith("__end__") &&
              n.type !== "addPlaceholder" &&
              n.type !== "endNode"
          );

          setRawNodes(realNodes.length > 0 ? realNodes : [initialStartNode]);

          // Derive stepOrder from persisted data or saved authoring order
          const savedOrder =
            loadedCanvas.authoring?.stepOrder ??
            loadedCanvas.stepOrder;

          if (Array.isArray(savedOrder) && savedOrder.length > 0) {
            const existingIds = new Set(realNodes.map((n: Node) => n.id));
            const normalizedOrder = savedOrder
              .map((id: unknown) => String(id))
              .filter((id: string) => existingIds.has(id));
            setStepOrder(normalizedOrder.length > 0 ? normalizedOrder : deriveStepOrder(realNodes));
          } else {
            setStepOrder(deriveStepOrder(realNodes));
          }
        }

        // Canvas loaded — no toast needed.
      } catch (error) {
        console.error("Error loading canvas data:", error);
        toast.error("Failed to load workflow canvas data");
      } finally {
        setIsLoadingCanvas(false);
      }
    };

    fetchCanvasData();
  }, [workflowId, canvasDataUrl]);

  // ── Fetch form fields when form selected ────────────────────────────────
  useEffect(() => {
    if (!formState.form_id) {
      setFormFields([]);
      return;
    }
    fetch(`/workflow-config/forms/${formState.form_id}/fields`)
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((data) => {
        const fields = Array.isArray(data)
          ? data
          : Array.isArray(data?.fields)
          ? data.fields
          : [];
        setFormFields(fields);
      })
      .catch(() => {
        setFormFields([]);
        toast.error("Could not load form fields for conditional logic.");
      });
  }, [formState.form_id]);

  // ── Keep Start node title in sync with workflow name ────────────────────
  useEffect(() => {
    const workflowTitle = formState.workflow_name?.trim() || "New Workflow";
    setRawNodes((prev) =>
      prev.map((node) => {
        if (node.id !== "start") {
          return node;
        }
        return {
          ...node,
          data: {
            ...(node.data || {}),
            label: workflowTitle,
          },
        };
      })
    );
  }, [formState.workflow_name]);

  // ── Add node ────────────────────────────────────────────────────────────
  const addNode = useCallback(
    (type: string, insertAfterId?: string | null) => {
      const id = uid();

      let newNode: Node;
      if (type === "branchContainer") {
        newNode = {
          id,
          type: "branchContainer",
          position: { x: 0, y: 0 },
          data: { label: "Branch", step_group: null },
          style: { width: 360, height: 280, zIndex: 0 },
        };
      } else {
        newNode = {
          id,
          type: "step",
          position: { x: 0, y: 0 },
          data: {
            ...DEFAULT_STEP_DATA,
            step_group: formState.workflow_type === "Sequential" ? null : 1,
          },
          style: { width: STEP_W, height: 120, zIndex: 10 },
        };
      }

      // Add node to raw store
      setRawNodes((prev) => [...prev, newNode]);

      // Insert into stepOrder at the right position
      setStepOrder((prev) => {
        if (insertAfterId === null || insertAfterId === undefined) {
          // Append to end
          return [...prev, id];
        }
        if (insertAfterId === "start") {
          return [id, ...prev];
        }
        const idx = prev.indexOf(insertAfterId);
        if (idx === -1) return [...prev, id];
        const next = [...prev];
        next.splice(idx + 1, 0, id);
        return next;
      });
    },
    [formState.workflow_type]
  );

  // ── Remove node ─────────────────────────────────────────────────────────
  const removeNode = useCallback(
    (id: string) => {
      setRawNodes((prev) => {
        const target = prev.find((n) => n.id === id);
        let toRemove = [id];
        // If branch container, remove children too
        if (target?.type === "branchContainer") {
          toRemove = [id, ...prev.filter((n) => n.parentNode === id).map((n) => n.id)];
        }
        return prev.filter((n) => !toRemove.includes(n.id));
      });

      setStepOrder((prev) => prev.filter((sid) => sid !== id));
      setSelectedNodeId(null);
    },
    []
  );

  // ── Update node data ───────────────────────────────────────────────────
  const updateNode = useCallback(
    (id: string, newData: any) => {
      setRawNodes((prev) =>
        prev.map((n) => {
          if (n.id !== id) return n;
          const updated = { ...n, data: { ...n.data, ...newData } };
          if (newData.step_name !== undefined) updated.data.label = newData.step_name;
          return updated;
        })
      );
    },
    []
  );

  // ── Click plus button → add default step ───────────────────────────────
  const handlePlaceholderAdd = useCallback(
    (insertAfterId: string) => {
      addNode("approval", insertAfterId);
    },
    [addNode]
  );

  // ── Add child node inside a branch container ───────────────────────────
  const addChildNode = useCallback(
    (containerId: string) => {
      const id = uid();
      const childNode: Node = {
        id,
        type: "step",
        position: { x: 0, y: 0 },
        parentNode: containerId,
        extent: "parent" as const,
        data: { ...DEFAULT_STEP_DATA },
        style: { width: 200, height: 72, zIndex: 10 },
      };
      setRawNodes((prev) => [...prev, childNode]);
    },
    []
  );

  // ── Keyboard shortcuts ─────────────────────────────────────────────────
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.target as HTMLElement)?.tagName === "INPUT" || (e.target as HTMLElement)?.tagName === "TEXTAREA") return;

      // Delete selected node
      if ((e.key === "Delete" || e.key === "Backspace") && selectedNode) {
        e.preventDefault();
        if (selectedNode.type !== "start" && selectedNode.id !== "start") {
          removeNode(selectedNode.id);
          toast.success("Node deleted");
        }
      }

      // Duplicate (Cmd/Ctrl+D)
      if ((e.metaKey || e.ctrlKey) && e.key === "d" && selectedNode) {
        e.preventDefault();
        if (selectedNode.type !== "start" && selectedNode.id !== "start") {
          const newId = uid();
          const clone: Node = {
            ...selectedNode,
            id: newId,
            position: { x: 0, y: 0 },
            selected: false,
            data: { ...selectedNode.data, step_name: `${(selectedNode.data as any)?.step_name || "Step"} (copy)` },
          };
          setRawNodes((prev) => [...prev, clone]);
          // Insert after the selected node in stepOrder
          setStepOrder((prev) => {
            const idx = prev.indexOf(selectedNode.id);
            if (idx === -1) return [...prev, newId];
            const next = [...prev];
            next.splice(idx + 1, 0, newId);
            return next;
          });
          toast.success("Node duplicated");
        }
      }

      // Escape → deselect
      if (e.key === "Escape") setSelectedNodeId(null);
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [selectedNode, removeNode]);

  // ── Save ────────────────────────────────────────────────────────────────
  const handleSave = async (silent = false) => {
    if (!formState.workflow_name.trim()) {
      if (!silent) toast.error("Workflow name is required");
      return;
    }

    try {
      setSaveStatus("saving");

      // Use the positioned nodes from layout for compileForSave
      const nodesForSave = calculateStepGroups(layout.nodes, layout.edges, formState.workflow_type);
      const payload = compileForSave(nodesForSave, layout.edges, formState);

      // Persist stepOrder in workflow_settings for reliable reload
      (payload as any).workflow_settings.stepOrder = normalizedStepOrder;

      if (!payload.steps || payload.steps.length === 0) {
        if (!silent) toast.error("Please add at least one workflow step");
        setSaveStatus("unsaved");
        return;
      }

      let response;
      if (workflowId) {
        response = await api.updateWorkflow(workflowId, payload);
      } else {
        response = await api.createWorkflow(payload);
      }

      if (response.status >= 200 && response.status < 300) {
        setSaveStatus("saved");
        if (!silent) toast.success(workflowId ? "Workflow updated!" : "Workflow created!");
        if (!silent) router.visit(route("admin.workflows.index"));
        setTimeout(() => setSaveStatus("unsaved"), 2000);
      }
    } catch (error: unknown) {
      const axiosError = error as {
        message?: string;
        response?: {
          data?: {
            message?: string;
            errors?: Record<string, string[]>;
          };
        };
      };
      const validationMessage = axiosError.response?.data?.errors
        ? Object.values(axiosError.response.data.errors)[0]?.[0]
        : undefined;
      const message = validationMessage || axiosError.response?.data?.message || axiosError.message || "Failed to save workflow";
      setSaveStatus("unsaved");
      if (!silent) toast.error(message);
    }
  };

  // Keep a ref to the latest handleSave so the auto-save timer always calls
  // the current version without stale closure values.
  const handleSaveRef = useRef(handleSave);
  useEffect(() => { handleSaveRef.current = handleSave; });

  // ── Auto-save (for existing workflows) ──────────────────────────────────
  useEffect(() => {
    if (!workflowId || normalizedStepOrder.length === 0 || isLoadingCanvas) return;
    setSaveStatus("unsaved");

    if (autoSaveTimeoutRef.current) clearTimeout(autoSaveTimeoutRef.current);
    autoSaveTimeoutRef.current = setTimeout(() => handleSaveRef.current(true), 3000);

    return () => {
      if (autoSaveTimeoutRef.current) clearTimeout(autoSaveTimeoutRef.current);
    };
  }, [rawNodes, normalizedStepOrder, workflowId, isLoadingCanvas]);

  // ── Render ──────────────────────────────────────────────────────────────
  return (
    <AppLayout
      title="Workflow Builder"
      subtitle="Design and manage your approval flow"
    >
      <Head title="Workflow Builder" />

      <div className="flex flex-col h-[calc(100vh-4rem)]">
        {/* Top Navigation Bar */}
        <WorkflowHeader
          formState={formState}
          setFormState={setFormState}
          forms={forms}
          nodes={rawNodes}
          workflowId={workflowId}
          onSave={() => handleSave(false)}
          saveStatus={saveStatus}
          isLoadingCanvas={isLoadingCanvas}
        />

        {/* Main Content Area */}
        <div className="relative flex flex-1 overflow-hidden bg-muted/20">
          {/* Loading Overlay */}
          {isLoadingCanvas && (
            <div className="absolute inset-0 z-50 flex items-center justify-center bg-background/80 backdrop-blur-sm">
              <div className="flex flex-col items-center gap-4">
                <Loader2 className="h-12 w-12 animate-spin text-primary" />
                <div className="text-center">
                  <h3 className="text-lg font-semibold">Loading Canvas Data</h3>
                  <p className="text-sm text-muted-foreground">
                    Please wait while we load the workflow design...
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Canvas Area */}
          <div className="relative flex-1 bg-background">
            <ReactFlowProvider>
              <CanvasArea
                nodes={layout.nodes}
                edges={layout.edges}
                placeholders={layout.placeholders}
                endNode={layout.endNode}
                setSelectedNode={(n) => setSelectedNodeId(n?.id ?? null)}
                nodeTypes={NODE_TYPES}
                addNode={addNode}
                onPlaceholderAdd={handlePlaceholderAdd}
                workflowType={formState.workflow_type}
                addChildNode={addChildNode}
              />
            </ReactFlowProvider>

            {/* Floating Node Palette */}
            <div className="pointer-events-none absolute left-3 top-3 z-20 w-[240px]">
              <div className="pointer-events-auto">
                <NodePalette paletteKeys={PALETTE_NODE_KEYS} addNode={addNode} formState={formState} />
              </div>
            </div>

            {/* Empty canvas hint */}
            {!isLoadingCanvas && rawNodes.length <= 1 && (
              <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center">
                <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border/60 bg-background px-10 py-8 text-center">
                  <div className="rounded-full bg-muted/60 p-3">
                    <GitMerge className="h-6 w-6 text-muted-foreground" aria-hidden="true" />
                  </div>
                  <p className="text-sm font-semibold text-foreground/80">Your canvas is empty</p>
                  <p className="text-xs text-muted-foreground max-w-[220px] leading-relaxed">
                    Click an item in the Node Palette on the left, or drag it onto the canvas to add a step.
                  </p>
                </div>
              </div>
            )}

            {/* Floating Properties Card — always visible; shows empty-state when nothing selected */}
            <div className="pointer-events-none absolute right-3 top-3 bottom-3 z-20 w-[320px]">
              <div className="pointer-events-auto h-full rounded-xl border border-border/70 bg-card/95 shadow-lg backdrop-blur-sm overflow-hidden">
                <PropertyInspector
                  key={selectedNode?.id ?? "__none__"}
                  selectedNode={selectedNode}
                  updateNode={updateNode}
                  removeNode={removeNode}
                  users={users}
                  workflowType={formState.workflow_type}
                  formFields={formFields}
                  formState={formState}
                  setFormState={setFormState}
                  nodes={rawNodes}
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
