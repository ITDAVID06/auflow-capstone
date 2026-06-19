<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Get notifications for current user (API endpoint)
     */
    public function index(): JsonResponse
    {
        $accountId = auth()->user()->account_id;

        $notifications = $this->notificationService->getNotifications($accountId);
        $unreadCount = $this->notificationService->getUnreadCount($accountId);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        $accountId = auth()->user()->account_id;

        $success = $this->notificationService->markAsRead($id, $accountId);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification marked as read' : 'Notification not found',
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $accountId = auth()->user()->account_id;

        $count = $this->notificationService->markAllAsRead($accountId);

        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => "{$count} notification(s) marked as read",
        ]);
    }

    /**
     * Delete a notification
     */
    public function destroy(int $id): JsonResponse
    {
        $accountId = auth()->user()->account_id;

        $success = $this->notificationService->delete($id, $accountId);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification deleted' : 'Notification not found',
        ]);
    }

    /**
     * Show all notifications page
     */
    public function page(): Response
    {
        $accountId = auth()->user()->account_id;

        $notifications = $this->notificationService->getNotifications($accountId, 100);
        $unreadCount = $this->notificationService->getUnreadCount($accountId);

        return Inertia::render('Notifications/NotificationsPage', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }
}
