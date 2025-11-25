<?php

namespace App\Traits;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    /**
     * Log an activity.
     */
    protected function logActivity(Model $subject, string $description, array $properties = []): void
    {
        Activity::create([
            'user_id' => auth()->id(),
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,
            'description' => $description,
            'properties' => !empty($properties) ? json_encode($properties) : null,
        ]);
    }

    /**
     * Log project activity.
     */
    protected function logProjectActivity($project, string $action, array $properties = []): void
    {
        $descriptions = [
            'created' => 'Created project',
            'updated' => 'Updated project',
            'deleted' => 'Deleted project',
            'restored' => 'Restored project',
            'archived' => 'Archived project',
            'unarchived' => 'Unarchived project',
            'member_added' => 'Added member to project',
            'member_removed' => 'Removed member from project',
            'member_updated' => 'Updated member role in project',
        ];

        $this->logActivity(
            $project,
            $descriptions[$action] ?? $action,
            $properties
        );
    }

    /**
     * Log task activity.
     */
    protected function logTaskActivity($task, string $action, array $properties = []): void
    {
        $descriptions = [
            'created' => 'Created task',
            'updated' => 'Updated task',
            'deleted' => 'Deleted task',
            'assigned' => 'Assigned task',
            'status_changed' => 'Changed task status',
            'priority_changed' => 'Changed task priority',
            'tag_added' => 'Added tag to task',
            'tag_removed' => 'Removed tag from task',
        ];

        $this->logActivity(
            $task,
            $descriptions[$action] ?? $action,
            $properties
        );
    }

    /**
     * Log comment activity.
     */
    protected function logCommentActivity($commentable, string $action): void
    {
        $descriptions = [
            'added' => 'Added comment',
            'updated' => 'Updated comment',
            'deleted' => 'Deleted comment',
        ];

        $this->logActivity(
            $commentable,
            $descriptions[$action] ?? $action
        );
    }

    /**
     * Log attachment activity.
     */
    protected function logAttachmentActivity($attachable, string $action, array $properties = []): void
    {
        $descriptions = [
            'uploaded' => 'Uploaded file',
            'deleted' => 'Deleted file',
        ];

        $this->logActivity(
            $attachable,
            $descriptions[$action] ?? $action,
            $properties
        );
    }
}
