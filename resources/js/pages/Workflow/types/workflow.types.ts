import type { Edge, Node } from "reactflow";

export type WorkflowStatus = "Active" | "Draft" | "Archived";

export interface WorkflowStep {
  id: number;
  step_name: string;
  step_order: number;
  assigned_user?: {
    account_id: number;
    profile?: { first_name: string; last_name: string };
  } | null;
}

export interface WorkflowSettings {
  nodes: Node[];
  edges: Edge[];
  containers?: Array<{
    id: string;
    label?: string;
    group?: number;
    rect?: { x: number; y: number; w: number; h: number };
    children?: string[];
  }>;
  virtual_edges?: Edge[];
}

export interface Workflow {
  id: number;
  workflow_name: string;
  workflow_type: string;
  description?: string | null;
  status: WorkflowStatus;
  created_by: number;
  created_at: string;
  updated_at: string;
  steps_count: number;
  form?: { form_name: string } | null;
  steps?: WorkflowStep[];
  workflow_settings?: WorkflowSettings;
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  next_page_url: string | null;
  prev_page_url: string | null;
  total: number;
}

export interface WorkflowFilters {
  search?: string;
  status?: "all" | "active" | "draft" | "archived";
}
