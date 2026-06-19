import type { FormFieldDataType, FieldOptions } from '@/types/form';

export const FIELD_TYPE_DEFINITIONS: Array<{
  type: FormFieldDataType;
  label: string;
  hint: string;
}> = [
  { type: 'text', label: 'Text Input', hint: 'Single-line free text.' },
  { type: 'email', label: 'Email', hint: 'Validates email format.' },
  { type: 'phone', label: 'Phone', hint: 'Phone number input.' },
  { type: 'date', label: 'Date', hint: 'Date (supports range/slots).' },
  { type: 'textarea', label: 'Text Area', hint: 'Multi-line text.' },
  { type: 'checkbox', label: 'Checkbox', hint: 'Multiple selection (list).' },
  { type: 'radio', label: 'Radio Button', hint: 'Single selection (list).' },
  { type: 'select', label: 'Dropdown', hint: 'Single selection (dropdown).' },
  { type: 'file', label: 'File Upload', hint: 'Upload a file.' },
  { type: 'number', label: 'Number', hint: 'Numeric input.' },
  { type: 'table', label: 'Table', hint: 'Multi-row structured data.' },
  { type: 'section', label: 'Section Break', hint: 'Visual divider with title.' },
  { type: 'heading', label: 'Title & Description', hint: 'Add headings or instructions.' },
  { type: 'image', label: 'Image', hint: 'Embed an image.' },
];

export const FIELD_TYPE_LABELS: Record<FormFieldDataType, string> = FIELD_TYPE_DEFINITIONS.reduce(
  (carry, definition) => {
    carry[definition.type] = definition.label;

    return carry;
  },
  {} as Record<FormFieldDataType, string>
);

export const NON_INPUT_FIELD_TYPES: FormFieldDataType[] = ['section', 'heading', 'image'];
export const CHOICE_FIELD_TYPES: FormFieldDataType[] = ['select', 'radio', 'checkbox'];
export const ADVANCED_META_FIELD_TYPES: FormFieldDataType[] = ['checkbox', 'radio'];

export function isNonInputFieldType(type: string): boolean {
  return NON_INPUT_FIELD_TYPES.includes(type as FormFieldDataType);
}

export function isChoiceFieldType(type: string): boolean {
  return CHOICE_FIELD_TYPES.includes(type as FormFieldDataType);
}

export function supportsAdvancedMeta(type: string): boolean {
  return ADVANCED_META_FIELD_TYPES.includes(type as FormFieldDataType);
}

export function isDateFieldType(type: string): boolean {
  return type === 'date';
}

export function isSelectFieldType(type: string): boolean {
  return type === 'select';
}

export function getFieldTypeLabel(type: string): string {
  return FIELD_TYPE_LABELS[type as FormFieldDataType] ?? 'Field';
}

/** Default field_options per type. Only types that need initial config are listed. */
export const FIELD_DEFAULT_OPTIONS: Partial<Record<FormFieldDataType, FieldOptions>> = {
  table: {
    table_columns: [
      { id: `col_1`, label: 'Column 1', type: 'text', required: false },
      { id: `col_2`, label: 'Column 2', type: 'text', required: false },
    ],
    min_rows: 1,
    max_rows: 10,
  },
  section: {
    section_title: '',
    section_description: '',
  },
  heading: {
    heading_content: '',
    heading_size: 'medium',
  },
  image: {
    image_url: '',
    image_alt: '',
    image_alignment: 'center',
    image_width: 'medium',
  },
};
