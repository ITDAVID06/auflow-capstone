<?php

namespace App\Modules\Notifications\Models;

use App\Modules\UserManagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'tbl_notification';

    protected $fillable = [
        'account_id',
        'type',
        'title',
        'message',
        'action_url',
        'action_text',
        'related_type',
        'related_id',
        'icon',
        'priority',
        'is_read',
        'triggered_by',
        'idempotency_key',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id', 'account_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by', 'account_id');
    }

    /**
     * Mark this notification as read
     */
    public function markAsRead(): bool
    {
        if (! $this->is_read) {
            return $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return false;
    }

    /**
     * Scope: Only unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope: Notifications for a specific user
     */
    public function scopeForUser($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope: Recent notifications (last 30 days)
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
