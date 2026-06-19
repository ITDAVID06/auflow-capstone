<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowStateTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_archive_workflow_executes_writes_inside_db_transaction(): void
    {
        [$workflow] = $this->createActiveWorkflowWithForm();

        $capturedLevel = null;
        Workflow::saving(function () use (&$capturedLevel) {
            $capturedLevel = DB::transactionLevel();
        });

        app(WorkflowService::class)->archiveWorkflow($workflow->id);

        $this->assertNotNull($capturedLevel, 'Workflow::saving did not fire');
        $this->assertGreaterThan(
            1,
            $capturedLevel,
            'archiveWorkflow must wrap its writes inside DB::transaction()'
        );
    }

    public function test_draft_workflow_executes_writes_inside_db_transaction(): void
    {
        [$workflow] = $this->createActiveWorkflowWithForm();

        $capturedLevel = null;
        Workflow::saving(function () use (&$capturedLevel) {
            $capturedLevel = DB::transactionLevel();
        });

        app(WorkflowService::class)->draftWorkflow($workflow->id, force: true);

        $this->assertNotNull($capturedLevel, 'Workflow::saving did not fire');
        $this->assertGreaterThan(
            1,
            $capturedLevel,
            'draftWorkflow must wrap its writes inside DB::transaction()'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createActiveWorkflowWithForm(): array
    {
        $creator = User::create([
            'username' => 'tx_creator_'.uniqid(),
            'email' => 'tx_'.uniqid().'@test.com',
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);

        $form = Form::create([
            'form_name' => 'Tx Test Form '.uniqid(),
            'form_code' => 'TX-'.uniqid(),
            'status' => 'Active',
            'version' => 1,
            'is_locked' => true,
            'created_by' => $creator->account_id,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'Tx Test Workflow '.uniqid(),
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        return [$workflow, $form, $creator];
    }
}
