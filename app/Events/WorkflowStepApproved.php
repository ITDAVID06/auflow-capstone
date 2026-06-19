<?php

namespace App\Events;

use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;

class WorkflowStepApproved
{
    public function __construct(public WorkflowStepProgress $progress) {}
}
