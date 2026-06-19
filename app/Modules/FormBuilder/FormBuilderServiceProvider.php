<?php

namespace App\Modules\FormBuilder;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormField;
use App\Modules\FormBuilder\Observers\FormFieldObserver;
use App\Modules\FormBuilder\Observers\FormObserver;
use Illuminate\Support\ServiceProvider;

/**
 * FormBuilder Module Service Provider
 *
 * Manages dynamic form creation, field definitions, categories,
 * facilities, and calendar/slot booking.
 *
 * @dependencies
 *  - AuditTrail: AuditLogger service for lifecycle audit logging
 *  - WorkflowBuilder: Forms are linked to workflows via tbl_form.workflow_id
 *  - UserManagement: Form access controlled by RBAC permissions
 */
class FormBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Form::observe(FormObserver::class);
        FormField::observe(FormFieldObserver::class);
    }
}
