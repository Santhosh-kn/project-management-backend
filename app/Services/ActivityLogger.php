<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Log an activity.
     */
    public static function log(
        string $description,
        Model $subject,
        ?array $properties = null,
        ?int $userId = null
    ): Activity {
        return Activity::create([
            'user_id' => $userId ?? auth()->id(),
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    /**
     * Log project creation.
     */
    public static function projectCreated(Model $project): Activity
    {
        return self::log(
            'Project created',
            $project,
            ['project_name' => $project->name]
        );
    }

    /**
     * Log project update.
     */
    public static function projectUpdated(Model $project, array $changes): Activity
    {
        return self::log(
            'Project updated',
            $project,
            ['changes' => $changes]
        );
    }

    /**
     * Log project deleted.
     */
    public static function projectDeleted(Model $project): Activity
    {
        return self::log(
            'Project deleted',
            $project,
            ['project_name' => $project->name]
        );
    }

    /**
     * Log project archived.
     */
    public static function projectArchived(Model $project): Activity
    {
        return self::log(
            'Project archived',
            $project,
            ['project_name' => $project->name]
        );
    }

    /**
     * Log project unarchived.
     */
    public static function projectUnarchived(Model $project): Activity
    {
        return self::log(
            'Project unarchived',
            $project,
            ['project_name' => $project->name]
        );
    }

    /**
     * Log member added to project.
     */
    public static function memberAdded(Model $project, int $userId, string $role): Activity
    {
        return self::log(
            'Member added to project',
            $project,
            [
                'user_id' => $userId,
                'role' => $role,
            ]
        );
    }

    /**
     * Log member removed from project.
     */
    public static function memberRemoved(Model $project, int $userId): Activity
    {
        return self::log(
            'Member removed from project',
            $project,
            ['user_id' => $userId]
        );
    }

    /**
     * Log member role updated.
     */
    public static function memberRoleUpdated(Model $project, int $userId, string $oldRole, string $newRole): Activity
    {
        return self::log(
            'Member role updated',
            $project,
            [
                'user_id' => $userId,
                'old_role' => $oldRole,
                'new_role' => $newRole,
            ]
        );
    }

    /**
     * Log task creation.
     */
    public static function taskCreated(Model $task): Activity
    {
        return self::log(
            'Task created',
            $task,
            ['task_title' => $task->title]
        );
    }

    /**
     * Log task update.
     */
    public static function taskUpdated(Model $task, array $changes): Activity
    {
        return self::log(
            'Task updated',
            $task,
            ['changes' => $changes]
        );
    }

    /**
     * Log task deleted.
     */
    public static function taskDeleted(Model $task): Activity
    {
        return self::log(
            'Task deleted',
            $task,
            ['task_title' => $task->title]
        );
    }

    /**
     * Log task assigned.
     */
    public static function taskAssigned(Model $task, int $userId): Activity
    {
        return self::log(
            'Task assigned',
            $task,
            [
                'task_title' => $task->title,
                'assigned_to' => $userId,
            ]
        );
    }

    /**
     * Log task status changed.
     */
    public static function taskStatusChanged(Model $task, string $oldStatus, string $newStatus): Activity
    {
        return self::log(
            'Task status changed',
            $task,
            [
                'task_title' => $task->title,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]
        );
    }

    /**
     * Log task priority changed.
     */
    public static function taskPriorityChanged(Model $task, string $oldPriority, string $newPriority): Activity
    {
        return self::log(
            'Task priority changed',
            $task,
            [
                'task_title' => $task->title,
                'old_priority' => $oldPriority,
                'new_priority' => $newPriority,
            ]
        );
    }

    /**
     * Log comment added.
     */
    public static function commentAdded(Model $commentable, string $commentableType): Activity
    {
        return self::log(
            'Comment added',
            $commentable,
            ['commentable_type' => $commentableType]
        );
    }

    /**
     * Log attachment uploaded.
     */
    public static function attachmentUploaded(Model $attachable, string $filename): Activity
    {
        return self::log(
            'Attachment uploaded',
            $attachable,
            ['filename' => $filename]
        );
    }

    /**
     * Log attachment deleted.
     */
    public static function attachmentDeleted(Model $attachable, string $filename): Activity
    {
        return self::log(
            'Attachment deleted',
            $attachable,
            ['filename' => $filename]
        );
    }
}
