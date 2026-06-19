<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\Reports\Models\ScheduledExport;
use App\Modules\Reports\Services\ScheduledExportService;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportsMonthlyFrequencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_monthly_export_is_not_due_after_28_days(): void
    {
        $user = $this->createUser();
        $form = $this->createForm($user);

        ScheduledExport::create([
            'form_id'          => $form->id,
            'recipient_email'  => 'test@example.com',
            'frequency'        => 'monthly',
            'export_type'      => 'csv',
            'is_active'        => true,
            'created_by'       => $user->account_id,
            'last_sent_at'     => now()->subDays(28),
            'filter_state'     => [],
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertCount(0, $due, 'A monthly export sent 28 days ago should not be due yet.');
    }

    public function test_monthly_export_is_due_after_32_days(): void
    {
        $user = $this->createUser();
        $form = $this->createForm($user);

        ScheduledExport::create([
            'form_id'         => $form->id,
            'recipient_email' => 'test@example.com',
            'frequency'       => 'monthly',
            'export_type'     => 'csv',
            'is_active'       => true,
            'created_by'      => $user->account_id,
            'last_sent_at'    => now()->subDays(32),
            'filter_state'    => [],
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertCount(1, $due, 'A monthly export sent 32 days ago should be due.');
    }

    public function test_monthly_export_is_due_when_never_sent(): void
    {
        $user = $this->createUser();
        $form = $this->createForm($user);

        ScheduledExport::create([
            'form_id'         => $form->id,
            'recipient_email' => 'test@example.com',
            'frequency'       => 'monthly',
            'export_type'     => 'csv',
            'is_active'       => true,
            'created_by'      => $user->account_id,
            'last_sent_at'    => null,
            'filter_state'    => [],
        ]);

        $due = app(ScheduledExportService::class)->findDue();

        $this->assertCount(1, $due);
    }

    private function createUser(): User
    {
        $role = Role::create(['role_name' => 'Role ' . uniqid(), 'description' => 'Test', 'is_active' => true]);

        $user = User::create([
            'username'       => 'user_' . uniqid(),
            'email'          => 'user_' . uniqid() . '@test.com',
            'password'       => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        UserRole::create([
            'account_id'    => $user->account_id,
            'role_id'       => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active'     => true,
            'assigned_by'   => $user->account_id,
        ]);

        return $user;
    }

    private function createForm(User $creator): Form
    {
        $form = Form::create([
            'form_name'  => 'Form ' . uniqid(),
            'form_code'  => 'F' . uniqid(),
            'description' => 'Test',
            'version'    => 1,
            'status'     => 'Active',
            'created_by' => $creator->account_id,
            'is_locked'  => true,
        ]);

        FormField::create([
            'form_id'    => $form->id,
            'field_name' => 'field_text',
            'label'      => 'Text',
            'data_type'  => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        return $form;
    }
}
