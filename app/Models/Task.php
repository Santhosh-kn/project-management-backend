<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'project_id',
        'parent_id',
        'title',
        'description',
        'assigned_to',
        'created_by',
        'status',
        'priority',
        'due_date',
        'estimated_hours',
        'actual_hours',
        'order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
            'order' => 'integer',
        ];
    }

    /**
     * Relationships
     */

    /**
     * Get the project that owns the task
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user assigned to the task
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who created the task
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the parent task (for subtasks)
     */
    public function parent()
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * Get the subtasks
     */
    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    /**
     * Get the comments for the task (polymorphic)
     */
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Get the attachments for the task (polymorphic)
     */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the activities for the task (polymorphic)
     */
    public function activities()
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Get the tags for the task (polymorphic)
     */
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Get the tasks that this task depends on
     */
    public function dependencies()
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
                    ->withPivot('dependency_type')
                    ->withTimestamps();
    }

    /**
     * Get the tasks that depend on this task
     */
    public function dependents()
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
                    ->withPivot('dependency_type')
                    ->withTimestamps();
    }

    /**
     * Get all dependency records for this task
     */
    public function taskDependencies()
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    /**
     * Scopes
     */

    /**
     * Scope to filter by project
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by priority
     */
    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to filter by assigned user
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope to filter by creator
     */
    public function scopeCreatedBy($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to filter by due date range
     */
    public function scopeDueDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get only parent tasks (not subtasks)
     */
    public function scopeParentTasks($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get only subtasks
     */
    public function scopeSubtasks($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Scope to search tasks
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope to filter by tags
     */
    public function scopeWithTags($query, array $tagIds)
    {
        return $query->whereHas('tags', function ($q) use ($tagIds) {
            $q->whereIn('tags.id', $tagIds);
        });
    }

    /**
     * Scope to get overdue tasks
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['done']);
    }

    /**
     * Helper methods
     */

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date &&
               $this->due_date->isPast() &&
               $this->status !== 'done';
    }

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'done';
    }

    /**
     * Check if task is a subtask
     */
    public function isSubtask(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Check if task has subtasks
     */
    public function hasSubtasks(): bool
    {
        return $this->subtasks()->exists();
    }

    /**
     * Assign task to a user
     */
    public function assignTo(?int $userId): bool
    {
        return $this->update(['assigned_to' => $userId]);
    }

    /**
     * Update task status
     */
    public function updateStatus(string $status): bool
    {
        return $this->update(['status' => $status]);
    }

    /**
     * Update task priority
     */
    public function updatePriority(string $priority): bool
    {
        return $this->update(['priority' => $priority]);
    }

    /**
     * Mark task as complete
     */
    public function markAsComplete(): bool
    {
        return $this->updateStatus('done');
    }

    /**
     * Get completion percentage based on subtasks
     */
    public function getCompletionPercentage(): int
    {
        if (!$this->hasSubtasks()) {
            return $this->isCompleted() ? 100 : 0;
        }

        $totalSubtasks = $this->subtasks()->count();
        $completedSubtasks = $this->subtasks()->where('status', 'done')->count();

        return $totalSubtasks > 0 ? (int) (($completedSubtasks / $totalSubtasks) * 100) : 0;
    }
}
