<?php

namespace App\Modules\FormBuilder\Models;

use App\Modules\UserManagement\Models\User;
use App\Modules\VerificationSnapshot\Models\Snapshot;
use App\Modules\WorkflowBuilder\Models\WorkflowStepProgress;
use App\Modules\WorkflowBuilder\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSubmission extends Model
{
    protected $table = 'tbl_form_submission';

    protected $fillable = [
        'form_id',
        'account_id',
        'workflow_version_id',
        'idempotency_key',
        'submission_status',
        'current_workflow_status',
        'current_step_id',
        'current_actor_id',
        'payload_json',
        'schema_snapshot_json',
        'submitted_at',
        'revision_of',
        'root_submission_id',
        'is_latest_revision',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'schema_snapshot_json' => 'array',
        'idempotency_key' => 'string',
        'submitted_at' => 'datetime',
        'is_latest_revision' => 'boolean',
        'current_step_id' => 'integer',
        'current_actor_id' => 'integer',
        'revision_of' => 'integer',
        'root_submission_id' => 'integer',
        'workflow_version_id' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id')->withTrashed();
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id', 'account_id');
    }

    public function parentRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of');
    }

    public function rootSubmission(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_submission_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revision_of');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SubmissionAttachment::class, 'submission_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class, 'submission_id');
    }

    public function workflowProgressEntries(): HasMany
    {
        return $this->hasMany(WorkflowStepProgress::class, 'submission_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(Snapshot::class, 'submission_id');
    }
}
