<?php

namespace App\Modules\Reports\Models;

use App\Modules\FormBuilder\Models\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledExport extends Model
{
    protected $table = 'tbl_scheduled_export';

    protected $fillable = [
        'form_id',
        'recipient_email',
        'frequency',
        'export_type',
        'filter_state',
        'last_sent_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'form_id' => 'integer',
        'filter_state' => 'array',
        'last_sent_at' => 'datetime',
        'is_active' => 'boolean',
        'created_by' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }
}
