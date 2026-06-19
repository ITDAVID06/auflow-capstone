// Type definitions for submission review components

export interface WorkflowAttachment {
  id: number;
  original_name: string;
  mime_type?: string;
  size_bytes?: number;
  uploaded_by_id: number;
  uploaded_by_name: string;
  uploaded_at?: string;
  download_url: string;
  preview_url?: string;
}

export interface WorkflowStep {
  step: string;
  status: string;
  status_key?: "pending" | "in_progress" | "approved" | "rejected" | "skipped";
  status_label?: string;
  assignee?: string;
  actor: string | null;
  acted_at: string | null;
  step_index?: number;
  total_steps?: number;
  comments?: string;
  duration_human?: string;
  duration_seconds?: number;
  attachments?: WorkflowAttachment[];
}

export interface Slot {
  date: string;
  start_time: string;
  end_time: string;
  facility_id?: string;
}

export interface DateRange {
  start_date: string;
  end_date: string;
  start?: string;
  end?: string;
}

export interface Attachment {
  id: number;
  file_path: string;
  original_name: string;
  mime_type: string;
  uploaded_by: number;
  created_at: string;
}

export interface SubmissionField {
  field_name?: string;
  label: string;
  value: unknown;
  type?: string;
  field_options?: Record<string, unknown> | null;
}

export interface SubmissionFormField {
  id: number;
  field_name: string;
  label: string;
  data_type: string;
  is_required: boolean;
  options?: unknown;
  options_meta?: unknown;
  field_order?: number;
  help_text?: string | null;
  use_slots?: boolean;
  require_facility?: boolean;
  date_mode?: string;
  field_options?: Record<string, unknown> | null;
}

export interface RevisionHistory {
  id: number;
  progress_id?: number;
  version?: number;
  is_latest?: boolean;
  created_at: string;
  updated_at: string;
  status: string;
}

export interface SubmissionData {
  progress_id: number;
  id: number;
  form_id: number;
  form_code: string;
  form_name: string;
  created_at: string;
  updated_at: string;
  submitter: string;
  fields: SubmissionField[];
  workflow: WorkflowStep[] | null;
  can_review: boolean;
  slots?: Slot[];
  date_ranges?: DateRange[];
  attachments?: Attachment[];
  is_latest?: boolean;
  history?: RevisionHistory[];
  workflow_duration?: { total_seconds: number; total_human: string | null };
  range_field_label?: string;
  snapshot?: SnapshotInfo;
  form_fields?: SubmissionFormField[];
}

export interface SnapshotInfo {
  exists: boolean;
  public_id?: string;
  short_code?: string;
  status?: string;
  is_workflow_complete?: boolean;
  approved_at?: string;
  url?: string;
}
