<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = $user->notifications();

        // Filter by state
        if ($request->has('filter')) {
            if ($request->filter === 'unread') {
                $query = $user->unreadNotifications();
            } elseif ($request->filter === 'read') {
                $query = $user->readNotifications();
            }
        }

        // Search in data (JSON)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('data', 'like', "%{$search}%");
        }
        
        $notifications = $query->latest()->paginate(10);

        return response()->json($notifications);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Remove the specified notification.
     */
    public function destroy(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }
}
