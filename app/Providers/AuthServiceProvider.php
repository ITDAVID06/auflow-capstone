<?php

namespace App\Providers;

use App\Modules\FormBuilder\Models\Form;
// Existing modules
use App\Modules\FormBuilder\Policies\FormPolicy;
use App\Modules\UserManagement\Models\Role;
// User Management policies
use App\Modules\UserManagement\Models\User as UserModel;
use App\Modules\UserManagement\Policies\RolePolicy;
use App\Modules\UserManagement\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Existing
        Form::class => FormPolicy::class,
        \App\Modules\WorkflowBuilder\Models\WorkflowStepProgress::class => \App\Policies\WorkflowStepProgressPolicy::class,

        // New: User Management
        UserModel::class => UserPolicy::class,
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define Gates here if needed later.
    }
}
