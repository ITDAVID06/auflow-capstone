/**
 * Canonical Form Type Definitions
 *
 * Single source of truth for all form-related types used across
 * the form builder, form renderer, and student/staff dashboards.
 *
 * Property names match the backend DB columns exactly:
 *   field_name, label, data_type, is_required, options, options_meta,
 *   field_order, placeholder, help_text, use_slots, require_facility,
 *   date_mode, field_options
 */

// ---------------------------------------------------------------------------
// Field data types (matches backend StoreFormRequest validation)
// ---------------------------------------------------------------------------
export type FormFieldDataType =
  | 'text'
  | 'email'
  | 'phone'
  | 'textarea'
  | 'number'
  | 'checkbox'
  | 'radio'
  | 'select'
  | 'date'
  | 'file'
  | 'table'
  | 'section'
  | 'heading'
  | 'image';

// ---------------------------------------------------------------------------
// Structured option metadata (checkbox/radio per-option config)
// ---------------------------------------------------------------------------
export type OptionMeta = {
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
};

// ---------------------------------------------------------------------------
// Table column configuration
// ---------------------------------------------------------------------------
export interface TableColumn {
  id: string;
  label: string;
  type: 'text' | 'number' | 'date' | 'textarea';
  required: boolean;
}

// ---------------------------------------------------------------------------
// field_options JSON shape
// ---------------------------------------------------------------------------
export interface FieldOptions {
  // Auto-fill user name
  auto_fill_name?: boolean;

  // Table field
  table_columns?: TableColumn[];
  min_rows?: number;
  max_rows?: number;

  // Section field
  section_title?: string;
  section_description?: string;

  // Heading field
  heading_content?: string;
  heading_size?: 'small' | 'medium' | 'large';

  // Image field
  image_url?: string;
  image_alt?: string;
  image_alignment?: 'left' | 'center' | 'right';
  image_width?: 'small' | 'medium' | 'large' | 'full';
  image_path?: string;
}

// ---------------------------------------------------------------------------
// Conditional field logic
// ---------------------------------------------------------------------------
export interface FieldCondition {
  field_name: string;
  operator: 'equals' | 'not_equals' | 'contains' | 'not_empty' | 'is_empty';
  value?: string | number | boolean | null;
  action: 'show' | 'hide';
}

// ---------------------------------------------------------------------------
// Core FormField interface — matches tbl_formfield columns exactly
// ---------------------------------------------------------------------------
export interface FormField {
  id: number | string;
  form_id?: number;
  field_name: string;
  label: string;
  data_type: FormFieldDataType | string;
  is_required: boolean;

  // Simple options (legacy)
  options?: string[] | null;

  // Structured options with per-option config
  options_meta?: OptionMeta[] | null;

  field_order: number;
  date_created?: string;
  placeholder?: string;
  help_text?: string | null;

  // Date-related features
  use_slots?: boolean;
  require_facility?: boolean;
  date_mode?: 'single' | 'range';

  // Rich configuration
  field_options?: FieldOptions | null;

  // Conditional logic
  conditions?: FieldCondition[] | null;

  // Visibility on public verification page
  is_publicly_verifiable?: boolean;

  // Partially mask value for unauthenticated public viewers
  is_sensitive?: boolean;

  // Future-proof bag for dynamic settings
  meta?: Record<string, unknown>;
}

// ---------------------------------------------------------------------------
// Form builder page state
// ---------------------------------------------------------------------------
export interface FormBuilderState {
  id?: number;
  form_name: string;
  form_code?: string;
  description: string;
  version: number;
  status: 'Active' | 'Inactive';
  fields: FormField[];
  email_notifications: boolean;
  submission_limit: number | string;
  permissions?: number[];
  form_category_id?: number | null;
}

// ---------------------------------------------------------------------------
// Form payload (user-facing form rendering)
// ---------------------------------------------------------------------------
export interface FormPayload {
  id: number;
  form_name: string;
  description?: string;
  fields: FormField[];
  submission_limit_reached?: boolean;
  submission_availability?: {
    can_submit: boolean;
    code?: string | null;
    message?: string | null;
    has_permission?: boolean;
    has_active_workflow?: boolean;
    accepts_submissions?: boolean;
    submission_limit_reached?: boolean;
  };
}

// ---------------------------------------------------------------------------
// Form submission page props
// ---------------------------------------------------------------------------
export interface FormSubmissionPageProps {
  form: FormPayload;
  submitRouteName: string;
  backRouteName: string;
  userFullName?: string | null;
}

// ---------------------------------------------------------------------------
// Slot / date selection
// ---------------------------------------------------------------------------
export interface SelectedSlot {
  date: Date;
  start_time?: string;
  end_time?: string;
  facility_id?: string;
}

// ---------------------------------------------------------------------------
// File attachment reference
// ---------------------------------------------------------------------------
export interface ExistingAttachment {
  id: number | string;
  original_name: string;
  mime_type?: string;
  file_path?: string;
}

// ---------------------------------------------------------------------------
// Meta selection types (checkbox/radio value with qty/text)
// ---------------------------------------------------------------------------
export type SingleMetaSelection = {
  value: string;
  qty?: number;
  text?: string;
};

export type MultiMetaSelection = Array<{
  value: string;
  qty?: number;
  text?: string;
}>;

// ---------------------------------------------------------------------------
// Union type for all possible field values (for generic handlers)
// ---------------------------------------------------------------------------
export type FormFieldValue =
  | string
  | number
  | boolean
  | string[]
  | Date
  | File
  | File[]
  | null
  | undefined;

// ---------------------------------------------------------------------------
// Form data (key-value pairs of field names to values)
// ---------------------------------------------------------------------------
export type FormData = Record<string, FormFieldValue>;

// ---------------------------------------------------------------------------
// Validation error
// ---------------------------------------------------------------------------
export interface FormValidationError {
  field: string;
  message: string;
}

// ---------------------------------------------------------------------------
// Form slot for date/facility bookings
// ---------------------------------------------------------------------------
export interface FormSlot {
  date: string;
  start_time: string;
  end_time: string;
  facility_id?: number | string | null;
}

// ---------------------------------------------------------------------------
// Utility type guards
// ---------------------------------------------------------------------------
export function isValidFormFieldValue(value: unknown): value is FormFieldValue {
  return (
    typeof value === 'string' ||
    typeof value === 'number' ||
    typeof value === 'boolean' ||
    value === null ||
    value === undefined ||
    value instanceof Date ||
    value instanceof File ||
    (Array.isArray(value) && value.every(v => typeof v === 'string' || v instanceof File))
  );
}

export function isNonInputField(dataType: string): boolean {
  return ['section', 'heading', 'image'].includes(dataType);
}
