<?php

namespace App\Events;

use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;

class WorkflowStepRejected
{
    public function __construct(public WorkflowStepProgress $progress, public ?string $reason = null) {}
}
