import { Node, Edge } from "reactflow";

export type WorkflowStatus = "draft" | "active" | "archive";
export type WorkflowType = "Sequential" | "Parallel";
export type StepType =
  | "approval"
  | "task"
  | "notification"
  | "branching"
  | "integration"
  | "branchContainer"; // authoring-only

export interface WorkflowStepSettings {
  duration_days?: number;
  reminder_interval?: string;
  max_duration_hours?: number | null;
  [key: string]: any;
}

export interface ApproverCondition {
  account_id: number | null;
  user_name: string;
  condition: 'primary' | 'or';
  order: number;
}

export interface BranchCondition {
  field: string;
  operator: '=' | '!=' | '>' | '>=' | '<' | '<=' | 'contains';
  value: string | number;
}

export interface WorkflowStepNode {
  id: string;
  type: string;
  position: { x: number; y: number };
  data: {
    label: string;
    type: StepType;
    duration_days: number;
    assigned_user?: number;
    assigned_user_name?: string;
    assigned_account_id?: number | null; // Backwards compatibility
    step_group?: number;
    watch_fields?: string[];
    branch_condition?: BranchCondition | null;
    approvers?: ApproverCondition[]; // New: Multiple approvers support
    reminder_mode?: string; // "default", "custom", or "none"
    reminder_interval?: string; // Generated interval string: "30min", "2hours", "1day"
    reminder_value?: number | null; // Custom interval value: e.g., 30
    reminder_unit?: string; // Custom interval unit: "minutes", "hours", "days"
    max_duration_hours?: number | null; // Response deadline in hours
  };
  style?: {
    background: string;
    border: string;
    [key: string]: any;
  };
}

export interface WorkflowEdge {
  id: string;
  source: string;
  target: string;
  type?: string;
  animated?: boolean;
  style?: Record<string, any>;
}

export interface WorkflowStep {
  step_name: string;
  step_order: number;
  step_type: StepType;
  assigned_account_id?: number | null;
  step_group?: number;
  step_settings: WorkflowStepSettings;
}

export interface WorkflowBuilderState {
  id?: number;
  workflow_name: string;
  workflow_type: WorkflowType;
  description?: string;
  form_id: number | null;
  status: WorkflowStatus;
  steps: WorkflowStep[];
  workflow_settings: {
    nodes: WorkflowStepNode[];
    edges: WorkflowEdge[];
  };
}

// --- API/DTO helpers ---
export type FormLite = { id: number; form_name: string };
export type UserLite = { id: number | string; name: string };
export type FormFieldLite = { id: number; field_name: string; label: string; data_type: string };

export type BranchContainerAuthoring = {
  id: string;
  label?: string;
  group: number;
  rect: { x: number; y: number; w: number; h: number };
  children: string[];
};

export type ExportedWorkflow = {
  id: number;
  workflow_name: string;
  workflow_type: WorkflowType;
  description?: string | null;
  form_id: number;
  status: WorkflowStatus;
  workflow_settings: {
    nodes: WorkflowStepNode[];
    edges: WorkflowEdge[];
    containers?: BranchContainerAuthoring[];
    virtual_edges?: WorkflowEdge[];
  };
};

