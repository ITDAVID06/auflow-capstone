<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\WorkflowBuilder\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_demo_command_runs_with_quick_profile_and_deterministic_only_flag(): void
    {
        $this->artisan('seed:demo --profile=quick --deterministic-only')
            ->assertExitCode(0);
    }

    public function test_seed_demo_command_creates_demo_forms_workflows_and_submissions(): void
    {
        $this->artisan('seed:demo --profile=quick --deterministic-only')
            ->assertExitCode(0);

        $this->assertGreaterThan(0, Form::query()->count());
        $this->assertGreaterThan(0, Workflow::query()->count());
        $this->assertGreaterThan(0, FormSubmission::query()->count());
    }

    public function test_seed_demo_medium_profile_generates_target_submission_volume_with_manifest_output(): void
    {
        $this->artisan('seed:demo --profile=medium --with-edge --count-submissions=260')
            ->expectsOutputToContain('Demo seeding completed.')
            ->expectsOutputToContain('Scenario Distribution')
            ->assertExitCode(0);

        $this->assertSame(260, FormSubmission::query()->count());
    }
}
