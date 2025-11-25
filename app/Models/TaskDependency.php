<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskDependency extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'task_id',
        'depends_on_task_id',
        'dependency_type',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Relationships
     */

    /**
     * Get the task that has this dependency
     */
    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * Get the task that this task depends on
     */
    public function dependsOnTask()
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }

    /**
     * Get the user who created this dependency
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */

    /**
     * Scope to filter by dependency type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('dependency_type', $type);
    }

    /**
     * Scope to get blocking dependencies
     */
    public function scopeBlocking($query)
    {
        return $query->where('dependency_type', 'blocks');
    }

    /**
     * Scope to get blocked by dependencies
     */
    public function scopeBlockedBy($query)
    {
        return $query->where('dependency_type', 'blocked_by');
    }

    /**
     * Scope to get related dependencies
     */
    public function scopeRelated($query)
    {
        return $query->where('dependency_type', 'related_to');
    }

    /**
     * Helper methods
     */

    /**
     * Check if this is a blocking dependency
     */
    public function isBlocking(): bool
    {
        return $this->dependency_type === 'blocks';
    }

    /**
     * Check if this is a blocked by dependency
     */
    public function isBlockedBy(): bool
    {
        return $this->dependency_type === 'blocked_by';
    }

    /**
     * Check if this is a related to dependency
     */
    public function isRelatedTo(): bool
    {
        return $this->dependency_type === 'related_to';
    }

    /**
     * Check for circular dependencies
     * Returns true if adding this dependency would create a circle
     */
    public static function wouldCreateCircularDependency(int $taskId, int $dependsOnTaskId): bool
    {
        // If task depends on itself, it's circular
        if ($taskId === $dependsOnTaskId) {
            return true;
        }

        // Check if the depends_on task already depends on the current task (directly or indirectly)
        return self::hasTransitiveDependency($dependsOnTaskId, $taskId);
    }

    /**
     * Check if taskA has a transitive dependency on taskB
     * (i.e., taskA depends on taskB directly or through a chain)
     */
    private static function hasTransitiveDependency(int $taskAId, int $taskBId, array &$visited = []): bool
    {
        // Prevent infinite loops
        if (in_array($taskAId, $visited)) {
            return false;
        }

        $visited[] = $taskAId;

        // Direct dependency check
        $directDependency = self::where('task_id', $taskAId)
            ->where('depends_on_task_id', $taskBId)
            ->exists();

        if ($directDependency) {
            return true;
        }

        // Get all tasks that taskA depends on
        $dependencies = self::where('task_id', $taskAId)
            ->pluck('depends_on_task_id')
            ->toArray();

        // Recursively check each dependency
        foreach ($dependencies as $dependencyTaskId) {
            if (self::hasTransitiveDependency($dependencyTaskId, $taskBId, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all tasks in the dependency chain
     */
    public static function getDependencyChain(int $taskId): array
    {
        $chain = [];
        self::buildDependencyChain($taskId, $chain);
        return $chain;
    }

    /**
     * Recursively build dependency chain
     */
    private static function buildDependencyChain(int $taskId, array &$chain, array &$visited = []): void
    {
        if (in_array($taskId, $visited)) {
            return;
        }

        $visited[] = $taskId;
        $chain[] = $taskId;

        $dependencies = self::where('task_id', $taskId)
            ->pluck('depends_on_task_id')
            ->toArray();

        foreach ($dependencies as $dependencyTaskId) {
            self::buildDependencyChain($dependencyTaskId, $chain, $visited);
        }
    }
}
