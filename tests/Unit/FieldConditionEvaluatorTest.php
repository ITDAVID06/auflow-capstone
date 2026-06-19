<?php

namespace Tests\Unit;

use App\Modules\FormBuilder\Support\FieldConditionEvaluator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FieldConditionEvaluatorTest extends TestCase
{
    public function test_field_is_visible_when_no_conditions_are_defined(): void
    {
        $field = [
            'field_name' => 'additional_details',
            'conditions' => null,
        ];

        $this->assertTrue(FieldConditionEvaluator::isFieldVisible($field, []));
    }

    public function test_hide_condition_hides_field_when_rule_matches(): void
    {
        $field = [
            'field_name' => 'additional_details',
            'conditions' => [
                [
                    'field_name' => 'request_type',
                    'operator' => 'equals',
                    'value' => 'simple',
                    'action' => 'hide',
                ],
            ],
        ];

        $this->assertFalse(FieldConditionEvaluator::isFieldVisible($field, ['request_type' => 'simple']));
        $this->assertTrue(FieldConditionEvaluator::isFieldVisible($field, ['request_type' => 'complex']));
    }

    public function test_last_matching_condition_takes_precedence(): void
    {
        $field = [
            'field_name' => 'details',
            'conditions' => [
                [
                    'field_name' => 'category',
                    'operator' => 'equals',
                    'value' => 'urgent',
                    'action' => 'hide',
                ],
                [
                    'field_name' => 'category',
                    'operator' => 'equals',
                    'value' => 'urgent',
                    'action' => 'show',
                ],
            ],
        ];

        $this->assertTrue(FieldConditionEvaluator::isFieldVisible($field, ['category' => 'urgent']));
    }

    public function test_visible_fields_filters_hidden_fields(): void
    {
        $fields = collect([
            [
                'field_name' => 'request_type',
                'conditions' => null,
            ],
            [
                'field_name' => 'additional_details',
                'conditions' => [
                    [
                        'field_name' => 'request_type',
                        'operator' => 'equals',
                        'value' => 'simple',
                        'action' => 'hide',
                    ],
                ],
            ],
        ]);

        $visibleWhenSimple = FieldConditionEvaluator::visibleFields($fields, ['request_type' => 'simple']);
        $visibleWhenComplex = FieldConditionEvaluator::visibleFields($fields, ['request_type' => 'complex']);

        $this->assertSame(['request_type'], $this->fieldNames($visibleWhenSimple));
        $this->assertSame(['request_type', 'additional_details'], $this->fieldNames($visibleWhenComplex));
    }

    private function fieldNames(Collection $fields): array
    {
        return $fields
            ->map(fn ($field): string => (string) data_get($field, 'field_name', ''))
            ->values()
            ->all();
    }
}
