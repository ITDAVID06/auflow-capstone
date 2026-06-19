<?php

namespace Tests\Unit;

use App\Modules\StudentDashboard\Services\StudentSubmissionService;
use App\Services\NotificationService;
use Tests\TestCase;

class StudentSubmissionServiceJsonNormalizationTest extends TestCase
{
    public function test_normalize_json_field_unwraps_single_string_array_payload(): void
    {
        $service = new StudentSubmissionService($this->createMock(NotificationService::class));

        $method = new \ReflectionMethod(StudentSubmissionService::class, 'normalizeJsonField');
        $method->setAccessible(true);

        $wrappedPayload = ['[{"value":"chairs","qty":2},{"value":"tables","qty":2},{"value":"others","text":""}]'];

        $normalized = $method->invoke($service, $wrappedPayload, 'field_items');

        $this->assertSame(
            '[{"value":"chairs","qty":2},{"value":"tables","qty":2},{"value":"others","text":""}]',
            $normalized
        );
    }

    public function test_normalize_slots_input_deduplicates_identical_slots(): void
    {
        $service = new StudentSubmissionService($this->createMock(NotificationService::class));

        $method = new \ReflectionMethod(StudentSubmissionService::class, 'normalizeSlotsInput');
        $method->setAccessible(true);

        $rawSlots = [
            [
                'date' => '2026-03-15',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'facility_id' => 5,
            ],
            [
                'date' => '2026-03-15',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'facility_id' => 5,
            ],
        ];

        /** @var array<int, array{date:?string,start_time:?string,end_time:?string,facility_id:?int}> $normalized */
        $normalized = $method->invoke($service, $rawSlots);

        $this->assertCount(1, $normalized, 'Duplicate slot rows should collapse into a single normalized slot.');
    }
}
