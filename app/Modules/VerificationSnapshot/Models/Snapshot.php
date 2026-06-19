<?php

namespace App\Modules\VerificationSnapshot\Models;

use App\Exceptions\SnapshotImmutableException;
use App\Modules\FormBuilder\Models\FormSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Snapshot extends Model
{
    protected $table = 'tbl_snapshot';

    public $timestamps = false;

    protected $fillable = [
        'public_id',
        'submission_id',
        'form_id',
        'workflow_id',
        'step_id',
        'workflow_step',
        'status',
        'approved_by',
        'approved_at',
        'comment',
        'payload_json',
        'action_hash',
        'rendered_html_path',
        'locked',
        'created_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'payload_json' => 'array',
        'locked' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Snapshot $model): void {
            if ($model->exists && $model->locked) {
                throw new SnapshotImmutableException(
                    "Snapshot #{$model->getKey()} is locked and cannot be modified."
                );
            }
        });
    }

    public function canonicalSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\UserManagement\Models\User::class, 'approved_by', 'account_id');
    }
}
