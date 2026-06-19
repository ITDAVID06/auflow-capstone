<?php

namespace App\Events;

use App\Modules\WorkflowBuilder\Models\WorkflowStep;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;

class WorkflowStepMoved
{
    public function __construct(
        public WorkflowStepProgress $progress,
        public WorkflowStep $fromStep,
        public WorkflowStep $toStep
    ) {}
}
