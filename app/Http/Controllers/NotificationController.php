<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications - List user notifications
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 20);
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->paginate($limit);

        return response()->json($notifications);
    }

    /**
     * GET /api/notifications/unread-count - Get count
     */
    public function unreadCount()
    {
        $count = Notification::where('user_id', Auth::id())
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * PUT /api/notifications/{id}/read - Mark as read
     */
    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->findOrFail($id);
            
        $notification->markAsRead();

        return response()->json(['message' => 'Marked as read']);
    }

    /**
     * PUT /api/notifications/read-all - Mark all as read
     */
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All marked as read']);
    }
}
