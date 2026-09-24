<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Display a listing of user notifications or return JSON payload.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $filter = $request->input('filter', 'all');

        $query = $user->notifications();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate(20)->through(function ($notification) {
            $data = is_array($notification->data) ? $notification->data : (json_decode($notification->data, true) ?? []);

            return [
                'id' => $notification->id,
                'type' => $data['type'] ?? class_basename($notification->type),
                'title' => $data['title'] ?? 'Notification',
                'message' => $data['message'] ?? '',
                'url' => $data['url'] ?? null,
                'data' => $data,
                'read' => ! is_null($notification->read_at),
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at->toIso8601String(),
                'time_ago' => $notification->created_at->diffForHumans(),
            ];
        });

        if ($request->wantsJson()) {
            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Lightweight unread counters endpoint for realtime badge updates.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadNotifications = $user->unreadNotifications()->count();
        $unreadMessages = Message::where('direction', 'inbound')->where('is_read', false)->count();
        $unreadConversations = Conversation::where('unread_count', '>', 0)->count();
        $myUnreadConversations = Conversation::where('assigned_user_id', $user->id)
            ->where('unread_count', '>', 0)
            ->count();

        return response()->json([
            'unread_notifications' => $unreadNotifications,
            'unread_messages' => $unreadMessages,
            'unread_conversations' => $unreadConversations,
            'my_unread_conversations' => $myUnreadConversations,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }
}
