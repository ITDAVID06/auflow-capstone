<?php

namespace Tests\Feature;

use App\Modules\AdminSubmissions\Services\AdminSubmissionsQueryService;
use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSubmissionsMetricsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_get_user_metrics_returns_correct_counts(): void
    {
        $accountId = $this->createUser()->account_id;

        $this->createProgressForUser($accountId, 'Pending');
        $this->createProgressForUser($accountId, 'Approved');
        $this->createProgressForUser($accountId, 'Approved');
        $this->createProgressForUser($accountId, 'Rejected');

        /** @var AdminSubmissionsQueryService $service */
        $service = $this->app->make(AdminSubmissionsQueryService::class);
        $metrics = $service->getUserMetrics($accountId);

        $this->assertArrayHasKey('total', $metrics);
        $this->assertArrayHasKey('pending', $metrics);
        $this->assertArrayHasKey('approved', $metrics);
        $this->assertArrayHasKey('rejected', $metrics);
        $this->assertSame(4, $metrics['total']);
        $this->assertSame(1, $metrics['pending']);
        $this->assertSame(2, $metrics['approved']);
        $this->assertSame(1, $metrics['rejected']);
    }

    public function test_get_user_metrics_stores_result_in_cache(): void
    {
        $accountId = $this->createUser()->account_id;
        $this->createProgressForUser($accountId, 'Pending');

        $cacheKey = "auflow:dashboard:metrics:user:{$accountId}";
        $this->assertFalse(Cache::has($cacheKey), 'Cache key must not exist before first call');

        /** @var AdminSubmissionsQueryService $service */
        $service = $this->app->make(AdminSubmissionsQueryService::class);
        $service->getUserMetrics($accountId);

        $this->assertTrue(Cache::has($cacheKey), 'Cache key must be populated after first call');
    }

    public function test_get_user_metrics_serves_subsequent_calls_from_cache(): void
    {
        $accountId = $this->createUser()->account_id;
        $this->createProgressForUser($accountId, 'Approved');

        /** @var AdminSubmissionsQueryService $service */
        $service = $this->app->make(AdminSubmissionsQueryService::class);
        $first = $service->getUserMetrics($accountId);

        // Insert another record after cache is warm — should not be reflected
        $this->createProgressForUser($accountId, 'Rejected');

        DB::enableQueryLog();
        $second = $service->getUserMetrics($accountId);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($first['total'], $second['total'], 'Cached value should be returned unchanged');
        $this->assertEmpty($queries, 'No DB queries should fire on a cache hit');
    }

    public function test_get_user_metrics_isolates_data_per_account_id(): void
    {
        $accountId = $this->createUser()->account_id;
        $otherId = $this->createUser()->account_id;

        $this->createProgressForUser($accountId, 'Approved');
        $this->createProgressForUser($otherId, 'Approved');
        $this->createProgressForUser($otherId, 'Pending');

        /** @var AdminSubmissionsQueryService $service */
        $service = $this->app->make(AdminSubmissionsQueryService::class);
        $metrics = $service->getUserMetrics($accountId);

        $this->assertSame(1, $metrics['total'], 'Must only count rows for the given account');
        $this->assertSame(1, $metrics['approved']);
        $this->assertSame(0, $metrics['pending']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createUser(): User
    {
        return User::create([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
    }

    private function createProgressForUser(int $accountId, string $status): WorkflowStepProgress
    {
        $form = Form::create([
            'form_name' => 'User Metrics Form '.uniqid(),
            'form_code' => 'UMF-'.uniqid(),
            'description' => 'Test',
            'form_category_id' => null,
            'version' => 1,
            'status' => 'Active',
            'created_by' => $accountId,
            'email_notifications' => false,
            'submission_limit' => null,
            'is_locked' => true,
            'draft_data' => null,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'User Metrics Workflow '.uniqid(),
            'workflow_type' => 'Sequential',
            'version' => 1,
            'effective_from' => now(),
            'effective_to' => null,
            'form_id' => $form->id,
            'description' => 'Test',
            'workflow_settings' => null,
            'status' => 'Active',
            'created_by' => $accountId,
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Review',
            'step_description' => 'Review',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $accountId,
            'max_duration_hours' => null,
            'step_conditions' => null,
            'if_rejected_id' => null,
        ]);

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $accountId,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'current_step_id' => $step->id,
            'current_actor_id' => $accountId,
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
            'actor_id' => $accountId,
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
