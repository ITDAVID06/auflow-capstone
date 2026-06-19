<?php

namespace App\Modules\AdminSubmissions\Services;

class SubmissionFieldValueNormalizer
{
    /**
     * Decode strings that look like JSON, including HTML-escaped or backslash-escaped payloads.
     */
    public function decodeJsonish(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $input = trim($value);
        if ($input === '') {
            return $input;
        }

        $decodedHtml = html_entity_decode($input, ENT_QUOTES | ENT_HTML5);
        $variants = [
            $input,
            $decodedHtml,
            $this->stripOuterQuotes($input),
            $this->stripOuterQuotes($decodedHtml),
            $this->deSlash($input),
            $this->deSlash($decodedHtml),
            $this->deSlash($this->stripOuterQuotes($input)),
            $this->deSlash($this->stripOuterQuotes($decodedHtml)),
        ];

        foreach ($variants as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    return array_map(function ($item) {
                        if (is_string($item)) {
                            $nested = json_decode($item, true);

                            return json_last_error() === JSON_ERROR_NONE ? $nested : $item;
                        }

                        return $item;
                    }, $decoded);
                }

                return $decoded;
            }
        }

        return $this->deSlash($this->stripOuterQuotes($decodedHtml));
    }

    /**
     * Normalize choice-like values to arrays / objects for frontend rendering.
     */
    public function normalizeChoiceValue(string $dataType, mixed $rawValue): mixed
    {
        $choiceTypes = ['checkbox', 'radio', 'select', 'multiselect'];
        if (! in_array(strtolower($dataType), $choiceTypes, true)) {
            return $rawValue;
        }

        $decoded = $this->decodeJsonish($rawValue);

        if (in_array(strtolower($dataType), ['checkbox', 'multiselect'], true)) {
            if (is_array($decoded)) {
                return $decoded;
            }

            if ($decoded === null || $decoded === '') {
                return [];
            }

            return [$decoded];
        }

        if (is_array($decoded)) {
            if (array_is_list($decoded)) {
                return $decoded[0] ?? null;
            }

            return $decoded;
        }

        return $decoded;
    }

    private function stripOuterQuotes(string $value): string
    {
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private function deSlash(string $value): string
    {
        $value = str_replace(['\\n', '\\r', '\\t'], '', $value);

        return str_replace(['\\"', "\\'"], ['"', "'"], $value);
    }
}
