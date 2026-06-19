<?php

namespace Tests\Unit;

use App\Modules\StaffDashboard\Services\StaffDashboardQueryService;
use App\Modules\StaffDashboard\Services\StaffSubmissionDetailsService;
use App\Modules\StaffDashboard\Services\StaffSubmissionService;
use App\Modules\VerificationSnapshot\Services\SnapshotService;
use App\Services\NotificationService;
use Tests\TestCase;

class StaffSubmissionServiceJsonNormalizationTest extends TestCase
{
    public function test_normalize_json_field_unwraps_single_string_array_payload(): void
    {
        $service = new StaffSubmissionService(
            $this->createMock(SnapshotService::class),
            $this->createMock(NotificationService::class),
            $this->createMock(StaffDashboardQueryService::class),
            $this->createMock(StaffSubmissionDetailsService::class)
        );

        $method = new \ReflectionMethod(StaffSubmissionService::class, 'normalizeJsonField');
        $method->setAccessible(true);

        $wrappedPayload = ['[{"value":"chairs","qty":3},{"value":"tables","qty":4}]'];

        $normalized = $method->invoke($service, $wrappedPayload, 'field_checkbox_items');

        $this->assertSame(
            '[{"value":"chairs","qty":3},{"value":"tables","qty":4}]',
            $normalized
        );
    }

    public function test_normalize_slots_input_deduplicates_identical_slots(): void
    {
        $service = new StaffSubmissionService(
            $this->createMock(SnapshotService::class),
            $this->createMock(NotificationService::class),
            $this->createMock(StaffDashboardQueryService::class),
            $this->createMock(StaffSubmissionDetailsService::class)
        );

        $method = new \ReflectionMethod(StaffSubmissionService::class, 'normalizeSlotsInput');
        $method->setAccessible(true);

        $rawSlots = [
            [
                'date' => '2026-03-16',
                'start_time' => '08:30',
                'end_time' => '09:30',
                'facility_id' => 7,
            ],
            [
                'date' => '2026-03-16',
                'start_time' => '08:30',
                'end_time' => '09:30',
                'facility_id' => 7,
            ],
        ];

        /** @var array<int, array{date:?string,start_time:?string,end_time:?string,facility_id:?int}> $normalized */
        $normalized = $method->invoke($service, $rawSlots);

        $this->assertCount(1, $normalized, 'Duplicate slot rows should collapse into a single normalized slot.');
    }
}
