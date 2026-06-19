<?php

namespace App\Modules\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionAttachment extends Model
{
    protected $table = 'tbl_submission_attachment';

    protected $fillable = [
        'submission_id',
        'file_path',
        'original_name',
        'mime_type',
        'uploaded_by',
    ];

    public function canonicalSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }
}
