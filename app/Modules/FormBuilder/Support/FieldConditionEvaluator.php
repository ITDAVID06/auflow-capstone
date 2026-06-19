<?php

namespace App\Modules\FormBuilder\Support;

use Illuminate\Support\Collection;

final class FieldConditionEvaluator
{
    public static function visibleFields(Collection $fields, array $values): Collection
    {
        return $fields->filter(fn ($field) => self::isFieldVisible($field, $values))->values();
    }

    public static function isFieldVisible(object|array $field, array $values): bool
    {
        $conditions = self::fieldConditions($field);

        if (empty($conditions)) {
            return true;
        }

        $visible = true;

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $matches = self::matchesCondition($condition, $values);
            if (! $matches) {
                continue;
            }

            $action = (string) ($condition['action'] ?? 'show');
            $visible = $action !== 'hide';
        }

        return $visible;
    }

    private static function fieldConditions(object|array $field): array
    {
        $conditions = is_array($field)
            ? ($field['conditions'] ?? [])
            : ($field->conditions ?? []);

        if (is_string($conditions)) {
            $decoded = json_decode($conditions, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($conditions) ? $conditions : [];
    }

    private static function matchesCondition(array $condition, array $values): bool
    {
        $targetField = (string) ($condition['field_name'] ?? '');
        if ($targetField === '') {
            return false;
        }

        $operator = (string) ($condition['operator'] ?? 'equals');
        $actual = $values[$targetField] ?? null;
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            'equals' => self::stringify($actual) === self::stringify($expected),
            'not_equals' => self::stringify($actual) !== self::stringify($expected),
            'contains' => str_contains(mb_strtolower(self::stringify($actual)), mb_strtolower(self::stringify($expected))),
            'not_empty' => self::isNotEmpty($actual),
            'is_empty' => ! self::isNotEmpty($actual),
            default => false,
        };
    }

    private static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_map(fn ($item) => (string) $item, $value));
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private static function isNotEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, fn ($item) => ! in_array($item, [null, '', []], true))) > 0;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return true;
        }

        return trim((string) ($value ?? '')) !== '';
    }
}
