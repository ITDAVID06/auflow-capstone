<?php

namespace App\Modules\WorkflowBuilder\Models;

use App\Modules\FormBuilder\Models\Form;
use App\Modules\FormBuilder\Models\FormSubmission;
use App\Modules\UserManagement\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepProgress extends Model
{
    protected $table = 'tbl_workflow_step_progress';

    protected $fillable = [
        'form_id',
        'submission_id',
        'workflow_id',
        'workflow_version_id',
        'step_id',
        'actor_id',
        'action_taken',
        'comments',
        'acted_at',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_reminder_at' => 'datetime',
    ];

    protected $attributes = [
        'reminder_count' => 0,
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id', 'id');
    }

    public function canonicalSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function getDurationHumanAttribute(): ?string
    {
        if (! $this->duration_seconds) {
            return null;
        }

        $interval = CarbonInterval::seconds($this->duration_seconds)->cascade();
        $parts = [];

        if ($interval->d > 0) {
            $parts[] = $interval->d.'d';
        }
        if ($interval->h > 0) {
            $parts[] = $interval->h.'h';
        }
        if ($interval->i > 0) {
            $parts[] = $interval->i.'m';
        }

        return implode(' ', $parts) ?: '0m';
    }

    public function commentAttachments()
    {
        return $this->hasMany(
            \App\Modules\WorkflowBuilder\Models\WorkflowStepProgressCommentAttachment::class,
            'progress_id'
        );
    }
}
