<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('notifications')
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', 'App\\Models\\User');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        if ($request->has('type')) {
            $query->where('type', 'like', '%' . $request->type . '%');
        }

        $notifications = $query
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('limit', 20));

        $items = collect($notifications->items())->map(function ($notification) {
            $notification->data = json_decode($notification->data, true);
            return $notification;
        });

        return response()->json(['data' => $items]);
    }

    public function show($id)
    {
        $notification = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', 'App\\Models\\User')
            ->first();

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->data = json_decode($notification->data, true);

        return response()->json(['data' => $notification]);
    }

    public function unreadCount()
    {
        $count = DB::table('notifications')
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', 'App\\Models\\User')
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function stats()
    {
        $userId = Auth::id();
        $userType = 'App\\Models\\User';

        $total = DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->count();

        $unread = DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->whereNull('read_at')
            ->count();

        $read = DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->whereNotNull('read_at')
            ->count();

        $today = DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->whereDate('created_at', today())
            ->count();

        $thisWeek = DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->where('notifiable_type', $userType)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        return response()->json([
            'data' => [
                'total' => $total,
                'unread' => $unread,
                'read' => $read,
                'today' => $today,
                'this_week' => $thisWeek,
            ]
        ]);
    }

    public function markAsRead($id)
    {
        $affected = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', 'App\\Models\\User')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($affected === 0) {
            return response()->json(['message' => 'Notification not found or already read'], 404);
        }

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAsUnread($id)
    {
        $affected = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', 'App\\Models\\User')
            ->update(['read_at' => null]);

        if ($affected === 0) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(['message' => 'Notification marked as unread']);
    }

    public function markAllAsRead()
    {
        DB::table('notifications')
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', 'App\\Models\\User')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function destroy($id)
    {
        $affected = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', 'App\\Models\\User')
            ->delete();

        if ($affected === 0) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(['message' => 'Notification deleted']);
    }

    public function deleteAllRead()
    {
        DB::table('notifications')
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', 'App\\Models\\User')
            ->whereNotNull('read_at')
            ->delete();

        return response()->json(['message' => 'All read notifications deleted']);
    }

    public function getSettings()
    {
        $settings = DB::table('notification_settings')
            ->where('user_id', Auth::id())
            ->first();

        if (!$settings) {
            $settingsId = DB::table('notification_settings')->insertGetId([
                'user_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $settings = DB::table('notification_settings')
                ->where('id', $settingsId)
                ->first();
        }

        return response()->json(['data' => $settings]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'email_notifications' => 'nullable|boolean',
            'push_notifications' => 'nullable|boolean',
            'task_assigned' => 'nullable|boolean',
            'task_completed' => 'nullable|boolean',
            'task_commented' => 'nullable|boolean',
            'task_mentioned' => 'nullable|boolean',
            'task_due_soon' => 'nullable|boolean',
            'project_updates' => 'nullable|boolean',
            'digest_frequency' => 'nullable|in:realtime,daily,weekly,never',
            'quiet_hours_enabled' => 'nullable|boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
        ]);

        $validated['updated_at'] = now();

        $exists = DB::table('notification_settings')
            ->where('user_id', Auth::id())
            ->exists();

        if ($exists) {
            DB::table('notification_settings')
                ->where('user_id', Auth::id())
                ->update($validated);
        } else {
            $validated['user_id'] = Auth::id();
            $validated['created_at'] = now();
            DB::table('notification_settings')->insert($validated);
        }

        $settings = DB::table('notification_settings')
            ->where('user_id', Auth::id())
            ->first();

        return response()->json(['data' => $settings]);
    }

    public function sendTestNotification()
    {
        DB::table('notifications')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => Auth::id(),
            'data' => json_encode([
                'title' => 'Test Notification',
                'message' => 'This is a test notification from the system.',
                'icon' => 'bell',
                'action_url' => '/notifications',
                'action_text' => 'View Notifications',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Test notification sent']);
    }

    public function subscribeToPush(Request $request)
    {
        DB::table('notification_settings')
            ->updateOrInsert(
                ['user_id' => Auth::id()],
                [
                    'push_notifications' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

        return response()->json(['message' => 'Subscribed to push notifications']);
    }
}
