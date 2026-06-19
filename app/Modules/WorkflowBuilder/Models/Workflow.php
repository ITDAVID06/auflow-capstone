<?php

namespace App\Modules\WorkflowBuilder\Models;

use App\Modules\FormBuilder\Models\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $table = 'tbl_workflow';

    protected $fillable = [
        'workflow_name',
        'workflow_type',
        'form_id',
        'description',
        'status',
        'created_by',
        'workflow_settings',
    ];

    protected $casts = [
        'workflow_settings' => 'array',
        'version' => 'integer',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class, 'workflow_id');
    }
}
