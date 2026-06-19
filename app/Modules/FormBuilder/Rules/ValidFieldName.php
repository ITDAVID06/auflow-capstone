<?php

namespace App\Modules\FormBuilder\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidFieldName implements ValidationRule
{
    private const RESERVED = ['id', 'account_id', 'created_at', 'updated_at'];

    /** @var array<string,bool> Shared across all field validations in a single request */
    private static array $seen = [];

    /** Reset between requests (called from StoreFormRequest::prepareForValidation). */
    public static function resetSeen(): void
    {
        self::$seen = [];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $name = (string) $value;

        if (in_array(strtolower($name), self::RESERVED, true)) {
            $fail("Field name '{$name}' is reserved.");

            return;
        }
        if (isset(self::$seen[$name])) {
            $fail("Duplicate field name '{$name}' within the form.");

            return;
        }
        self::$seen[$name] = true;
    }
}
