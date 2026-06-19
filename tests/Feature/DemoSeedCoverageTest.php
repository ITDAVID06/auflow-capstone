<?php

namespace Tests\Feature;

use App\Modules\StaffDashboard\Services\StaffDashboardQueryService;
use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoSeedCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_demo_with_edge_populates_required_demo_states(): void
    {
        $this->artisan('seed:demo --profile=quick --with-edge --deterministic-only')
            ->assertExitCode(0);

        $workflows = DB::table('tbl_workflow')->get(['workflow_settings']);
        $this->assertGreaterThan(0, $workflows->count());

        foreach ($workflows as $workflow) {
            $settings = json_decode((string) $workflow->workflow_settings, true) ?: [];
            $nodes = $settings['nodes'] ?? [];
            $edges = $settings['edges'] ?? [];

            $this->assertNotEmpty($nodes, 'Seeded workflow is missing canvas nodes used by preview.');
            $this->assertNotEmpty($edges, 'Seeded workflow is missing canvas edges used by preview.');

            $hasStartNode = collect($nodes)->contains(function ($node) {
                $id = (string) ($node['id'] ?? '');
                $type = (string) ($node['type'] ?? '');
                $dataType = (string) ($node['data']['type'] ?? '');

                return $id === 'start' || $type === 'start' || $dataType === 'form_submitted';
            });

            $this->assertTrue($hasStartNode, 'Seeded workflow preview requires a start node.');
        }

        $this->assertGreaterThan(0, DB::table('tbl_form')->count());
        $this->assertGreaterThan(0, DB::table('tbl_workflow')->count());
        $this->assertGreaterThan(0, DB::table('tbl_form_submission')->count());

        $fieldSignaturesByForm = DB::table('tbl_formfield')
            ->orderBy('form_id')
            ->orderBy('field_order')
            ->get(['form_id', 'field_name', 'data_type'])
            ->groupBy('form_id')
            ->map(function ($fields): string {
                return $fields
                    ->map(fn ($field): string => $field->field_name.':'.$field->data_type)
                    ->implode('|');
            })
            ->unique()
            ->values();

        $this->assertGreaterThan(
            2,
            $fieldSignaturesByForm->count(),
            'Seeded demo forms should include multiple field configurations.'
        );

        $conditionalFieldCount = DB::table('tbl_formfield')
            ->whereNotNull('conditions')
            ->where('conditions', '!=', 'null')
            ->count();

        $this->assertGreaterThan(
            0,
            $conditionalFieldCount,
            'Seeded demo forms should include conditional field configurations.'
        );

        $fieldMetadataCount = DB::table('tbl_formfield')
            ->whereNotNull('options_meta')
            ->where('options_meta', '!=', 'null')
            ->count();

        $this->assertGreaterThan(
            0,
            $fieldMetadataCount,
            'Seeded demo forms should include fields with options metadata for richer UI configuration testing.'
        );

        $workflowsWithBranching = DB::table('tbl_workflow')
            ->whereRaw('LOWER(workflow_type) = ?', ['parallel'])
            ->get(['workflow_settings'])
            ->filter(function ($workflow): bool {
                $settings = json_decode((string) $workflow->workflow_settings, true) ?: [];
                $edges = collect($settings['edges'] ?? []);

                if ($edges->isEmpty()) {
                    return false;
                }

                $outgoingCounts = $edges
                    ->groupBy(fn (array $edge): string => (string) ($edge['source'] ?? ''))
                    ->map(fn ($group): int => $group->count());

                return $outgoingCounts->contains(fn (int $count): bool => $count > 1);
            });

        $this->assertGreaterThan(
            0,
            $workflowsWithBranching->count(),
            'Seeded workflows should include at least one branching (parallel) configuration.'
        );

        $workflowSettingsWithRules = DB::table('tbl_workflow')
            ->get(['workflow_settings'])
            ->filter(function ($workflow): bool {
                $settings = json_decode((string) $workflow->workflow_settings, true) ?: [];

                return ! empty($settings['routing']) || ! empty($settings['notifications']) || ! empty($settings['service_level']);
            });

        $this->assertGreaterThan(
            0,
            $workflowSettingsWithRules->count(),
            'Seeded workflows should include richer routing/notification/SLA settings for contract coverage.'
        );

        $stepsWithOrApprovers = DB::table('tbl_workflow_step_approvers')
            ->where('condition', 'or')
            ->count();

        $this->assertGreaterThan(
            0,
            $stepsWithOrApprovers,
            'Seeded workflows should include at least one step with OR approver configuration.'
        );

        $staffOneId = (int) DB::table('tbl_user')
            ->where('email', 'staff1@auf.test')
            ->value('account_id');

        $this->assertGreaterThan(0, $staffOneId, 'Expected demo staff1 account to exist.');

        $myRequests = app(StudentSubmissionService::class)
            ->getPaginatedSubmissionSummaries(
                accountId: $staffOneId,
                status: 'all',
                search: '',
                page: 1,
                perPage: 10,
            );

        $this->assertGreaterThanOrEqual(
            2,
            (int) ($myRequests['meta']['total'] ?? 0),
            'Staff My Requests should include a couple of seeded submissions.'
        );

        $pendingRequests = app(StaffDashboardQueryService::class)
            ->getPendingRequestsForStaff($staffOneId);

        $this->assertNotEmpty(
            $pendingRequests,
            'Pending approvals for staff1 should include at least one submission in demo data.'
        );

        $this->assertGreaterThanOrEqual(
            3,
            count($pendingRequests),
            'Pending approvals should include a few submissions for staff1 in demo data.'
        );

        foreach ($pendingRequests as $request) {
            $this->assertArrayHasKey('submission_id', $request);
            $this->assertNotEmpty(
                $request['submission_id'],
                'Pending approval rows must include a submission identifier for staff dashboards.'
            );
        }

        $staffRoleId = (int) DB::table('tbl_role')
            ->where('role_name', 'Staff')
            ->value('id');

        $studentDashboardPermissionId = (int) DB::table('tbl_permission')
            ->where('slug', 'dashboard.student')
            ->value('id');

        $staffDashboardPermissionId = (int) DB::table('tbl_permission')
            ->where('slug', 'dashboard.staff')
            ->value('id');

        $this->assertGreaterThan(0, $staffRoleId, 'Staff role should exist after baseline seeding.');
        $this->assertGreaterThan(0, $studentDashboardPermissionId, 'Student dashboard permission should exist.');
        $this->assertGreaterThan(0, $staffDashboardPermissionId, 'Staff dashboard permission should exist.');

        $this->assertDatabaseMissing('tbl_role_permission', [
            'role_id' => $staffRoleId,
            'permission_id' => $studentDashboardPermissionId,
        ]);

        $this->assertDatabaseHas('tbl_role_permission', [
            'role_id' => $staffRoleId,
            'permission_id' => $staffDashboardPermissionId,
        ]);

        $workflowStatuses = DB::table('tbl_form_submission')
            ->selectRaw('LOWER(current_workflow_status) as status')
            ->pluck('status')
            ->all();

        $this->assertContains('approved', $workflowStatuses);
        $this->assertContains('rejected', $workflowStatuses);
        $this->assertContains('pending', $workflowStatuses);

        $progressStatuses = DB::table('tbl_workflow_step_progress')
            ->selectRaw('status')
            ->distinct()
            ->pluck('status')
            ->all();

        $this->assertContains('Approved', $progressStatuses);
        $this->assertContains('Rejected', $progressStatuses);
        $this->assertContains('Pending', $progressStatuses);
        $this->assertContains('Waiting', $progressStatuses);
        $this->assertContains('Skipped', $progressStatuses);

        $this->assertGreaterThan(0, DB::table('tbl_snapshot')->count());
        $this->assertGreaterThan(0, DB::table('tbl_snapshot')->where('status', 'Rejected')->count());

        $approvedSnapshot = DB::table('tbl_snapshot')
            ->where('status', 'Approved')
            ->orderBy('id')
            ->first(['submission_id', 'payload_json']);

        $this->assertNotNull($approvedSnapshot, 'Expected at least one approved snapshot from demo seeding.');

        $snapshotPayload = json_decode((string) $approvedSnapshot->payload_json, true) ?: [];
        $this->assertArrayHasKey('form', $snapshotPayload);
        $this->assertArrayHasKey('submission', $snapshotPayload);
        $this->assertArrayHasKey('fields', $snapshotPayload);
        $this->assertNotEmpty($snapshotPayload['submission']['created_at'] ?? null, 'Snapshot should include submitted timestamp.');
        $this->assertNotEmpty($snapshotPayload['fields'] ?? [], 'Snapshot should include mapped submission field values.');

        $canonicalSubmission = DB::table('tbl_form_submission')
            ->where('id', (int) $approvedSnapshot->submission_id)
            ->first(['payload_json']);

        $this->assertNotNull($canonicalSubmission);

        $submissionPayload = json_decode((string) $canonicalSubmission->payload_json, true) ?: [];

        $reasonField = collect($snapshotPayload['fields'] ?? [])->first(function (array $field): bool {
            return ($field['name'] ?? null) === 'request_reason';
        });

        $this->assertNotNull($reasonField, 'Snapshot payload should include request_reason field.');
        $this->assertSame($submissionPayload['request_reason'] ?? null, $reasonField['value'] ?? null);

        $notificationTypes = DB::table('tbl_notification')
            ->select('type')
            ->distinct()
            ->pluck('type')
            ->all();

        $this->assertContains('workflow_pending_approval', $notificationTypes);
        $this->assertContains('submission_approved', $notificationTypes);
        $this->assertContains('submission_rejected', $notificationTypes);

        $this->assertGreaterThan(0, DB::table('tbl_audit_log')->count());
        $this->assertGreaterThan(0, DB::table('tbl_audit_log')->where('action', 'override_approval')->count());
    }
}
