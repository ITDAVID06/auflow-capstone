<?php

namespace Tests\Feature;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\Notifications\Models\Notification;
use App\Modules\UserManagement\Models\User;
use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepApprover;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowApproverInAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tbl_user_status')->insertOrIgnore([
            ['id' => 1, 'status_name' => 'Active', 'description' => 'Active user', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_notify_approver_creates_in_app_notifications_for_multi_approvers(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-inapp', 'creator-inapp@test.com');
        $approverOne = $this->createUser('inapp-1', 'inapp-1@test.com');
        $approverTwo = $this->createUser('inapp-2', 'inapp-2@test.com');

        $form = Form::create([
            'form_name' => 'In-App Form',
            'form_code' => 'IN_APP_FORM',
            'description' => 'In-app workflow notification test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'In-App Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Approval Gate',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => null,
            'step_conditions' => ['watch_fields' => []],
        ]);

        WorkflowStepApprover::create([
            'step_id' => $step->id,
            'account_id' => $approverOne->account_id,
            'condition' => 'primary',
            'order' => 0,
        ]);

        WorkflowStepApprover::create([
            'step_id' => $step->id,
            'account_id' => $approverTwo->account_id,
            'condition' => 'or',
            'order' => 1,
        ]);

        app(NotificationService::class)->notifyApprover($form, $step, 7001);

        $notifications = Notification::query()
            ->where('type', 'workflow_pending_approval')
            ->where('related_type', 'workflow_step')
            ->where('related_id', $step->id)
            ->pluck('account_id')
            ->all();

        sort($notifications);
        $expected = [$approverOne->account_id, $approverTwo->account_id];
        sort($expected);

        $this->assertSame($expected, $notifications);
    }

    public function test_notify_approver_deduplicates_recent_in_app_notifications(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-inapp-dup', 'creator-inapp-dup@test.com');
        $approverOne = $this->createUser('inapp-dup-1', 'inapp-dup-1@test.com');
        $approverTwo = $this->createUser('inapp-dup-2', 'inapp-dup-2@test.com');

        $form = Form::create([
            'form_name' => 'In-App Dedupe Form',
            'form_code' => 'IN_APP_DEDUPE_FORM',
            'description' => 'In-app dedupe test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'In-App Dedupe Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Approval Gate',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => null,
            'step_conditions' => ['watch_fields' => []],
        ]);

        WorkflowStepApprover::create([
            'step_id' => $step->id,
            'account_id' => $approverOne->account_id,
            'condition' => 'primary',
            'order' => 0,
        ]);

        WorkflowStepApprover::create([
            'step_id' => $step->id,
            'account_id' => $approverTwo->account_id,
            'condition' => 'or',
            'order' => 1,
        ]);

        $service = app(NotificationService::class);
        $service->notifyApprover($form, $step, 7002);
        $service->notifyApprover($form, $step, 7002);

        $counts = Notification::query()
            ->where('type', 'workflow_pending_approval')
            ->where('related_type', 'workflow_step')
            ->where('related_id', $step->id)
            ->selectRaw('account_id, COUNT(*) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        $this->assertSame(1, (int) ($counts[$approverOne->account_id] ?? 0));
        $this->assertSame(1, (int) ($counts[$approverTwo->account_id] ?? 0));
    }

    public function test_notify_approver_deduplicates_via_database_idempotency_key(): void
    {
        Mail::fake();

        $creator = $this->createUser('creator-inapp-db', 'creator-inapp-db@test.com');
        $approver = $this->createUser('inapp-db-1', 'inapp-db-1@test.com');

        $form = Form::create([
            'form_name' => 'In-App DB Dedupe Form',
            'form_code' => 'IN_APP_DB_DEDUPE_FORM',
            'description' => 'In-app db dedupe test',
            'version' => 1,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'email_notifications' => true,
        ]);

        $workflow = Workflow::create([
            'workflow_name' => 'In-App DB Dedupe Workflow',
            'workflow_type' => 'Sequential',
            'form_id' => $form->id,
            'description' => null,
            'status' => 'Active',
            'created_by' => $creator->account_id,
            'workflow_settings' => ['nodes' => [], 'edges' => []],
        ]);

        $step = WorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_name' => 'Approval Gate',
            'step_order' => 1,
            'step_group' => 1,
            'action_type' => 'Approve',
            'assigned_account_id' => $approver->account_id,
            'step_conditions' => ['watch_fields' => []],
        ]);

        Notification::create([
            'account_id' => $approver->account_id,
            'type' => 'workflow_pending_approval',
            'title' => 'New Approval Request',
            'message' => "A {$form->form_name} request is awaiting your approval at step: {$step->step_name}",
            'action_url' => route('staff-dashboard.index'),
            'action_text' => 'Review Request',
            'related_type' => 'workflow_step',
            'related_id' => $step->id,
            'icon' => 'bell',
            'priority' => 'high',
            'is_read' => false,
            'idempotency_key' => "workflow_pending_approval:7003:{$step->id}:{$approver->account_id}",
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        app(NotificationService::class)->notifyApprover($form, $step, 7003);

        $count = Notification::query()
            ->where('type', 'workflow_pending_approval')
            ->where('related_type', 'workflow_step')
            ->where('related_id', $step->id)
            ->where('account_id', $approver->account_id)
            ->count();

        $this->assertSame(1, $count);
    }

    private function createUser(string $username, string $email): User
    {
        return User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password'),
            'user_status_id' => 1,
        ]);
    }
}
