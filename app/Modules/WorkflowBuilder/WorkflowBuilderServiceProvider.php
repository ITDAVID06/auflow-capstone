<?php

namespace App\Modules\WorkflowBuilder;

use App\Modules\WorkflowBuilder\Models\Workflow;
use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Modules\WorkflowBuilder\Observers\WorkflowObserver;
use App\Modules\WorkflowBuilder\Observers\WorkflowStepObserver;
use App\Modules\WorkflowBuilder\Observers\WorkflowStepProgressObserver;
use Illuminate\Support\ServiceProvider;

/**
 * WorkflowBuilder Module Service Provider
 *
 * Manages multi-step approval workflows, visual canvas editor,
 * workflow steps, and step progress tracking.
 *
 * @dependencies
 *  - AuditTrail: AuditLogger service for lifecycle audit logging
 *  - Notifications: NotificationService for approver/submitter notifications
 *    (used in WorkflowStepProgressObserver)
 *  - FormBuilder: Workflows are attached to forms; progress reads
 *    dynamic submission tables (tbl_form_{id})
 *  - UserManagement: Approver assignments reference tbl_user accounts;
 *    RBAC permissions control workflow management access
 *  - StudentDashboard: Notification action URLs route to student submission views
 *  - StaffDashboard: Notification action URLs route to staff review views
 */
class WorkflowBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Workflow::observe(WorkflowObserver::class);
        WorkflowStep::observe(WorkflowStepObserver::class);
        WorkflowStepProgress::observe(WorkflowStepProgressObserver::class);
    }
}
