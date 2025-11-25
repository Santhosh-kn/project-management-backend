<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks (using JOIN)
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $page = $request->get('page', 1);
        $perPage = min($request->get('per_page', 15), 100);

        // Build base query with JOINs
        $query = DB::table('tasks')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->leftJoin('users as assigned_user', 'tasks.assigned_to', '=', 'assigned_user.id')
            ->leftJoin('users as creator', 'tasks.created_by', '=', 'creator.id')
            ->select(
                'tasks.*',
                'projects.name as project_name',
                'assigned_user.name as assigned_to_name',
                'assigned_user.email as assigned_to_email',
                'creator.name as created_by_name',
                'creator.email as created_by_email'
            )
            ->whereNull('tasks.deleted_at');

        // Authorization: Filter accessible projects
        if ($user->role !== 'admin') {
            $query->where(function ($q) use ($user) {
                $q->where('projects.owner_id', $user->id)
                  ->orWhere('projects.is_public', true)
                  ->orWhereExists(function ($subQuery) use ($user) {
                      $subQuery->select(DB::raw(1))
                          ->from('project_members')
                          ->whereColumn('project_members.project_id', 'projects.id')
                          ->where('project_members.user_id', $user->id);
                  });
            });
        }

        // Filters
        if ($request->filled('project_id')) {
            $query->where('tasks.project_id', $request->project_id);
        }

        if ($request->filled('assigned_to')) {
            $query->where('tasks.assigned_to', $request->assigned_to);
        }

        if ($request->filled('status')) {
            $query->where('tasks.status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('tasks.priority', $request->priority);
        }

        if ($request->filled('due_date_from')) {
            $query->where('tasks.due_date', '>=', $request->due_date_from);
        }

        if ($request->filled('due_date_to')) {
            $query->where('tasks.due_date', '<=', $request->due_date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('tasks.title', 'like', "%{$search}%")
                  ->orWhere('tasks.description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tags')) {
            $tagIds = explode(',', $request->tags);
            $query->whereExists(function ($subQuery) use ($tagIds) {
                $subQuery->select(DB::raw(1))
                    ->from('taggables')
                    ->whereColumn('taggables.taggable_id', 'tasks.id')
                    ->where('taggables.taggable_type', 'App\\Models\\Task')
                    ->whereIn('taggables.tag_id', $tagIds);
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        
        if (in_array($sortField, ['title', 'status', 'priority', 'due_date', 'created_at', 'updated_at'])) {
            $query->orderBy("tasks.{$sortField}", $sortOrder);
        }

        // Get total count
        $totalQuery = clone $query;
        $total = $totalQuery->count();

        // Apply pagination
        $tasks = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $lastPage = ceil($total / $perPage);

        return response()->json([
            'success' => true,
            'data' => $tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'project_id' => $task->project_id,
                    'project' => [
                        'id' => $task->project_id,
                        'name' => $task->project_name,
                    ],
                    'parent_id' => $task->parent_id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date,
                    'estimated_hours' => $task->estimated_hours,
                    'actual_hours' => $task->actual_hours,
                    'order' => $task->order,
                    'assigned_to' => $task->assigned_to,
                    'assigned_user' => $task->assigned_to ? [
                        'id' => $task->assigned_to,
                        'name' => $task->assigned_to_name,
                        'email' => $task->assigned_to_email,
                    ] : null,
                    'created_by' => [
                        'id' => $task->created_by,
                        'name' => $task->created_by_name,
                        'email' => $task->created_by_email,
                    ],
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                ];
            }),
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Store a newly created task
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $taskData = $request->validated();
        $taskData['created_by'] = auth()->id();
        $taskData['created_at'] = now();
        $taskData['updated_at'] = now();

        // Extract tag_ids if present
        $tagIds = $taskData['tag_ids'] ?? [];
        unset($taskData['tag_ids']);

        $taskId = DB::table('tasks')->insertGetId($taskData);

        // Attach tags if provided
        if (!empty($tagIds)) {
            foreach ($tagIds as $tagId) {
                DB::table('taggables')->insert([
                    'tag_id' => $tagId,
                    'taggable_id' => $taskId,
                    'taggable_type' => 'App\\Models\\Task',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Get the created task with related data
        $task = DB::table('tasks')
            ->leftJoin('users as assigned_user', 'tasks.assigned_to', '=', 'assigned_user.id')
            ->leftJoin('users as creator', 'tasks.created_by', '=', 'creator.id')
            ->select(
                'tasks.*',
                'assigned_user.name as assigned_to_name',
                'assigned_user.email as assigned_to_email',
                'creator.name as created_by_name',
                'creator.email as created_by_email'
            )
            ->where('tasks.id', $taskId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully',
            'data' => [
                'id' => $task->id,
                'project_id' => $task->project_id,
                'parent_id' => $task->parent_id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date,
                'estimated_hours' => $task->estimated_hours,
                'actual_hours' => $task->actual_hours,
                'assigned_to' => $task->assigned_to ? [
                    'id' => $task->assigned_to,
                    'name' => $task->assigned_to_name,
                    'email' => $task->assigned_to_email,
                ] : null,
                'created_by' => [
                    'id' => $task->created_by,
                    'name' => $task->created_by_name,
                    'email' => $task->created_by_email,
                ],
                'created_at' => $task->created_at,
                'updated_at' => $task->updated_at,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Display the specified task (using JOIN)
     */
    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $taskData = DB::table('tasks')
            ->leftJoin('users as assigned_user', 'tasks.assigned_to', '=', 'assigned_user.id')
            ->leftJoin('users as creator', 'tasks.created_by', '=', 'creator.id')
            ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id')
            ->select(
                'tasks.*',
                'projects.name as project_name',
                'assigned_user.name as assigned_to_name',
                'assigned_user.email as assigned_to_email',
                'creator.name as created_by_name',
                'creator.email as created_by_email'
            )
            ->where('tasks.id', $task->id)
            ->first();

        // Get subtasks count
        $subtasksCount = DB::table('tasks')
            ->where('parent_id', $task->id)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $taskData->id,
                'project_id' => $taskData->project_id,
                'project_name' => $taskData->project_name,
                'parent_id' => $taskData->parent_id,
                'title' => $taskData->title,
                'description' => $taskData->description,
                'status' => $taskData->status,
                'priority' => $taskData->priority,
                'due_date' => $taskData->due_date,
                'estimated_hours' => $taskData->estimated_hours,
                'actual_hours' => $taskData->actual_hours,
                'order' => $taskData->order,
                'assigned_to' => $taskData->assigned_to ? [
                    'id' => $taskData->assigned_to,
                    'name' => $taskData->assigned_to_name,
                    'email' => $taskData->assigned_to_email,
                ] : null,
                'created_by' => [
                    'id' => $taskData->created_by,
                    'name' => $taskData->created_by_name,
                    'email' => $taskData->created_by_email,
                ],
                'subtasks_count' => $subtasksCount,
                'created_at' => $taskData->created_at,
                'updated_at' => $taskData->updated_at,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the specified task
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $taskData = $request->validated();
        
        // Extract tag_ids if present
        $tagIds = $taskData['tag_ids'] ?? null;
        unset($taskData['tag_ids']);

        // Update task data
        DB::table('tasks')
            ->where('id', $task->id)
            ->update(array_merge($taskData, ['updated_at' => now()]));

        // Sync tags if provided
        if ($tagIds !== null) {
            // Delete existing tags
            DB::table('taggables')
                ->where('taggable_id', $task->id)
                ->where('taggable_type', 'App\\Models\\Task')
                ->delete();

            // Add new tags
            if (!empty($tagIds)) {
                foreach ($tagIds as $tagId) {
                    DB::table('taggables')->insert([
                        'tag_id' => $tagId,
                        'taggable_id' => $task->id,
                        'taggable_type' => 'App\\Models\\Task',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Get updated task with tags
        $updatedTask = DB::table('tasks')
            ->leftJoin('users as assigned_user', 'tasks.assigned_to', '=', 'assigned_user.id')
            ->leftJoin('users as creator', 'tasks.created_by', '=', 'creator.id')
            ->select(
                'tasks.*',
                'assigned_user.name as assigned_to_name',
                'assigned_user.email as assigned_to_email',
                'creator.name as created_by_name',
                'creator.email as created_by_email'
            )
            ->where('tasks.id', $task->id)
            ->first();

        // Get tags
        $tags = DB::table('taggables')
            ->join('tags', 'taggables.tag_id', '=', 'tags.id')
            ->where('taggables.taggable_id', $task->id)
            ->where('taggables.taggable_type', 'App\\Models\\Task')
            ->select('tags.id', 'tags.name', 'tags.color')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
            'data' => [
                'id' => $updatedTask->id,
                'project_id' => $updatedTask->project_id,
                'parent_id' => $updatedTask->parent_id,
                'title' => $updatedTask->title,
                'description' => $updatedTask->description,
                'status' => $updatedTask->status,
                'priority' => $updatedTask->priority,
                'due_date' => $updatedTask->due_date,
                'estimated_hours' => $updatedTask->estimated_hours,
                'actual_hours' => $updatedTask->actual_hours,
                'assigned_to' => $updatedTask->assigned_to ? [
                    'id' => $updatedTask->assigned_to,
                    'name' => $updatedTask->assigned_to_name,
                    'email' => $updatedTask->assigned_to_email,
                ] : null,
                'created_by' => [
                    'id' => $updatedTask->created_by,
                    'name' => $updatedTask->created_by_name,
                    'email' => $updatedTask->created_by_email,
                ],
                'tags' => $tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ];
                }),
                'created_at' => $updatedTask->created_at,
                'updated_at' => $updatedTask->updated_at,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Remove the specified task (soft delete)
     */
    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        DB::table('tasks')
            ->where('id', $task->id)
            ->update(['deleted_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Assign task to a user
     */
    public function assign(AssignTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        DB::table('tasks')
            ->where('id', $task->id)
            ->update([
                'assigned_to' => $request->user_id,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Task assigned successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create a subtask
     */
    public function createSubtask(StoreTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $subtaskData = $request->validated();
        $subtaskData['parent_id'] = $task->id;
        $subtaskData['project_id'] = $task->project_id;
        $subtaskData['created_by'] = auth()->id();
        $subtaskData['created_at'] = now();
        $subtaskData['updated_at'] = now();

        $subtaskId = DB::table('tasks')->insertGetId($subtaskData);

        return response()->json([
            'success' => true,
            'message' => 'Subtask created successfully',
            'data' => [
                'id' => $subtaskId,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get subtasks (using JOIN)
     */
    public function subtasks(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $subtasks = DB::table('tasks')
            ->leftJoin('users as assigned_user', 'tasks.assigned_to', '=', 'assigned_user.id')
            ->leftJoin('users as creator', 'tasks.created_by', '=', 'creator.id')
            ->select(
                'tasks.*',
                'assigned_user.name as assigned_to_name',
                'assigned_user.email as assigned_to_email',
                'creator.name as created_by_name',
                'creator.email as created_by_email'
            )
            ->where('tasks.parent_id', $task->id)
            ->whereNull('tasks.deleted_at')
            ->orderBy('tasks.order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subtasks->map(function ($subtask) {
                return [
                    'id' => $subtask->id,
                    'title' => $subtask->title,
                    'description' => $subtask->description,
                    'status' => $subtask->status,
                    'priority' => $subtask->priority,
                    'due_date' => $subtask->due_date,
                    'assigned_to' => $subtask->assigned_to ? [
                        'id' => $subtask->assigned_to,
                        'name' => $subtask->assigned_to_name,
                        'email' => $subtask->assigned_to_email,
                    ] : null,
                    'created_by' => [
                        'id' => $subtask->created_by,
                        'name' => $subtask->created_by_name,
                        'email' => $subtask->created_by_email,
                    ],
                    'created_at' => $subtask->created_at,
                    'updated_at' => $subtask->updated_at,
                ];
            }),
            'meta' => [
                'total' => $subtasks->count(),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $request->validate([
            'status' => ['required', 'in:todo,in_progress,review,done'],
        ]);

        DB::table('tasks')
            ->where('id', $task->id)
            ->update([
                'status' => $request->status,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update task priority
     */
    public function updatePriority(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $request->validate([
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        DB::table('tasks')
            ->where('id', $task->id)
            ->update([
                'priority' => $request->priority,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Task priority updated successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Add tags to task
     */
    public function addTags(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $request->validate([
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
        ]);

        foreach ($request->tag_ids as $tagId) {
            // Check if tag is already attached
            $exists = DB::table('taggables')
                ->where('tag_id', $tagId)
                ->where('taggable_id', $task->id)
                ->where('taggable_type', 'App\\Models\\Task')
                ->exists();

            if (!$exists) {
                DB::table('taggables')->insert([
                    'tag_id' => $tagId,
                    'taggable_id' => $task->id,
                    'taggable_type' => 'App\\Models\\Task',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Tags added successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Remove tag from task
     */
    public function removeTag(Task $task, $tagId): JsonResponse
    {
        $this->authorize('update', $task);

        DB::table('taggables')
            ->where('tag_id', $tagId)
            ->where('taggable_id', $task->id)
            ->where('taggable_type', 'App\\Models\\Task')
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag removed successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
