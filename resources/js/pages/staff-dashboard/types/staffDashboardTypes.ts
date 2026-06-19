import { PageProps } from "@/types/inertia"; // Correct path

export interface RequestForm {
  id: number;
  form_name: string;
  description?: string;
}

export interface Submission {
  progress_id: number;
  form_name: string;
  workflow_name: string;
  step_order: number;
  submission_data: Record<string, unknown>;
}

export interface StaffDashboardRequest {
  progress_id: number;
  submission_id: number;
  form_code: string;
  form_name: string;
  status: string;
  progress?: number;
  submitted_at: string;
  submitter: string;
  version?: number;
  is_latest?: boolean;
  revision_of?: number | null;
}

export interface StaffDashboardProps extends PageProps {
  metrics: {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
  };
  requests: StaffDashboardRequest[];
  pendingContext?: {
    assigned_count: number;
    pending_pool_count: number;
    has_unassigned_pending: boolean;
  };
  forms: RequestForm[];
}
