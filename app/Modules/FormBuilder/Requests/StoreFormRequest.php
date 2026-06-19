<?php

namespace App\Modules\FormBuilder\Requests;

use App\Modules\FormBuilder\Rules\ValidFieldName;
use App\Modules\FormBuilder\Support\FormFieldTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('forms.manage') ?? false;
    }

    public function rules(): array
    {
        $nameRule = [
            'required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]+$/',
            new ValidFieldName,
        ];

        return [
            'form_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1024',
            'form_category_id' => 'nullable|exists:tbl_form_category,id',
            'version' => 'nullable|integer|min:1',
            'status' => 'required|in:Active,Inactive',
            'email_notifications' => 'boolean',
            'submission_limit' => 'nullable|integer|min:1',

            // single audience permission (optional)
            'permissions' => 'nullable|array|max:1',
            'permissions.*' => [
                'integer',
                Rule::exists('tbl_permission', 'id')->where(function ($q) {
                    $q->where('resource', 'forms')
                        ->whereIn('action', ['student-access', 'staff-access', 'public-access']);
                }),
            ],

            // fields
            'fields' => 'required|array|min:1',
            'fields.*.id' => 'nullable|integer',
            'fields.*.field_name' => $nameRule,
            'fields.*.label' => 'required|string|max:255',
            'fields.*.data_type' => ['required', Rule::in(FormFieldTypeRegistry::all())],
            'fields.*.is_required' => 'boolean',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.help_text' => 'nullable|string|max:500',
            'fields.*.field_order' => 'required|integer|min:0',

            // legacy simple options
            'fields.*.options' => 'nullable|array',
            'fields.*.options.*' => 'nullable|string|max:255',

            // structured options (with qty/text rules)
            'fields.*.options_meta' => 'nullable|array',
            'fields.*.options_meta.*' => 'array',
            'fields.*.options_meta.*.label' => 'required_with:fields.*.options_meta|string|max:255',
            'fields.*.options_meta.*.value' => 'nullable|string|max:255',
            'fields.*.options_meta.*.requires_qty' => 'boolean',
            'fields.*.options_meta.*.qty_label' => 'nullable|string|max:50',
            'fields.*.options_meta.*.min_qty' => 'nullable|integer|min:0',
            'fields.*.options_meta.*.max_qty' => 'nullable|integer|min:0',
            'fields.*.options_meta.*.step' => 'nullable|integer|min:1',
            'fields.*.options_meta.*.default_qty' => 'nullable|integer|min:0',
            'fields.*.options_meta.*.unit' => 'nullable|string|max:20',

            // NEW: text-per-option
            'fields.*.options_meta.*.requires_text' => 'boolean',
            'fields.*.options_meta.*.text_label' => 'nullable|string|max:50',

            // date extras
            'fields.*.use_slots' => 'boolean',
            'fields.*.require_facility' => 'boolean',

            // NEW: date mode (single vs range) — only meaningful for data_type === 'date'
            'fields.*.date_mode' => 'nullable|string|in:single,range',

            // field options (auto-fill, etc.)
            'fields.*.field_options' => 'nullable|array',
            'fields.*.field_options.auto_fill_name' => 'nullable|boolean',

            // section field configuration
            'fields.*.field_options.section_title' => 'nullable|string|max:255',
            'fields.*.field_options.section_description' => 'nullable|string|max:1000',

            // heading field configuration
            'fields.*.field_options.heading_content' => 'nullable|string|max:1000',
            'fields.*.field_options.heading_size' => 'nullable|in:small,medium,large',

            // image field configuration
            'fields.*.field_options.image_url' => 'nullable|string|max:500',
            'fields.*.field_options.image_path' => 'nullable|string|max:500',
            'fields.*.field_options.image_alt' => 'nullable|string|max:255',
            'fields.*.field_options.image_alignment' => 'nullable|in:left,center,right',
            'fields.*.field_options.image_width' => 'nullable|in:small,medium,large,full',

            // table field configuration
            'fields.*.field_options.table_columns' => 'nullable|array',
            'fields.*.field_options.table_columns.*.id' => 'nullable|string|max:50',
            'fields.*.field_options.table_columns.*.label' => 'required_with:fields.*.field_options.table_columns|string|max:100',
            'fields.*.field_options.table_columns.*.type' => 'nullable|in:text,number,date,textarea',
            'fields.*.field_options.table_columns.*.required' => 'nullable|boolean',
            'fields.*.field_options.min_rows' => 'nullable|integer|min:0|max:100',
            'fields.*.field_options.max_rows' => 'nullable|integer|min:1|max:100',

            // conditional logic
            'fields.*.conditions' => 'nullable|array',
            'fields.*.conditions.*.field_name' => 'required_with:fields.*.conditions|string|max:255',
            'fields.*.conditions.*.operator' => 'required_with:fields.*.conditions|in:equals,not_equals,contains,not_empty,is_empty',
            'fields.*.conditions.*.value' => 'nullable',
            'fields.*.conditions.*.action' => 'required_with:fields.*.conditions|in:show,hide',

            // sensitive fields for encryption
            'sensitive_fields' => 'nullable|array',
            'sensitive_fields.*' => 'string|max:100',

            // per-field masking & visibility flags
            'fields.*.is_sensitive' => 'boolean',
            'fields.*.is_publicly_verifiable' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'form_category_id.exists' => 'The selected category is invalid.',
            'fields.*.field_name.regex' => 'Field names may only contain letters, numbers, and underscores.',
            'fields.*.options_meta.*.label.required_with' => 'Each choice needs a label.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Reset shared state for duplicate field name detection
        ValidFieldName::resetSeen();

        $this->merge([
            'form_name' => $this->form_name ? trim($this->form_name) : null,
            'form_code' => $this->form_code ? trim($this->form_code) : null,
            'description' => $this->description ? trim($this->description) : null,
            'form_category_id' => $this->form_category_id ?? null,
        ]);

        if (! is_array($this->fields)) {
            return;
        }

        $fields = array_map(function ($field) {
            $rawId = $field['id'] ?? null;
            if (is_string($rawId)) {
                $rawId = trim($rawId);
            }
            if (is_int($rawId) || (is_string($rawId) && ctype_digit($rawId))) {
                $field['id'] = (int) $rawId;
            } else {
                $field['id'] = null;
            }

            $field['label'] = trim((string) ($field['label'] ?? ''));
            $field['field_name'] = trim((string) ($field['field_name'] ?? ''));
            $field['placeholder'] = trim((string) ($field['placeholder'] ?? ''));
            $field['help_text'] = isset($field['help_text']) ? trim((string) $field['help_text']) : null;

            if (isset($field['options']) && is_array($field['options'])) {
                $field['options'] = array_map(function ($v) {
                    return trim((string) $v);
                }, $field['options']);
            }

            // Normalize options_meta if present
            if (isset($field['options_meta']) && is_array($field['options_meta'])) {
                $field['options_meta'] = array_map(function ($o) {
                    $label = (string) ($o['label'] ?? '');
                    $min = isset($o['min_qty']) ? max(0, (int) $o['min_qty']) : 0;

                    $maxRaw = $o['max_qty'] ?? null;
                    $max = (is_null($maxRaw) || $maxRaw === '' ? null : max(0, (int) $maxRaw));

                    $step = isset($o['step']) ? max(1, (int) $o['step']) : 1;

                    $defaultRaw = isset($o['default_qty']) ? (int) $o['default_qty'] : 1;
                    $default = $this->clampInt($defaultRaw, $min, $max);

                    return [
                        'label' => $label,
                        'value' => (isset($o['value']) && trim((string) $o['value']) !== '')
                                            ? (string) $o['value']
                                            : $this->slugify($label),
                        'requires_qty' => (bool) ($o['requires_qty'] ?? false),
                        'qty_label' => isset($o['qty_label']) && trim((string) $o['qty_label']) !== '' ? (string) $o['qty_label'] : 'Qty',
                        'min_qty' => $min,
                        'max_qty' => $max,
                        'step' => $step,
                        'default_qty' => $default,
                        'unit' => isset($o['unit']) && trim((string) $o['unit']) !== '' ? (string) $o['unit'] : 'pcs',

                        // NEW text controls
                        'requires_text' => (bool) ($o['requires_text'] ?? false),
                        'text_label' => isset($o['text_label']) && trim((string) $o['text_label']) !== '' ? (string) $o['text_label'] : 'Specify',
                    ];
                }, $field['options_meta']);

                // Enforce: SELECT should not carry qty/text controls
                if (($field['data_type'] ?? null) === 'select') {
                    $field['options_meta'] = array_map(function ($o) {
                        unset($o['requires_qty'], $o['qty_label'], $o['min_qty'], $o['max_qty'], $o['step'], $o['default_qty'], $o['unit']);
                        unset($o['requires_text'], $o['text_label']);

                        return $o;
                    }, $field['options_meta']);
                }
            }

            // Non-input fields (section, heading, image) must never be required
            if (FormFieldTypeRegistry::isNonInput((string) ($field['data_type'] ?? ''))) {
                $field['is_required'] = false;
            }

            // Date flags default
            $field['use_slots'] = (bool) ($field['use_slots'] ?? false);
            // require_facility is only meaningful when slots are active.
            // If use_slots is off, clear any stale true value so the field is
            // not silently dropped from the student submission form.
            $field['require_facility'] = $field['use_slots']
                ? (bool) ($field['require_facility'] ?? false)
                : false;

            // Normalize field_options — whitelist known keys, preserve all
            if (isset($field['field_options']) && is_array($field['field_options'])) {
                $allowed = [
                    'auto_fill_name', 'table_columns', 'min_rows', 'max_rows',
                    'section_title', 'section_description',
                    'heading_content', 'heading_size',
                    'image_url', 'image_alt', 'image_alignment', 'image_width', 'image_path',
                ];

                $cleaned = array_intersect_key(
                    $field['field_options'],
                    array_flip($allowed)
                );

                // Cast booleans
                if (array_key_exists('auto_fill_name', $cleaned)) {
                    $cleaned['auto_fill_name'] = (bool) $cleaned['auto_fill_name'];
                }

                $field['field_options'] = ! empty($cleaned) ? $cleaned : null;
            } else {
                $field['field_options'] = null;
            }

            // NEW: normalize date_mode for date fields
            if (($field['data_type'] ?? null) === 'date') {
                $mode = $field['date_mode'] ?? 'single';
                $mode = in_array($mode, ['single', 'range'], true) ? $mode : 'single';

                // If using slots, force single mode (range handled elsewhere)
                if (! empty($field['use_slots'])) {
                    $mode = 'single';
                }

                $field['date_mode'] = $mode;
            } else {
                // Not a date field: ensure we don't carry stray date_mode
                if (isset($field['date_mode'])) {
                    unset($field['date_mode']);
                }
            }

            return $field;
        }, $this->fields);

        $this->merge(['fields' => $fields]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $fields = $this->input('fields', []);
            if (! is_array($fields)) {
                return;
            }

            foreach ($fields as $i => $field) {
                if (! isset($field['options_meta']) || ! is_array($field['options_meta'])) {
                    continue;
                }

                foreach ($field['options_meta'] as $j => $opt) {
                    $min = (int) ($opt['min_qty'] ?? 0);
                    $max = array_key_exists('max_qty', $opt) ? $opt['max_qty'] : null; // may be null
                    $def = (int) ($opt['default_qty'] ?? 1);

                    if ($max !== null && $min > (int) $max) {
                        $v->errors()->add(
                            "fields.$i.options_meta.$j.max_qty",
                            'Max must be greater than or equal to Min.'
                        );
                    }

                    if ($max === null) {
                        if ($def < $min) {
                            $v->errors()->add(
                                "fields.$i.options_meta.$j.default_qty",
                                'Default must be greater than or equal to Min.'
                            );
                        }
                    } else {
                        if ($def < $min || $def > (int) $max) {
                            $v->errors()->add(
                                "fields.$i.options_meta.$j.default_qty",
                                'Default must be between Min and Max.'
                            );
                        }
                    }
                }
            }
        });
    }

    /** Stable slug for internal value keys. */
    private function slugify(string $s): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $ascii = $ascii === false ? $s : $ascii;
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $ascii) ?? '');

        return trim($slug, '_');
    }

    /** Clamp $value to [$min, $max]; $max may be null (no cap). */
    private function clampInt(int $value, int $min, ?int $max): int
    {
        $v = max($value, $min);

        return is_null($max) ? $v : min($v, $max);
    }
}
