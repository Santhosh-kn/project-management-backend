<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationHelper
{
    public static function create($userId, $type, $data)
    {
        return DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $userId,
            'data' => json_encode($data),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function taskAssigned($userId, $task, $assignedBy)
    {
        return self::create($userId, 'App\\Notifications\\TaskAssignedNotification', [
            'title' => 'New Task Assignment',
            'message' => $assignedBy['name'] . ' assigned you to "' . $task['title'] . '"',
            'icon' => 'task',
            'action_url' => '/tasks/' . $task['id'],
            'action_text' => 'View Task',
            'actor' => ['id' => $assignedBy['id'], 'name' => $assignedBy['name']],
            'context' => ['task_id' => $task['id'], 'task_title' => $task['title'], 'project_id' => $task['project_id'] ?? null],
        ]);
    }

    public static function taskCommented($userId, $task, $comment, $commenter)
    {
        return self::create($userId, 'App\\Notifications\\TaskCommentedNotification', [
            'title' => 'New Comment',
            'message' => $commenter['name'] . ' commented on "' . $task['title'] . '"',
            'icon' => 'comment',
            'action_url' => '/tasks/' . $task['id'],
            'action_text' => 'View Task',
            'actor' => ['id' => $commenter['id'], 'name' => $commenter['name']],
            'context' => ['task_id' => $task['id'], 'task_title' => $task['title'], 'comment_id' => $comment['id']],
        ]);
    }

    public static function taskCompleted($userId, $task, $completedBy)
    {
        return self::create($userId, 'App\\Notifications\\TaskCompletedNotification', [
            'title' => 'Task Completed',
            'message' => $completedBy['name'] . ' completed "' . $task['title'] . '"',
            'icon' => 'check',
            'action_url' => '/tasks/' . $task['id'],
            'action_text' => 'View Task',
            'actor' => ['id' => $completedBy['id'], 'name' => $completedBy['name']],
            'context' => ['task_id' => $task['id'], 'task_title' => $task['title']],
        ]);
    }

    public static function taskMentioned($userId, $task, $comment, $mentioner)
    {
        return self::create($userId, 'App\\Notifications\\TaskMentionedNotification', [
            'title' => 'You were mentioned',
            'message' => $mentioner['name'] . ' mentioned you in "' . $task['title'] . '"',
            'icon' => 'mention',
            'action_url' => '/tasks/' . $task['id'],
            'action_text' => 'View Task',
            'actor' => ['id' => $mentioner['id'], 'name' => $mentioner['name']],
            'context' => ['task_id' => $task['id'], 'task_title' => $task['title'], 'comment_id' => $comment['id']],
        ]);
    }
}
