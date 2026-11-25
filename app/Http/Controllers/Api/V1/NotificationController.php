<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group الإشعارات (Notifications)
 */
class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Get user notifications list with unread counter.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->latest('id')
            ->take(30)
            ->get();

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return $this->successResponse([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if ($notification && ! $notification->is_read) {
            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();
        }

        return $this->successResponse(null, 'تم تحديد الإشعار كمقروء');
    }

    /**
     * Mark all user notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return $this->successResponse(null, 'تم تحديد جميع الإشعارات كمقروءة');
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->delete();

        return $this->successResponse(null, 'تم حذف الإشعار');
    }
}

