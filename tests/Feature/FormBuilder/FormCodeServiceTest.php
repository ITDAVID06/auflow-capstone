<?php

namespace Tests\Feature\FormBuilder;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Services\FormCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FormCodeService::class);
    }

    public function test_next_family_code_returns_first_code_when_no_forms_exist(): void
    {
        $code = $this->service->nextFamilyCode();

        $this->assertSame('AUF-Form-00001', $code);
    }

    public function test_next_family_code_returns_next_after_existing_max(): void
    {
        Form::create([
            'form_name' => 'Form A',
            'form_code' => 'AUF-Form-00001 Rev-01',
            'form_family_code' => 'AUF-Form-00001',
            'version' => 1,
            'status' => 'Inactive',
            'created_by' => 1,
        ]);

        Form::create([
            'form_name' => 'Form B',
            'form_code' => 'AUF-Form-00003 Rev-01',
            'form_family_code' => 'AUF-Form-00003',
            'version' => 1,
            'status' => 'Inactive',
            'created_by' => 1,
        ]);

        $code = $this->service->nextFamilyCode();

        $this->assertSame('AUF-Form-00004', $code);
    }

    public function test_next_family_code_considers_soft_deleted_forms(): void
    {
        $form = Form::create([
            'form_name' => 'Archived Form',
            'form_code' => 'AUF-Form-00005 Rev-01',
            'form_family_code' => 'AUF-Form-00005',
            'version' => 1,
            'status' => 'Inactive',
            'created_by' => 1,
        ]);
        $form->delete(); // soft delete

        $code = $this->service->nextFamilyCode();

        $this->assertSame('AUF-Form-00006', $code);
    }
}
