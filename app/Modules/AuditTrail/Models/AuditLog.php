<?php

namespace App\Modules\AuditTrail\Models;

use App\Modules\UserManagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $table = 'tbl_audit_log';

    protected $fillable = [
        'category', 'action', 'status', 'description',
        'actor_id', 'actor_name', 'actor_email', 'actor_role',
        'auditable_type', 'auditable_id',
        'ip_address', 'user_agent',
        'snapshot_id', 'snapshot_public_id',
        'qr_payload', 'qr_image_path', 'verification_result',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        // actor_id (logs) -> account_id (users)
        return $this->belongsTo(User::class, 'actor_id', 'account_id');
    }
}
