<?php

namespace Tests\Unit;

use App\Modules\AdminSubmissions\Services\AdminSubmissionsQueryService;
use App\Modules\FormBuilder\Models\Form;
use Tests\TestCase;

class AdminSubmissionsServiceTest extends TestCase
{
    public function test_runtime_table_exists_uses_schema_check(): void
    {
        // AdminSubmissionsQueryService no longer contains a runtimeTableExists() method.
        // The per-form dynamic table lookup has been removed from this service.
        $this->markTestSkipped('runtimeTableExists() has been removed from AdminSubmissionsQueryService.');
    }
}
