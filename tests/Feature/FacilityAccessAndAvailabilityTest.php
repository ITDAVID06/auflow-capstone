<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Facility;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\FormBuilder\Support\FormFieldTypeRegistry;
use App\Modules\StaffDashboard\Services\StaffSubmissionService;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FacilityAccessAndAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_facility_management_routes_require_facilities_manage_permission(): void
    {
        $withoutPermission = $this->createUserWithPermissions(['forms.view']);
        $withPermission = $this->createUserWithPermissions(['facilities.manage']);

        $this->actingAs($withoutPermission)
            ->get('/admin/facilities/calendar')
            ->assertForbidden();

        $this->actingAs($withoutPermission)
            ->post('/admin/facilities', ['name' => 'New Facility'])
            ->assertForbidden();

        $this->actingAs($withPermission)
            ->get('/admin/facilities/calendar')
            ->assertOk();
    }

    public function test_active_and_availability_endpoints_are_accessible_to_authenticated_users_and_filter_slots(): void
    {
        $user = $this->createUserWithPermissions(['forms.view']);
        $facility = Facility::create([
            'name' => 'Main Hall',
            'description' => 'Test facility',
            'is_active' => true,
        ]);

        $form1 = Form::create([
            'form_name' => 'Availability Form 1',
            'form_code' => 'AVAIL1'.uniqid(),
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        $form2 = Form::create([
            'form_name' => 'Availability Form 2',
            'form_code' => 'AVAIL2'.uniqid(),
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        DB::table('tbl_slots')->insert([
            [
                'form_id' => $form1->id,
                'submission_id' => 100,
                'account_id' => $user->account_id,
                'facility_id' => null,
                'date' => '2026-02-14',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'form_id' => $form1->id,
                'submission_id' => 101,
                'account_id' => $user->account_id,
                'facility_id' => null,
                'date' => '2026-02-14',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'status' => 'Rejected',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'form_id' => $form2->id,
                'submission_id' => 102,
                'account_id' => $user->account_id,
                'facility_id' => $facility->id,
                'date' => '2026-02-14',
                'start_time' => '13:00',
                'end_time' => '14:00',
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->get('/admin/facilities/active')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/admin/facilities/slots/availability?date=2026-02-14')
            ->assertOk()
            ->assertJsonPath('date', '2026-02-14')
            ->assertJsonCount(1, 'slots')
            ->assertJsonPath('slots.0.start_time', '09:00')
            ->assertJsonPath('slots.0.end_time', '10:00');

        $this->actingAs($user)
            ->getJson('/admin/facilities/slots/availability?date=2026-02-14&facility_id='.$facility->id)
            ->assertOk()
            ->assertJsonCount(1, 'slots')
            ->assertJsonPath('slots.0.start_time', '13:00')
            ->assertJsonPath('slots.0.end_time', '14:00')
            ->assertJsonPath('slots.0.facility_id', $facility->id);
    }

    public function test_calendar_events_are_scoped_by_date_range_and_filters(): void
    {
        $user = $this->createUserWithPermissions(['facilities.manage']);
        $facilityA = Facility::create([
            'name' => 'Gym',
            'description' => 'Gym facility',
            'is_active' => true,
        ]);
        $facilityB = Facility::create([
            'name' => 'Auditorium',
            'description' => 'Auditorium facility',
            'is_active' => true,
        ]);

        $calForm = Form::create([
            'form_name' => 'Calendar Events Form',
            'form_code' => 'CAL'.uniqid(),
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        DB::table('tbl_slots')->insert([
            [
                'form_id' => $calForm->id,
                'submission_id' => 200,
                'account_id' => $user->account_id,
                'facility_id' => $facilityA->id,
                'date' => '2026-02-10',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'form_id' => $calForm->id,
                'submission_id' => 201,
                'account_id' => $user->account_id,
                'facility_id' => $facilityA->id,
                'date' => '2026-02-20',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'Approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'form_id' => $calForm->id,
                'submission_id' => 202,
                'account_id' => $user->account_id,
                'facility_id' => $facilityB->id,
                'date' => '2026-03-05',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'status' => 'Pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/admin/facilities/calendar/events?start=2026-02-01&end=2026-02-28')
            ->assertOk()
            ->assertJsonCount(2);

        $this->actingAs($user)
            ->getJson('/admin/facilities/calendar/events?start=2026-02-01&end=2026-02-28&facility_id='.$facilityA->id.'&status=Approved')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.submissionId', 201)
            ->assertJsonPath('0.status', 'Approved');
    }

    public function test_calendar_events_reject_overly_large_date_window(): void
    {
        $user = $this->createUserWithPermissions(['facilities.manage']);

        $this->actingAs($user)
            ->getJson('/admin/facilities/calendar/events?start=2026-01-01&end=2026-06-30')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end']);
    }

    public function test_submission_conflict_guard_blocks_overlapping_timeslot(): void
    {
        $user = $this->createUserWithPermissions(['forms.view']);
        $facility = Facility::create([
            'name' => 'Conference Room',
            'description' => 'Main conference room',
            'is_active' => true,
        ]);

        $form = Form::create([
            'form_name' => 'Booking Form',
            'form_code' => 'FORM'.uniqid(),
            'description' => 'Test booking form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_date_booking',
            'label' => 'Booking Date',
            'data_type' => 'date',
            'is_required' => false,
            'use_slots' => true,
            'require_facility' => true,
            'date_mode' => 'single',
            'field_order' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_textarea_notes',
            'label' => 'Notes',
            'data_type' => 'textarea',
            'is_required' => false,
            'field_order' => 2,
        ]);

        $this->createLegacyRuntimeTable($form->fresh('fields'));

        Workflow::create([
            'workflow_name' => 'Booking Conflict Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'status' => 'Active',
            'created_by' => $user->account_id,
        ]);

        DB::table('tbl_slots')->insert([
            'form_id' => $form->id,
            'submission_id' => 9001,
            'account_id' => $user->account_id,
            'facility_id' => $facility->id,
            'date' => '2026-02-15',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $request = Request::create('/user/forms/'.$form->id.'/submit', 'POST', [
            'slots' => [
                [
                    'date' => '2026-02-15',
                    'start_time' => '09:30',
                    'end_time' => '10:30',
                    'facility_id' => $facility->id,
                ],
            ],
        ]);

        $service = app(StudentSubmissionService::class);

        $this->expectException(ValidationException::class);
        $service->handleSubmission($request, $form->id);
    }

    public function test_submission_allows_date_only_slot_when_no_time_overlap_check_applies(): void
    {
        $user = $this->createUserWithPermissions(['forms.view']);

        $form = Form::create([
            'form_name' => 'Date Only Form',
            'form_code' => 'FORM'.uniqid(),
            'description' => 'Test date form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_date_booking',
            'label' => 'Booking Date',
            'data_type' => 'date',
            'is_required' => false,
            'use_slots' => true,
            'require_facility' => false,
            'date_mode' => 'single',
            'field_order' => 1,
        ]);

        $this->createLegacyRuntimeTable($form->fresh('fields'));

        $workflowDateOnly = Workflow::create([
            'workflow_name' => 'Date Only Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'status' => 'Active',
            'created_by' => $user->account_id,
        ]);

        WorkflowVersion::create([
            'workflow_id' => $workflowDateOnly->id,
            'version_number' => 1,
            'steps_snapshot' => [],
            'published_at' => now(),
        ]);

        $this->actingAs($user);

        $request = Request::create('/user/forms/'.$form->id.'/submit', 'POST', [
            'slots' => [
                [
                    'date' => '2026-02-16',
                ],
            ],
        ]);

        $service = app(StudentSubmissionService::class);
        $response = $service->handleSubmission($request, $form->id);

        $this->assertNotNull($response);
        $this->assertTrue(
            DB::table('tbl_slots')
                ->where('form_id', $form->id)
                ->where('account_id', $user->account_id)
                ->whereDate('date', '2026-02-16')
                ->whereNull('start_time')
                ->whereNull('end_time')
                ->exists()
        );
    }

    public function test_submission_ignores_date_field_key_for_slot_based_date_forms(): void
    {
        $user = $this->createUserWithPermissions(['forms.view']);

        $form = Form::create([
            'form_name' => 'Slot Date Form',
            'form_code' => 'FORM'.uniqid(),
            'description' => 'Slot-based date form',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_date_booking',
            'label' => 'Booking Date',
            'data_type' => 'date',
            'is_required' => false,
            'use_slots' => true,
            'require_facility' => false,
            'date_mode' => 'single',
            'field_order' => 1,
        ]);

        $this->createLegacyRuntimeTable($form->fresh('fields'));

        $workflowSlotDate = Workflow::create([
            'workflow_name' => 'Slot Date Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'status' => 'Active',
            'created_by' => $user->account_id,
        ]);

        WorkflowVersion::create([
            'workflow_id' => $workflowSlotDate->id,
            'version_number' => 1,
            'steps_snapshot' => [],
            'published_at' => now(),
        ]);

        $this->actingAs($user);

        $request = Request::create('/user/forms/'.$form->id.'/submit', 'POST', [
            'field_date_booking' => '2026-02-17',
            'field_textarea_notes' => 'Sample note',
            'slots' => [
                ['date' => '2026-02-17'],
            ],
        ]);

        $service = app(StudentSubmissionService::class);
        $response = $service->handleSubmission($request, $form->id);

        $this->assertNotNull($response);

        $canonicalSubmission = FormSubmission::query()->where('form_id', $form->id)->first();

        $this->assertNotNull($canonicalSubmission);
        $this->assertEquals([
            ['date' => '2026-02-17', 'start_time' => null, 'end_time' => null, 'facility_id' => null],
        ], $canonicalSubmission->payload_json['slots'] ?? null);
    }

    public function test_staff_submission_enforces_global_submission_limit_from_canonical_rows_without_runtime_rows(): void
    {
        $user = $this->createUserWithPermissions(['forms.view']);

        $form = Form::create([
            'form_name' => 'Staff Limit Form',
            'form_code' => 'FORM'.uniqid(),
            'description' => 'Staff limit test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
            'submission_limit' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text_name',
            'label' => 'Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $this->createLegacyRuntimeTable($form->fresh('fields'));

        $existingSubmission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $user->account_id,
            'submission_status' => 'Pending',
            'current_workflow_status' => 'Pending',
            'payload_json' => ['field_text_name' => 'Existing staff canonical submission'],
            'schema_snapshot_json' => $form->fresh('fields')->toSchemaArray(),
            'submitted_at' => now()->subMinute(),
            'is_latest_revision' => true,
        ]);
        $existingSubmission->forceFill(['root_submission_id' => $existingSubmission->id])->save();

        $this->assertSame(0, DB::table('tbl_form_'.$form->id)->count());

        $this->actingAs($user);

        $request = Request::create('/staff/forms/'.$form->id.'/submit', 'POST', [
            'field_text_name' => 'Blocked by canonical staff limit',
        ]);

        $response = app(StaffSubmissionService::class)->handleSubmission($request, $form->id);

        $this->assertNotNull($response);
        $this->assertSame(1, FormSubmission::query()->where('form_id', $form->id)->count());
        $this->assertSame(0, DB::table('tbl_form_'.$form->id)->count());
    }

    public function test_staff_submission_writes_canonical_record_without_runtime_row(): void
    {
        $user = $this->createUserWithPermissions(['forms.view']);

        $form = Form::create([
            'form_name' => 'Staff Canonical Form',
            'form_code' => 'FORM'.uniqid(),
            'description' => 'Staff canonical write test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_text_name',
            'label' => 'Name',
            'data_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);

        $this->createLegacyRuntimeTable($form->fresh('fields'));

        $this->actingAs($user);

        $request = Request::create('/staff/forms/'.$form->id.'/submit', 'POST', [
            'field_text_name' => 'Canonical staff submission',
        ]);

        $response = app(StaffSubmissionService::class)->handleSubmission($request, $form->id);

        $this->assertNotNull($response);

        $canonicalSubmission = FormSubmission::query()->where('form_id', $form->id)->first();

        $this->assertNotNull($canonicalSubmission);
        $this->assertSame('Canonical staff submission', $canonicalSubmission->payload_json['field_text_name'] ?? null);
        $this->assertNotNull($canonicalSubmission->id);
        $this->assertSame(0, DB::table('tbl_form_'.$form->id)->count());
    }

    public function test_staff_submission_with_duplicate_slots_succeeds_and_deduplicates_payload(): void
    {
        $user = $this->createUserWithPermissions(['forms.view']);

        $form = Form::create([
            'form_name' => 'Staff Slot Dedup Form',
            'form_code' => 'FORM'.uniqid(),
            'description' => 'Staff duplicate slot regression test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_date_request',
            'label' => 'Requested Date',
            'data_type' => 'date',
            'is_required' => false,
            'use_slots' => true,
            'field_order' => 1,
        ]);

        $this->createLegacyRuntimeTable($form->fresh('fields'));

        $this->actingAs($user);

        $request = Request::create('/staff/forms/'.$form->id.'/submit', 'POST', [
            'slots' => [
                [
                    'date' => '2026-03-16',
                    'start_time' => '10:00',
                    'end_time' => '11:00',
                    'facility_id' => null,
                ],
                [
                    'date' => '2026-03-16',
                    'start_time' => '10:00',
                    'end_time' => '11:00',
                    'facility_id' => null,
                ],
            ],
        ]);

        $response = app(StaffSubmissionService::class)->handleSubmission($request, $form->id);

        $this->assertNotNull($response);
        $this->assertSame(302, $response->getStatusCode());

        $canonicalSubmission = FormSubmission::query()->where('form_id', $form->id)->first();

        $this->assertNotNull($canonicalSubmission);
        $this->assertCount(1, $canonicalSubmission->payload_json['slots'] ?? []);
    }

    public function test_staff_submission_accepts_required_range_dates_without_field_key(): void
    {
        $user = $this->createUserWithPermissions(['forms.view']);

        $form = Form::create([
            'form_name' => 'Staff Mixed Date Form',
            'form_code' => 'FORM'.uniqid(),
            'description' => 'Staff date range regression test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $user->account_id,
            'is_locked' => true,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_date_only',
            'label' => 'Date Only',
            'data_type' => 'date',
            'is_required' => true,
            'use_slots' => false,
            'require_facility' => false,
            'date_mode' => 'single',
            'field_order' => 1,
        ]);

        FormField::create([
            'form_id' => $form->id,
            'field_name' => 'field_date_range',
            'label' => 'Date Range',
            'data_type' => 'date',
            'is_required' => true,
            'use_slots' => false,
            'require_facility' => false,
            'date_mode' => 'range',
            'field_order' => 2,
        ]);

        $this->createLegacyRuntimeTable($form->fresh('fields'));

        $this->actingAs($user);

        $request = Request::create('/staff/forms/'.$form->id.'/submit', 'POST', [
            'field_date_only' => '2026-03-18',
            'date_ranges' => [
                [
                    'from' => '2026-03-18',
                    'to' => '2026-03-20',
                ],
            ],
            // Intentionally omit field_date_range key. Range values should come from date_ranges.
        ]);

        $response = app(StaffSubmissionService::class)->handleSubmission($request, $form->id);

        $this->assertNotNull($response);
        $this->assertSame(302, $response->getStatusCode());

        $canonicalSubmission = FormSubmission::query()->where('form_id', $form->id)->first();

        $this->assertNotNull($canonicalSubmission);
        $this->assertSame('2026-03-18', $canonicalSubmission->payload_json['field_date_only'] ?? null);
        $this->assertEquals([
            ['start_date' => '2026-03-18', 'end_date' => '2026-03-20'],
        ], $canonicalSubmission->payload_json['date_ranges'] ?? null);
    }

    private function createUserWithPermissions(array $permissionSlugs): User
    {
        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'permission_name' => ucwords(str_replace(['.', '-'], ' ', $slug)),
                    'description' => 'Test permission',
                    'resource' => explode('.', $slug)[0] ?? 'test',
                    'action' => explode('.', $slug)[1] ?? 'access',
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role = Role::create([
            'role_name' => 'Role '.uniqid(),
            'description' => 'Test role',
            'is_active' => true,
        ]);
        $role->permissions()->sync($permissionIds);

        $user = User::create([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        UserRole::create([
            'account_id' => $user->account_id,
            'role_id' => $role->id,
            'assigned_date' => now()->toDateString(),
            'is_active' => true,
            'assigned_by' => $user->account_id,
        ]);

        return $user;
    }

    private function runtimeTableName(Form $form): string
    {
        return 'tbl_form_'.$form->id;
    }

    private function createLegacyRuntimeTable(Form $form): void
    {
        $tableName = $this->runtimeTableName($form);
        $fields = $form->fields()->orderBy('field_order')->get();

        $hasAnyDate = $fields->contains(fn ($field) => FormFieldTypeRegistry::isDate((string) $field->data_type));
        $hasAnyRange = $fields->contains(fn ($field) => FormFieldTypeRegistry::isDate((string) $field->data_type) && (($field->date_mode ?? 'single') === 'range'));

        Schema::create($tableName, function (Blueprint $table) use ($fields, $hasAnyDate, $hasAnyRange) {
            $table->bigIncrements('id');
            $table->bigInteger('account_id')->nullable()->index();
            $table->bigInteger('revision_of')->nullable()->index();

            if ($hasAnyDate) {
                $table->json('slots')->nullable();
            }

            if ($hasAnyRange) {
                $table->json('date_ranges')->nullable();
            }

            foreach ($fields as $field) {
                if (! FormFieldTypeRegistry::isRuntimeColumnType((string) $field->data_type)) {
                    continue;
                }

                $column = match ((string) $field->data_type) {
                    'textarea' => $table->text($field->field_name),
                    'email' => $table->string($field->field_name, 255),
                    'phone' => $table->string($field->field_name, 32),
                    'file' => $table->string($field->field_name, 2048),
                    'number' => $table->decimal($field->field_name, 16, 2),
                    'checkbox' => $table->text($field->field_name),
                    'table' => $table->json($field->field_name),
                    default => $table->string($field->field_name, 500),
                };

                if (! $field->is_required) {
                    $column->nullable();
                }
            }

            $table->timestamps();
        });
    }
}
