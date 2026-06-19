export type Field = {
  name?: string | null;
  label: string;
  value: unknown;
  type: string;
  isFile?: boolean;
  mime_type?: string;
  field_options?: Record<string, unknown> | null;
};

export type SnapshotProp = {
  public_id: string;
  short_code: string;
  status: "Approved" | "Rejected" | string;
  step: string;
  approved_by: string;
  approved_at: string | null;
  comment?: string | null;
  form: { id?: number | null; code?: string | null; name: string; version?: number | null };
  submission: { id?: number | null; created_at?: string | null };
  fields: Field[];
  is_workflow_complete: boolean;
};

export type ApprovalRecord = {
  step: string;
  status: string;
  actor: string;
  acted_at: string | null;
  comment?: string | null;
  action_hash?: string | null;
};

export type CommentAttachment = {
  id: number;
  filename: string;
  path: string;
  mime_type?: string;
  size_bytes?: number;
  uploaded_at: string;
};

export type ApprovalItem = {
  id?: number;
  step: string;
  status: string;
  actor: string;
  acted_at?: string | null;
  created_at?: string | null; // keep optional if you don't have it
  comment?: string | null;
  attachments?: CommentAttachment[];
  action_hash?: string | null;
};
