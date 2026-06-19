<?php

namespace Tests\Unit;

use App\Modules\VerificationSnapshot\Jobs\GenerateVerificationSnapshot;
use Tests\TestCase;

class GenerateVerificationSnapshotJobTest extends TestCase
{
    public function test_unique_id_is_scoped_to_progress_id(): void
    {
        $job = new GenerateVerificationSnapshot(123);

        $this->assertSame('snapshot-progress-123', $job->uniqueId());
        $this->assertSame(300, $job->uniqueFor);
    }
}
