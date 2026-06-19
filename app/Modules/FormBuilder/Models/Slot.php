<?php

namespace App\Modules\FormBuilder\Models;

use App\Modules\UserManagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Slot extends Model
{
    protected $table = 'tbl_slots';

    protected $fillable = [
        'form_id',
        'submission_id',
        'account_id',
        'facility_id',
        'date',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function canonicalSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id', 'account_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }
}
