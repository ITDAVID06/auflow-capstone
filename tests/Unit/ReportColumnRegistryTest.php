<?php

namespace Tests\Unit;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\Reports\Services\ReportColumnRegistry;
use App\Modules\UserManagement\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportColumnRegistryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->user = User::create([
            'username' => 'registry_tester',
            'email' => 'registry@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
    }

    public function test_date_type_fields_are_included_in_resolved_columns(): void
    {
        $form = Form::create([
            'form_name' => 'Date Column Test Form',
            'form_code' => 'DCT'.uniqid(),
            'status' => 'Active',
            'is_locked' => true,
            'version' => 1,
            'created_by' => $this->user->account_id,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'appointment_date',
            'label' => 'Appointment Date',
            'data_type' => 'date',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $registry = new ReportColumnRegistry;
        $columns = $registry->resolveAllColumns($form->load('fields'));

        $keys = array_column($columns, 'key');

        $this->assertContains('appointment_date', $keys, 'Date-type form fields must appear in resolved report columns.');
    }

    public function test_non_date_fields_remain_included(): void
    {
        $form = Form::create([
            'form_name' => 'Text Column Test Form',
            'form_code' => 'TCT'.uniqid(),
            'status' => 'Active',
            'is_locked' => true,
            'version' => 1,
            'created_by' => $this->user->account_id,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'full_name',
            'label' => 'Full Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $registry = new ReportColumnRegistry;
        $columns = $registry->resolveAllColumns($form->load('fields'));

        $keys = array_column($columns, 'key');

        $this->assertContains('full_name', $keys, 'Text-type fields must still appear in resolved columns.');
    }

    public function test_system_columns_are_always_present(): void
    {
        $form = Form::create([
            'form_name' => 'System Columns Test Form',
            'form_code' => 'SCT'.uniqid(),
            'status' => 'Active',
            'is_locked' => true,
            'version' => 1,
            'created_by' => $this->user->account_id,
        ]);

        $registry = new ReportColumnRegistry;
        $columns = $registry->resolveAllColumns($form->load('fields'));

        $keys = array_column($columns, 'key');

        $this->assertContains('id', $keys);
        $this->assertContains('submitter_name', $keys);
        $this->assertContains('submission_status', $keys);
        $this->assertContains('created_at', $keys);
    }
}
