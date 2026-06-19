<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\StaffDashboard\Services\StaffDashboardQueryService;
use App\Modules\UserManagement\Models\Permission;
use App\Modules\UserManagement\Models\Role;
use App\Modules\UserManagement\Models\User;
use App\Modules\UserManagement\Models\UserRole;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffDashboardMetricsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_get_metrics_for_staff_returns_correct_counts(): void
    {
        $staffId = $this->createStaffUser()->account_id;

        $this->createProgressForStaff($staffId, 'Pending');
        $this->createProgressForStaff($staffId, 'Waiting');
        $this->createProgressForStaff($staffId, 'Approved');
        $this->createProgressForStaff($staffId, 'Rejected');

        /** @var StaffDashboardQueryService $service */
        $service = $this->app->make(StaffDashboardQueryService::class);
        $metrics = $service->getMetricsForStaff($staffId);

        $this->assertArrayHasKey('total', $metrics);
        $this->assertArrayHasKey('pending', $metrics);
        $this->assertArrayHasKey('approved', $metrics);
        $this->assertArrayHasKey('rejected', $metrics);
        $this->assertSame(4, $metrics['total']);
        $this->assertSame(2, $metrics['pending']); // Pending + Waiting
        $this->assertSame(1, $metrics['approved']);
        $this->assertSame(1, $metrics['rejected']);
    }

    public function test_get_metrics_for_staff_stores_result_in_cache(): void
    {
        $staffId = $this->createStaffUser()->account_id;
        $this->createProgressForStaff($staffId, 'Approved');

        $cacheKey = "auflow:dashboard:metrics:staff:{$staffId}";
        $this->assertFalse(Cache::has($cacheKey), 'Cache key must not exist before first call');

        /** @var StaffDashboardQueryService $service */
        $service = $this->app->make(StaffDashboardQueryService::class);
        $service->getMetricsForStaff($staffId);

        $this->assertTrue(Cache::has($cacheKey), 'Cache key must be populated after first call');
    }

    public function test_get_metrics_for_staff_serves_subsequent_calls_from_cache(): void
    {
        $staffId = $this->createStaffUser()->account_id;
        $this->createProgressForStaff($staffId, 'Approved');

        /** @var StaffDashboardQueryService $service */
        $service = $this->app->make(StaffDashboardQueryService::class);
        $first = $service->getMetricsForStaff($staffId);

        // Insert another record after cache is warm — should not be reflected
        $this->createProgressForStaff($staffId, 'Rejected');

        DB::enableQueryLog();
        $second = $service->getMetricsForStaff($staffId);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($first['total'], $second['total'], 'Cached value should be returned unchanged');
        $this->assertEmpty($queries, 'No DB queries should fire on a cache hit');
    }

    public function test_get_metrics_for_staff_isolates_data_per_staff_id(): void
    {
        $staffId = $this->createStaffUser()->account_id;
        $otherId = $this->createStaffUser()->account_id;

        $this->createProgressForStaff($staffId, 'Approved');
        $this->createProgressForStaff($otherId, 'Approved');
        $this->createProgressForStaff($otherId, 'Pending');

        /** @var StaffDashboardQueryService $service */
        $service = $this->app->make(StaffDashboardQueryService::class);
        $metrics = $service->getMetricsForStaff($staffId);

        $this->assertSame(1, $metrics['total'], 'Must only count rows for the given staff');
        $this->assertSame(1, $metrics['approved']);
        $this->assertSame(0, $metrics['pending']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createStaffUser(): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'dashboard.staff'],
            [
                'permission_name' => 'Dashboard Staff',
                'description' => 'Staff dashboard access',
                'resource' => 'dashboard',
                'action' => 'staff',
            ]
        );

        $role = Role::create([
            'role_name' => 'Staff '.uniqid(),
            'description' => 'Staff role',
            'is_active' => true,
        ]);
        $role->permissions()->sync([$permission->id]);

        $user = User::create([
            'username' => 'staff_'.uniqid(),
            'email' => 'staff_'.uniqid().'@test.com',
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

    private function createProgressForStaff(int $staffId, string $status): WorkflowStepProgress
    {
        $form = Form::create([
            'form_name' => 'Metrics Form '.uniqid(),
            'form_code' => 'MF-'.uniqid(),
            'description' => 'Test',
            'form_category_id' => null,
            'version' => 1,
            'status' => 'Active',
            'created_by' => $staffId,
            'email_notifications' => false,
            'submission_limit' => null,
            'is_locked' => true,
            'draft_data' => null,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Metrics Workflow '.uniqid(),
            'workflow_type' => 'Sequential',
            'version' => 1,
            'effective_from' => now(),
            'effective_to' => null,
            'form_id' => $form->id,
            'description' => 'Test',
            'workflow_settings' => null,
            'status' => 'Active',
            'created_by' => $staffId,
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Review',
            'step_description' => 'Review',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $staffId,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $staffId,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'current_step_id' => $step->id,
            'current_actor_id' => $staffId,
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return WorkflowStepProgress::create([
            'form_id' => $form->id,
            'submission_id' => $submission->id,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'step_id' => $step->id,
            'actor_id' => $staffId,
            'action_taken' => null,
            'comments' => null,
            'acted_at' => null,
            'status' => $status,
            'started_at' => now(),
            'completed_at' => null,
            'duration_seconds' => null,
            'reminder_count' => 0,
            'last_reminder_at' => null,
        ]);
    }
}
