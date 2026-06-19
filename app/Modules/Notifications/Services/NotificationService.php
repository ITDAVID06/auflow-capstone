<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\Notification;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * Send notification to one or more users
     */
    public function send(array|int $recipientIds, string $type, array $data): void
    {
        if (! is_array($recipientIds)) {
            $recipientIds = [$recipientIds];
        }

        $notifications = [];
        $now = now();

        foreach ($recipientIds as $accountId) {
            $notifications[] = [
                'account_id' => $accountId,
                'type' => $type,
                'title' => $data['title'],
                'message' => $data['message'],
                'action_url' => $data['action_url'] ?? null,
                'action_text' => $data['action_text'] ?? null,
                'related_type' => $data['related_type'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'icon' => $data['icon'] ?? 'bell',
                'priority' => $data['priority'] ?? 'normal',
                'triggered_by' => $data['triggered_by'] ?? auth()->user()?->account_id,
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Batch insert for performance
        if (! empty($notifications)) {
            Notification::insert($notifications);
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(int $notificationId, int $accountId): bool
    {
        return Notification::where('id', $notificationId)
            ->where('account_id', $accountId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]) > 0;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $accountId): int
    {
        return Notification::where('account_id', $accountId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get unread notification count for a user
     */
    public function getUnreadCount(int $accountId): int
    {
        return Notification::where('account_id', $accountId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get recent notifications for a user
     */
    public function getNotifications(int $accountId, int $limit = 50): Collection
    {
        return Notification::with('triggeredBy:account_id,username')
            ->where('account_id', $accountId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'action_url' => $notification->action_url,
                    'action_text' => $notification->action_text,
                    'icon' => $notification->icon,
                    'priority' => $notification->priority,
                    'is_read' => $notification->is_read,
                    'created_at' => $notification->created_at->toIso8601String(),
                    'triggered_by' => $notification->triggeredBy ? [
                        'account_id' => $notification->triggeredBy->account_id,
                        'username' => $notification->triggeredBy->username,
                        'full_name' => $notification->triggeredBy->full_name,
                    ] : null,
                ];
            });
    }

    /**
     * Delete old read notifications (cleanup)
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return Notification::where('is_read', true)
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Delete a notification
     */
    public function delete(int $notificationId, int $accountId): bool
    {
        return Notification::where('id', $notificationId)
            ->where('account_id', $accountId)
            ->delete() > 0;
    }
}
