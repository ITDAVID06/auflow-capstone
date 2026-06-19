<?php

namespace Tests\Feature\FormBuilder;

use App\Modules\FormBuilder\Requests\StoreFormRequest;
use Illuminate\Http\Request;
use Tests\TestCase;

class StoreFormRequestNormalizationTest extends TestCase
{
    /**
     * When use_slots is false, any leftover require_facility=true must be cleared to false.
     * A field with use_slots=false and require_facility=true falls through all three date
     * field categories in useFormSubmissionState.ts, making it invisible to students.
     */
    public function test_require_facility_is_cleared_when_use_slots_is_disabled(): void
    {
        $request = $this->makeRequest([
            'field_name' => 'appointment',
            'label' => 'Appointment',
            'data_type' => 'date',
            'is_required' => false,
            'use_slots' => false,
            'require_facility' => true, // stale — slots were disabled but flag was not cleared
            'date_mode' => 'single',
        ]);

        $this->invokePreparation($request);

        $fields = $request->input('fields');
        $this->assertFalse((bool) $fields[0]['require_facility'], 'require_facility must be false when use_slots is false');
    }

    /**
     * When use_slots is true, require_facility is kept as supplied (true stays true).
     */
    public function test_require_facility_is_preserved_when_use_slots_is_enabled(): void
    {
        $request = $this->makeRequest([
            'field_name' => 'appointment',
            'label' => 'Appointment',
            'data_type' => 'date',
            'is_required' => false,
            'use_slots' => true,
            'require_facility' => true,
            'date_mode' => 'single',
        ]);

        $this->invokePreparation($request);

        $fields = $request->input('fields');
        $this->assertTrue((bool) $fields[0]['require_facility']);
    }

    /**
     * Non-input field types (section, heading, image) must never be required.
     */
    public function test_is_required_is_forced_false_for_section_field(): void
    {
        $request = $this->makeRequest([
            'field_name' => 'section_1',
            'label' => 'My Section',
            'data_type' => 'section',
            'is_required' => true, // must be cleared
        ]);

        $this->invokePreparation($request);

        $fields = $request->input('fields');
        $this->assertFalse((bool) $fields[0]['is_required'], 'is_required must be false for section fields');
    }

    public function test_is_required_is_forced_false_for_heading_field(): void
    {
        $request = $this->makeRequest([
            'field_name' => 'heading_1',
            'label' => 'My Heading',
            'data_type' => 'heading',
            'is_required' => true,
        ]);

        $this->invokePreparation($request);

        $fields = $request->input('fields');
        $this->assertFalse((bool) $fields[0]['is_required'], 'is_required must be false for heading fields');
    }

    public function test_is_required_is_forced_false_for_image_field(): void
    {
        $request = $this->makeRequest([
            'field_name' => 'image_1',
            'label' => 'My Image',
            'data_type' => 'image',
            'is_required' => true,
        ]);

        $this->invokePreparation($request);

        $fields = $request->input('fields');
        $this->assertFalse((bool) $fields[0]['is_required'], 'is_required must be false for image fields');
    }

    public function test_is_required_is_preserved_for_input_fields(): void
    {
        $request = $this->makeRequest([
            'field_name' => 'my_text',
            'label' => 'My Text',
            'data_type' => 'text',
            'is_required' => true,
        ]);

        $this->invokePreparation($request);

        $fields = $request->input('fields');
        $this->assertTrue((bool) $fields[0]['is_required'], 'is_required must be preserved for input fields');
    }

    // -------------------------------------------------------------------------

    private function makeRequest(array $field): StoreFormRequest
    {
        $httpRequest = Request::create('/admin/forms', 'POST', [
            'form_name' => 'Test Form',
            'fields' => [$field],
        ]);

        $request = StoreFormRequest::createFrom($httpRequest);
        $request->setContainer(app());
        $request->setRedirector(app(\Illuminate\Routing\Redirector::class));

        return $request;
    }

    private function invokePreparation(StoreFormRequest $request): void
    {
        $method = new \ReflectionMethod(StoreFormRequest::class, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
