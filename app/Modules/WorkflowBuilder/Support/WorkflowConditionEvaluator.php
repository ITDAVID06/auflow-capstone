<?php

namespace App\Modules\WorkflowBuilder\Support;

/**
 * Evaluates a simple step branch condition against a flattened submission payload.
 *
 * A condition has the shape:
 *   {"field": "amount", "operator": ">", "value": 500}
 *
 * Supported operators: =, !=, >, >=, <, <=, contains
 *
 * Returns true  → condition passes; step should execute.
 * Returns false → condition fails; step should be skipped.
 */
class WorkflowConditionEvaluator
{
    /**
     * @param  array{field: string, operator: string, value: mixed}|null  $condition
     * @param  array<string, mixed>  $payload  Flattened submission data
     */
    public static function evaluate(?array $condition, array $payload): bool
    {
        if (empty($condition)) {
            return true;
        }

        $field = (string) ($condition['field'] ?? '');
        $operator = (string) ($condition['operator'] ?? '=');
        $conditionValue = $condition['value'] ?? null;

        if ($field === '' || ! array_key_exists($field, $payload)) {
            return true; // Unknown field → do not skip
        }

        $fieldValue = $payload[$field];

        // Normalise strings that contain JSON
        if (is_string($fieldValue)) {
            $decoded = json_decode($fieldValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $fieldValue = $decoded;
            }
        }

        return self::compare($fieldValue, $operator, $conditionValue);
    }

    private static function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=' => $actual == $expected,
            '==' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            '>=' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            '<' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            '<=' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            default => true, // Unknown operator → do not skip
        };
    }
}
