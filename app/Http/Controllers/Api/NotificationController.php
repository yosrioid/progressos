<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = InAppNotification::ownedBy($request->user())
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $unreadCount = InAppNotification::ownedBy($request->user())->unread()->count();

        return ApiResponse::ok([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, InAppNotification $notification)
    {
        abort_if($notification->user_id !== $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return ApiResponse::ok(['read' => true]);
    }

    public function markAllRead(Request $request)
    {
        InAppNotification::ownedBy($request->user())
            ->unread()
            ->update(['read_at' => now()]);

        return ApiResponse::ok(['marked' => true]);
    }

    public function destroy(Request $request, InAppNotification $notification)
    {
        abort_if($notification->user_id !== $request->user()->id, 403);
        $notification->delete();

        return response()->noContent();
    }

    public function clearAll(Request $request)
    {
        InAppNotification::ownedBy($request->user())->delete();

        return ApiResponse::ok(['cleared' => true]);
    }

    public function unreadCount(Request $request)
    {
        return ApiResponse::ok([
            'count' => InAppNotification::ownedBy($request->user())->unread()->count(),
        ]);
    }
}
