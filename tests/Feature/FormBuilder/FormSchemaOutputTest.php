<?php

namespace Tests\Feature\FormBuilder;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that Form::toSchemaArray() produces the correct representation
 * for fields with and without options_meta, so the frontend can reliably
 * distinguish simple mode from meta mode.
 */
class FormSchemaOutputTest extends TestCase
{
    use RefreshDatabase;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->form = Form::create([
            'form_name' => 'Schema Test Form',
            'form_code' => 'SCH-001 Rev-01',
            'form_family_code' => 'SCH-001',
            'status' => 'Active',
            'is_locked' => true,
            'version' => 1,
            'created_by' => 1,
        ]);
    }

    public function test_options_meta_is_null_in_schema_array_when_field_has_no_meta(): void
    {
        FormField::create([
            'form_id' => $this->form->id,
            'field_name' => 'color',
            'label' => 'Favorite Color',
            'data_type' => 'radio',
            'is_required' => false,
            'field_order' => 0,
            'options' => ['Red', 'Blue', 'Green'],
            'options_meta' => null,
        ]);

        $this->form->load('fields');
        $schema = $this->form->toSchemaArray();

        $field = $schema['fields'][0];
        $this->assertArrayHasKey('options_meta', $field);
        $this->assertNull($field['options_meta'], 'options_meta must be null for simple-options fields so the frontend does not enter meta mode');
    }

    public function test_options_are_preserved_in_schema_array_for_simple_options_field(): void
    {
        FormField::create([
            'form_id' => $this->form->id,
            'field_name' => 'size',
            'label' => 'T-Shirt Size',
            'data_type' => 'select',
            'is_required' => false,
            'field_order' => 0,
            'options' => ['S', 'M', 'L', 'XL'],
            'options_meta' => null,
        ]);

        $this->form->load('fields');
        $schema = $this->form->toSchemaArray();

        $field = $schema['fields'][0];
        $this->assertEquals(['S', 'M', 'L', 'XL'], $field['options']);
        $this->assertNull($field['options_meta']);
    }

    public function test_options_meta_is_returned_correctly_when_set(): void
    {
        $meta = [
            ['label' => 'Option A', 'value' => 'option_a', 'requires_qty' => false, 'qty_label' => 'Qty', 'min_qty' => 0, 'max_qty' => null, 'step' => 1, 'default_qty' => 1, 'unit' => 'pcs', 'requires_text' => false, 'text_label' => 'Specify'],
        ];

        FormField::create([
            'form_id' => $this->form->id,
            'field_name' => 'choice',
            'label' => 'Pick One',
            'data_type' => 'checkbox',
            'is_required' => false,
            'field_order' => 0,
            'options' => [],
            'options_meta' => $meta,
        ]);

        $this->form->load('fields');
        $schema = $this->form->toSchemaArray();

        $field = $schema['fields'][0];
        $this->assertIsArray($field['options_meta']);
        $this->assertCount(1, $field['options_meta']);
        $this->assertSame('Option A', $field['options_meta'][0]['label']);
    }
}
