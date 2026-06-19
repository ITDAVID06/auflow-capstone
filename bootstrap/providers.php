<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,

    // Module Service Providers
    App\Modules\UserManagement\UserManagementServiceProvider::class,
    App\Modules\FormBuilder\FormBuilderServiceProvider::class,
    App\Modules\WorkflowBuilder\WorkflowBuilderServiceProvider::class,
    App\Modules\VerificationSnapshot\VerificationSnapshotServiceProvider::class,
    App\Modules\ErrorReports\ErrorReportsServiceProvider::class,
];
