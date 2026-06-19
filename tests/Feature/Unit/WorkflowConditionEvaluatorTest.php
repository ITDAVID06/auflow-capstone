<?php

namespace Tests\Feature\Unit;

use App\Modules\WorkflowBuilder\Support\WorkflowConditionEvaluator;
use Tests\TestCase;

class WorkflowConditionEvaluatorTest extends TestCase
{
    // --- null / missing condition ---

    public function test_null_condition_returns_true(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(null, []));
    }

    public function test_empty_array_condition_returns_true(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate([], []));
    }

    public function test_unknown_field_returns_true(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'missing', 'operator' => '>', 'value' => 100],
            ['amount' => 200]
        ));
    }

    // --- numeric operators ---

    public function test_greater_than_passes(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'amount', 'operator' => '>', 'value' => 100],
            ['amount' => 200]
        ));
    }

    public function test_greater_than_fails(): void
    {
        $this->assertFalse(WorkflowConditionEvaluator::evaluate(
            ['field' => 'amount', 'operator' => '>', 'value' => 500],
            ['amount' => 100]
        ));
    }

    public function test_greater_than_or_equal_passes_on_equal(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'amount', 'operator' => '>=', 'value' => 100],
            ['amount' => 100]
        ));
    }

    public function test_less_than_passes(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'amount', 'operator' => '<', 'value' => 500],
            ['amount' => 100]
        ));
    }

    public function test_less_than_or_equal_passes(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'amount', 'operator' => '<=', 'value' => 100],
            ['amount' => 100]
        ));
    }

    // --- equality operators ---

    public function test_equals_passes(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'status', 'operator' => '=', 'value' => 'approved'],
            ['status' => 'approved']
        ));
    }

    public function test_equals_fails(): void
    {
        $this->assertFalse(WorkflowConditionEvaluator::evaluate(
            ['field' => 'status', 'operator' => '=', 'value' => 'approved'],
            ['status' => 'pending']
        ));
    }

    public function test_not_equals_passes(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'status', 'operator' => '!=', 'value' => 'rejected'],
            ['status' => 'pending']
        ));
    }

    // --- contains operator ---

    public function test_contains_passes(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'notes', 'operator' => 'contains', 'value' => 'urgent'],
            ['notes' => 'this is urgent']
        ));
    }

    public function test_contains_fails(): void
    {
        $this->assertFalse(WorkflowConditionEvaluator::evaluate(
            ['field' => 'notes', 'operator' => 'contains', 'value' => 'urgent'],
            ['notes' => 'routine review']
        ));
    }

    // --- JSON-encoded payload values ---

    public function test_json_encoded_numeric_string_is_decoded_for_comparison(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'amount', 'operator' => '>', 'value' => 100],
            ['amount' => '200']
        ));
    }

    // --- unknown operator falls through ---

    public function test_unknown_operator_returns_true(): void
    {
        $this->assertTrue(WorkflowConditionEvaluator::evaluate(
            ['field' => 'amount', 'operator' => 'between', 'value' => 100],
            ['amount' => 200]
        ));
    }
}
