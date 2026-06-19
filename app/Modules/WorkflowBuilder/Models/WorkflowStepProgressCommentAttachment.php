<?php

namespace App\Modules\WorkflowBuilder\Models;

use App\Modules\UserManagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // correct user model (tbl_user, PK account_id)

class WorkflowStepProgressCommentAttachment extends Model
{
    protected $table = 'tbl_workflow_step_progress_comment_attachment';

    protected $fillable = [
        'progress_id',
        'uploaded_by',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    public function progress(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepProgress::class, 'progress_id');
    }

    public function uploader(): BelongsTo
    {
        // uploaded_by (FK) → account_id (PK on tbl_user)
        return $this->belongsTo(User::class, 'uploaded_by', 'account_id')
            ->withDefault(); // avoids null-property errors in views
    }
}
