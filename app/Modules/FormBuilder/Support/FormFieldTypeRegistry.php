<?php

namespace App\Modules\FormBuilder\Support;

final class FormFieldTypeRegistry
{
    private const ALL_TYPES = [
        'text',
        'email',
        'phone',
        'date',
        'textarea',
        'checkbox',
        'radio',
        'select',
        'file',
        'number',
        'table',
        'section',
        'heading',
        'image',
    ];

    private const NON_INPUT_TYPES = [
        'section',
        'heading',
        'image',
    ];

    public static function all(): array
    {
        return self::ALL_TYPES;
    }

    public static function nonInput(): array
    {
        return self::NON_INPUT_TYPES;
    }

    public static function isDate(string $type): bool
    {
        return $type === 'date';
    }

    public static function isNonInput(string $type): bool
    {
        return in_array($type, self::NON_INPUT_TYPES, true);
    }

    public static function isRuntimeColumnType(string $type): bool
    {
        return ! self::isDate($type) && ! self::isNonInput($type);
    }
}
