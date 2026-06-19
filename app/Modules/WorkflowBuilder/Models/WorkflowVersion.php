<?php

namespace App\Modules\WorkflowBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowVersion extends Model
{
    protected $table = 'tbl_workflow_version';

    protected $fillable = [
        'workflow_id',
        'version_number',
        'steps_snapshot',
        'published_at',
        'is_current',
    ];

    protected $casts = [
        'steps_snapshot' => 'array',
        'published_at' => 'datetime',
        'version_number' => 'integer',
        'is_current' => 'boolean',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }
}
