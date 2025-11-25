<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

class ActivityService
{
    /**
     * Log an activity for a model.
     */
    public function log(Model $subject, string $description, ?int $userId = null, ?array $properties = null): Activity
    {
        return Activity::create([
            'user_id' => $userId ?? auth()->id(),
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    /**
     * Log a project creation.
     */
    public function logProjectCreated(Model $project): Activity
    {
        return $this->log($project, 'Created project', null, [
            'name' => $project->name,
            'status' => $project->status,
        ]);
    }

    /**
     * Log a project update.
     */
    public function logProjectUpdated(Model $project, array $changes): Activity
    {
        return $this->log($project, 'Updated project', null, [
            'changes' => $changes,
        ]);
    }

    /**
     * Log a project deletion.
     */
    public function logProjectDeleted(Model $project): Activity
    {
        return $this->log($project, 'Deleted project', null, [
            'name' => $project->name,
        ]);
    }

    /**
     * Log a project archive.
     */
    public function logProjectArchived(Model $project): Activity
    {
        return $this->log($project, 'Archived project');
    }

    /**
     * Log a project unarchive.
     */
    public function logProjectUnarchived(Model $project): Activity
    {
        return $this->log($project, 'Unarchived project');
    }

    /**
     * Log member addition to project.
     */
    public function logMemberAdded(Model $project, int $memberId, string $role): Activity
    {
        return $this->log($project, 'Added member to project', null, [
            'member_id' => $memberId,
            'role' => $role,
        ]);
    }

    /**
     * Log member removal from project.
     */
    public function logMemberRemoved(Model $project, int $memberId): Activity
    {
        return $this->log($project, 'Removed member from project', null, [
            'member_id' => $memberId,
        ]);
    }

    /**
     * Log member role update.
     */
    public function logMemberRoleUpdated(Model $project, int $memberId, string $oldRole, string $newRole): Activity
    {
        return $this->log($project, 'Updated member role', null, [
            'member_id' => $memberId,
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ]);
    }

    /**
     * Log a task creation.
     */
    public function logTaskCreated(Model $task): Activity
    {
        return $this->log($task, 'Created task', null, [
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
        ]);
    }

    /**
     * Log a task update.
     */
    public function logTaskUpdated(Model $task, array $changes): Activity
    {
        return $this->log($task, 'Updated task', null, [
            'changes' => $changes,
        ]);
    }

    /**
     * Log a task deletion.
     */
    public function logTaskDeleted(Model $task): Activity
    {
        return $this->log($task, 'Deleted task', null, [
            'title' => $task->title,
        ]);
    }

    /**
     * Log task assignment.
     */
    public function logTaskAssigned(Model $task, ?int $oldAssignee, ?int $newAssignee): Activity
    {
        return $this->log($task, 'Assigned task', null, [
            'old_assignee' => $oldAssignee,
            'new_assignee' => $newAssignee,
        ]);
    }

    /**
     * Log task status change.
     */
    public function logTaskStatusChanged(Model $task, string $oldStatus, string $newStatus): Activity
    {
        return $this->log($task, 'Changed task status', null, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);
    }

    /**
     * Log task priority change.
     */
    public function logTaskPriorityChanged(Model $task, string $oldPriority, string $newPriority): Activity
    {
        return $this->log($task, 'Changed task priority', null, [
            'old_priority' => $oldPriority,
            'new_priority' => $newPriority,
        ]);
    }

    /**
     * Log comment creation.
     */
    public function logCommentAdded(Model $commentable, string $commentableType): Activity
    {
        return $this->log($commentable, "Added comment to {$commentableType}");
    }

    /**
     * Log comment update.
     */
    public function logCommentUpdated(Model $commentable, string $commentableType): Activity
    {
        return $this->log($commentable, "Updated comment on {$commentableType}");
    }

    /**
     * Log comment deletion.
     */
    public function logCommentDeleted(Model $commentable, string $commentableType): Activity
    {
        return $this->log($commentable, "Deleted comment from {$commentableType}");
    }

    /**
     * Log file upload.
     */
    public function logFileUploaded(Model $attachable, string $filename): Activity
    {
        return $this->log($attachable, 'Uploaded file', null, [
            'filename' => $filename,
        ]);
    }

    /**
     * Log file deletion.
     */
    public function logFileDeleted(Model $attachable, string $filename): Activity
    {
        return $this->log($attachable, 'Deleted file', null, [
            'filename' => $filename,
        ]);
    }

    /**
     * Log tag addition.
     */
    public function logTagAdded(Model $taggable, string $tagName): Activity
    {
        return $this->log($taggable, 'Added tag', null, [
            'tag' => $tagName,
        ]);
    }

    /**
     * Log tag removal.
     */
    public function logTagRemoved(Model $taggable, string $tagName): Activity
    {
        return $this->log($taggable, 'Removed tag', null, [
            'tag' => $tagName,
        ]);
    }
}
