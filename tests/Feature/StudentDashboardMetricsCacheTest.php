<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use App\Modules\UserManagement\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentDashboardMetricsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_get_submission_metrics_returns_correct_counts(): void
    {
        $accountId = $this->createUser()->account_id;

        $this->createSubmission($accountId, 'Pending');
        $this->createSubmission($accountId, 'Pending');
        $this->createSubmission($accountId, 'Approved');
        $this->createSubmission($accountId, 'Rejected');

        /** @var StudentSubmissionService $service */
        $service = $this->app->make(StudentSubmissionService::class);
        $metrics = $service->getSubmissionMetrics($accountId);

        $this->assertArrayHasKey('total', $metrics);
        $this->assertArrayHasKey('pending', $metrics);
        $this->assertArrayHasKey('approved', $metrics);
        $this->assertArrayHasKey('rejected', $metrics);
        $this->assertArrayHasKey('revision', $metrics);
        $this->assertSame(4, $metrics['total']);
        $this->assertSame(2, $metrics['pending']);
        $this->assertSame(1, $metrics['approved']);
        $this->assertSame(1, $metrics['rejected']);
    }

    public function test_get_submission_metrics_stores_result_in_cache(): void
    {
        $accountId = $this->createUser()->account_id;
        $this->createSubmission($accountId, 'Approved');

        $cacheKey = "auflow:dashboard:metrics:student:{$accountId}";
        $this->assertFalse(Cache::has($cacheKey), 'Cache key must not exist before first call');

        /** @var StudentSubmissionService $service */
        $service = $this->app->make(StudentSubmissionService::class);
        $service->getSubmissionMetrics($accountId);

        $this->assertTrue(Cache::has($cacheKey), 'Cache key must be populated after first call');
    }

    public function test_get_submission_metrics_serves_subsequent_calls_from_cache(): void
    {
        $accountId = $this->createUser()->account_id;
        $this->createSubmission($accountId, 'Approved');

        /** @var StudentSubmissionService $service */
        $service = $this->app->make(StudentSubmissionService::class);
        $first = $service->getSubmissionMetrics($accountId);

        // Insert another submission after cache is warm — should not be reflected
        $this->createSubmission($accountId, 'Rejected');

        DB::enableQueryLog();
        $second = $service->getSubmissionMetrics($accountId);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($first['total'], $second['total'], 'Cached value should be returned unchanged');
        $this->assertEmpty($queries, 'No DB queries should fire on a cache hit');
    }

    public function test_get_submission_metrics_isolates_data_per_account_id(): void
    {
        $accountId = $this->createUser()->account_id;
        $otherId = $this->createUser()->account_id;

        $this->createSubmission($accountId, 'Approved');
        $this->createSubmission($otherId, 'Approved');
        $this->createSubmission($otherId, 'Pending');

        /** @var StudentSubmissionService $service */
        $service = $this->app->make(StudentSubmissionService::class);
        $metrics = $service->getSubmissionMetrics($accountId);

        $this->assertSame(1, $metrics['total'], 'Must only count submissions for the given account');
        $this->assertSame(1, $metrics['approved']);
        $this->assertSame(0, $metrics['pending']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function createUser(): User
    {
        return User::create([
            'username' => 'student_'.uniqid(),
            'email' => 'student_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
    }

    private function createSubmission(int $accountId, string $status): FormSubmission
    {
        $form = Form::create([
            'form_name' => 'Student Metrics Form '.uniqid(),
            'form_code' => 'SMF-'.uniqid(),
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

        $submission = FormSubmission::query()->create([
            'form_id' => $form->id,
            'account_id' => $accountId,
            'submission_status' => $status,
            'current_workflow_status' => $status,
            'current_step_id' => null,
            'current_actor_id' => null,
            'payload_json' => [],
            'schema_snapshot_json' => [],
            'submitted_at' => now(),
            'is_latest_revision' => true,
        ]);
        $submission->forceFill(['root_submission_id' => $submission->id])->save();

        return $submission;
    }
}
