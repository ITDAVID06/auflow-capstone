import type { FieldCondition, FormField } from '@/types/form';

function stringify(value: unknown): string {
  if (Array.isArray(value)) {
    return value.map((item) => String(item ?? '')).join(',');
  }

  if (typeof value === 'boolean') {
    return value ? '1' : '0';
  }

  if (value === null || typeof value === 'undefined') {
    return '';
  }

  return String(value).trim();
}

function isNotEmpty(value: unknown): boolean {
  if (Array.isArray(value)) {
    return value.some((item) => {
      if (Array.isArray(item)) {
        return item.length > 0;
      }
      if (item === null || typeof item === 'undefined') {
        return false;
      }
      return String(item).trim() !== '';
    });
  }

  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return true;
  }

  return String(value ?? '').trim() !== '';
}

function matchesCondition(condition: FieldCondition, values: Record<string, unknown>): boolean {
  const target = condition.field_name;
  if (!target) {
    return false;
  }

  const actual = values[target];
  const expected = condition.value;

  switch (condition.operator) {
    case 'equals':
      return stringify(actual) === stringify(expected);
    case 'not_equals':
      return stringify(actual) !== stringify(expected);
    case 'contains':
      return stringify(actual).toLowerCase().includes(stringify(expected).toLowerCase());
    case 'not_empty':
      return isNotEmpty(actual);
    case 'is_empty':
      return !isNotEmpty(actual);
    default:
      return false;
  }
}

export function isFieldVisible(field: FormField, values: Record<string, unknown>): boolean {
  const conditions = field.conditions ?? [];

  if (!Array.isArray(conditions) || conditions.length === 0) {
    return true;
  }

  let visible = true;

  for (const condition of conditions) {
    if (!condition) {
      continue;
    }

    if (!matchesCondition(condition, values)) {
      continue;
    }

    visible = condition.action !== 'hide';
  }

  return visible;
}

export function getVisibleFields(fields: FormField[], values: Record<string, unknown>): FormField[] {
  return fields.filter((field) => isFieldVisible(field, values));
}
