export type FormStatus = "Active" | "Inactive" | "Archived";

export interface Form {
  id: number;
  form_name: string;
  form_code: string;
  form_family_code?: string | null;
  description: string | null;
  status: FormStatus;
  version: number | string;
  created_by: number;
  created_at: string;
  updated_at: string;
  is_locked: boolean;
  permission_id: string | null;
  permission_label?: string;
  category?: string | null;
  category_name?: string | null;
  family_revision_count?: number;
}

export interface PaginatedForms {
  data: Form[];
  current_page: number;
  last_page: number;
  per_page?: number;
  next_page_url: string | null;
  prev_page_url: string | null;
  total: number;
}

export interface FormFilters {
  search?: string;
  status?: "Active" | "Inactive" | "Archived" | "All";
}

export interface PermissionOption {
  id: number;
  label: string;
}

export interface FormFieldItem {
  id: number;
  label: string;
  data_type: string;
  is_required: boolean;
  help_text?: string;
  options?: string[] | null;
  options_meta?: Array<{
    label: string;
    value?: string | null;
    requires_qty?: boolean;
    qty_label?: string | null;
    min_qty?: number | null;
    max_qty?: number | null;
    step?: number | null;
    default_qty?: number | null;
    unit?: string | null;
    requires_text?: boolean;
    text_label?: string | null;
  }> | null;
}
