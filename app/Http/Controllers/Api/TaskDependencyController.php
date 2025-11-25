<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskDependencyController extends Controller
{
    /**
     * Get all dependencies for a task
     */
    public function index(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        // Get dependencies with related task information
        $dependencies = DB::table('task_dependencies as td')
            ->join('tasks as t', 'td.depends_on_task_id', '=', 't.id')
            ->leftJoin('users as assigned', 't.assigned_to', '=', 'assigned.id')
            ->leftJoin('users as creator', 'td.created_by', '=', 'creator.id')
            ->select(
                'td.id',
                'td.task_id',
                'td.depends_on_task_id',
                'td.dependency_type',
                'td.created_at',
                'td.updated_at',
                't.title as depends_on_task_title',
                't.status as depends_on_task_status',
                't.priority as depends_on_task_priority',
                't.due_date as depends_on_task_due_date',
                'assigned.id as assigned_to_id',
                'assigned.name as assigned_to_name',
                'assigned.email as assigned_to_email',
                'creator.id as created_by_id',
                'creator.name as created_by_name'
            )
            ->where('td.task_id', $task->id)
            ->orderBy('td.created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dependencies->map(function ($dep) {
                return [
                    'id' => $dep->id,
                    'task_id' => $dep->task_id,
                    'depends_on_task_id' => $dep->depends_on_task_id,
                    'dependency_type' => $dep->dependency_type,
                    'depends_on_task' => [
                        'id' => $dep->depends_on_task_id,
                        'title' => $dep->depends_on_task_title,
                        'status' => $dep->depends_on_task_status,
                        'priority' => $dep->depends_on_task_priority,
                        'due_date' => $dep->depends_on_task_due_date,
                        'assigned_to' => $dep->assigned_to_id ? [
                            'id' => $dep->assigned_to_id,
                            'name' => $dep->assigned_to_name,
                            'email' => $dep->assigned_to_email,
                        ] : null,
                    ],
                    'created_by' => [
                        'id' => $dep->created_by_id,
                        'name' => $dep->created_by_name,
                    ],
                    'created_at' => $dep->created_at,
                    'updated_at' => $dep->updated_at,
                ];
            }),
            'meta' => [
                'total' => $dependencies->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Create a new dependency
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'depends_on_task_id' => [
                'required',
                'integer',
                'exists:tasks,id',
            ],
            'dependency_type' => [
                'required',
                Rule::in(['blocks', 'blocked_by', 'related_to']),
            ],
        ]);

        // Check if depends_on task exists and is in the same project
        $dependsOnTask = Task::find($validated['depends_on_task_id']);
        
        if (!$dependsOnTask) {
            return response()->json([
                'success' => false,
                'message' => 'The task to depend on does not exist',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'TASK_NOT_FOUND',
                ]
            ], 404);
        }

        // Ensure tasks are in the same project
        if ($task->project_id !== $dependsOnTask->project_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot create dependency between tasks in different projects',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'DIFFERENT_PROJECTS',
                ]
            ], 422);
        }

        // Check for circular dependencies
        if (TaskDependency::wouldCreateCircularDependency($task->id, $validated['depends_on_task_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot create dependency: This would create a circular dependency',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'CIRCULAR_DEPENDENCY',
                ]
            ], 422);
        }

        // Check if dependency already exists
        $existingDependency = TaskDependency::where('task_id', $task->id)
            ->where('depends_on_task_id', $validated['depends_on_task_id'])
            ->where('dependency_type', $validated['dependency_type'])
            ->first();

        if ($existingDependency) {
            return response()->json([
                'success' => false,
                'message' => 'This dependency already exists',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'DUPLICATE_DEPENDENCY',
                ]
            ], 422);
        }

        // Create the dependency
        $dependencyId = DB::table('task_dependencies')->insertGetId([
            'task_id' => $task->id,
            'depends_on_task_id' => $validated['depends_on_task_id'],
            'dependency_type' => $validated['dependency_type'],
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get the created dependency with related information
        $dependency = DB::table('task_dependencies as td')
            ->join('tasks as t', 'td.depends_on_task_id', '=', 't.id')
            ->leftJoin('users as assigned', 't.assigned_to', '=', 'assigned.id')
            ->leftJoin('users as creator', 'td.created_by', '=', 'creator.id')
            ->select(
                'td.id',
                'td.task_id',
                'td.depends_on_task_id',
                'td.dependency_type',
                'td.created_at',
                'td.updated_at',
                't.title as depends_on_task_title',
                't.status as depends_on_task_status',
                't.priority as depends_on_task_priority',
                't.due_date as depends_on_task_due_date',
                'assigned.id as assigned_to_id',
                'assigned.name as assigned_to_name',
                'assigned.email as assigned_to_email',
                'creator.id as created_by_id',
                'creator.name as created_by_name'
            )
            ->where('td.id', $dependencyId)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $dependency->id,
                'task_id' => $dependency->task_id,
                'depends_on_task_id' => $dependency->depends_on_task_id,
                'dependency_type' => $dependency->dependency_type,
                'depends_on_task' => [
                    'id' => $dependency->depends_on_task_id,
                    'title' => $dependency->depends_on_task_title,
                    'status' => $dependency->depends_on_task_status,
                    'priority' => $dependency->depends_on_task_priority,
                    'due_date' => $dependency->depends_on_task_due_date,
                    'assigned_to' => $dependency->assigned_to_id ? [
                        'id' => $dependency->assigned_to_id,
                        'name' => $dependency->assigned_to_name,
                        'email' => $dependency->assigned_to_email,
                    ] : null,
                ],
                'created_by' => [
                    'id' => $dependency->created_by_id,
                    'name' => $dependency->created_by_name,
                ],
                'created_at' => $dependency->created_at,
                'updated_at' => $dependency->updated_at,
            ],
            'message' => 'Dependency created successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 201);
    }

    /**
     * Delete a dependency
     */
    public function destroy(TaskDependency $dependency): JsonResponse
    {
        // Authorize - user must be able to update the task
        $task = Task::findOrFail($dependency->task_id);
        $this->authorize('update', $task);

        DB::table('task_dependencies')
            ->where('id', $dependency->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dependency deleted successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 204);
    }

    /**
     * Get dependency tree for a task (for graph visualization)
     */
    public function tree(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        // Get all dependencies recursively
        $visited = [];
        $tree = $this->buildDependencyTree($task->id, $visited);

        return response()->json([
            'success' => true,
            'data' => $tree,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Build dependency tree recursively
     */
    private function buildDependencyTree(int $taskId, array &$visited): array
    {
        if (in_array($taskId, $visited)) {
            return [
                'circular' => true,
                'task_id' => $taskId,
            ];
        }

        $visited[] = $taskId;

        $task = DB::table('tasks')
            ->leftJoin('users as assigned', 'tasks.assigned_to', '=', 'assigned.id')
            ->select(
                'tasks.id',
                'tasks.title',
                'tasks.status',
                'tasks.priority',
                'tasks.due_date',
                'assigned.id as assigned_to_id',
                'assigned.name as assigned_to_name'
            )
            ->where('tasks.id', $taskId)
            ->first();

        if (!$task) {
            return [];
        }

        $dependencies = DB::table('task_dependencies')
            ->where('task_id', $taskId)
            ->get();

        $dependencyNodes = [];
        foreach ($dependencies as $dep) {
            $dependencyNodes[] = [
                'dependency_id' => $dep->id,
                'dependency_type' => $dep->dependency_type,
                'node' => $this->buildDependencyTree($dep->depends_on_task_id, $visited),
            ];
        }

        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'priority' => $task->priority,
            'due_date' => $task->due_date,
            'assigned_to' => $task->assigned_to_id ? [
                'id' => $task->assigned_to_id,
                'name' => $task->assigned_to_name,
            ] : null,
            'dependencies' => $dependencyNodes,
        ];
    }

    /**
     * Get tasks that depend on this task (reverse dependencies)
     */
    public function dependents(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        // Get tasks that depend on this task
        $dependents = DB::table('task_dependencies as td')
            ->join('tasks as t', 'td.task_id', '=', 't.id')
            ->leftJoin('users as assigned', 't.assigned_to', '=', 'assigned.id')
            ->select(
                'td.id',
                'td.task_id',
                'td.depends_on_task_id',
                'td.dependency_type',
                't.title as task_title',
                't.status as task_status',
                't.priority as task_priority',
                't.due_date as task_due_date',
                'assigned.id as assigned_to_id',
                'assigned.name as assigned_to_name',
                'assigned.email as assigned_to_email'
            )
            ->where('td.depends_on_task_id', $task->id)
            ->orderBy('td.created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dependents->map(function ($dep) {
                return [
                    'id' => $dep->id,
                    'task_id' => $dep->task_id,
                    'dependency_type' => $dep->dependency_type,
                    'task' => [
                        'id' => $dep->task_id,
                        'title' => $dep->task_title,
                        'status' => $dep->task_status,
                        'priority' => $dep->task_priority,
                        'due_date' => $dep->task_due_date,
                        'assigned_to' => $dep->assigned_to_id ? [
                            'id' => $dep->assigned_to_id,
                            'name' => $dep->assigned_to_name,
                            'email' => $dep->assigned_to_email,
                        ] : null,
                    ],
                ];
            }),
            'meta' => [
                'total' => $dependents->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }
}
