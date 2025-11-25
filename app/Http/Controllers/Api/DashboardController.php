<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview statistics (using JOIN)
     */
    public function stats(): JsonResponse
    {
        $user = auth()->user();

        // Get project IDs where user has access
        if ($user->role === 'admin') {
            // Admins can see all projects
            $projectIds = DB::table('projects')
                ->whereNull('deleted_at')
                ->pluck('id');
        } else {
            // Regular users see only their projects
            $projectIds = DB::table('projects')
                ->where(function ($query) use ($user) {
                    $query->where('owner_id', $user->id)
                        ->orWhere('is_public', true)
                        ->orWhereExists(function ($subQuery) use ($user) {
                            $subQuery->select(DB::raw(1))
                                ->from('project_members')
                                ->whereColumn('project_members.project_id', 'projects.id')
                                ->where('project_members.user_id', $user->id);
                        });
                })
                ->whereNull('deleted_at')
                ->pluck('id');
        }

        // Total projects
        $totalProjects = $projectIds->count();

        // Active projects
        $activeProjects = DB::table('projects')
            ->whereIn('id', $projectIds)
            ->where('status', 'active')
            ->count();

        // Total tasks in accessible projects
        $totalTasks = DB::table('tasks')
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at')
            ->count();

        // My tasks (assigned to me) or all tasks for admin
        if ($user->role === 'admin') {
            $myTasks = $totalTasks; // Admins see all tasks as "my tasks"
        } else {
            $myTasks = DB::table('tasks')
                ->whereIn('project_id', $projectIds)
                ->where('assigned_to', $user->id)
                ->whereNull('deleted_at')
                ->count();
        }

        // Completed tasks
        if ($user->role === 'admin') {
            $completedTasks = DB::table('tasks')
                ->whereIn('project_id', $projectIds)
                ->where('status', 'done')
                ->whereNull('deleted_at')
                ->count();
        } else {
            $completedTasks = DB::table('tasks')
                ->whereIn('project_id', $projectIds)
                ->where('assigned_to', $user->id)
                ->where('status', 'done')
                ->whereNull('deleted_at')
                ->count();
        }

        // Pending tasks
        if ($user->role === 'admin') {
            $pendingTasks = DB::table('tasks')
                ->whereIn('project_id', $projectIds)
                ->whereIn('status', ['todo', 'in_progress', 'review'])
                ->whereNull('deleted_at')
                ->count();
        } else {
            $pendingTasks = DB::table('tasks')
                ->whereIn('project_id', $projectIds)
                ->where('assigned_to', $user->id)
                ->whereIn('status', ['todo', 'in_progress', 'review'])
                ->whereNull('deleted_at')
                ->count();
        }

        // Overdue tasks
        if ($user->role === 'admin') {
            $overdueTasks = DB::table('tasks')
                ->whereIn('project_id', $projectIds)
                ->where('due_date', '<', now()->format('Y-m-d'))
                ->whereNotIn('status', ['done'])
                ->whereNull('deleted_at')
                ->count();
        } else {
            $overdueTasks = DB::table('tasks')
                ->whereIn('project_id', $projectIds)
                ->where('assigned_to', $user->id)
                ->where('due_date', '<', now()->format('Y-m-d'))
                ->whereNotIn('status', ['done'])
                ->whereNull('deleted_at')
                ->count();
        }

        // Tasks by status
        $tasksByStatusQuery = DB::table('tasks')
            ->select('status', DB::raw('count(*) as count'))
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at');

        if ($user->role !== 'admin') {
            $tasksByStatusQuery->where('assigned_to', $user->id);
        }

        $tasksByStatus = $tasksByStatusQuery
            ->groupBy('status')
            ->pluck('count', 'status');

        // Tasks by priority
        $tasksByPriorityQuery = DB::table('tasks')
            ->select('priority', DB::raw('count(*) as count'))
            ->whereIn('project_id', $projectIds)
            ->whereNull('deleted_at');

        if ($user->role !== 'admin') {
            $tasksByPriorityQuery->where('assigned_to', $user->id);
        }

        $tasksByPriority = $tasksByPriorityQuery
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $stats = [
            'total_projects' => $totalProjects,
            'active_projects' => $activeProjects,
            'total_tasks' => $totalTasks,
            'my_tasks' => $myTasks,
            'completed_tasks' => $completedTasks,
            'pending_tasks' => $pendingTasks,
            'overdue_tasks' => $overdueTasks,
            'tasks_by_status' => [
                'todo' => $tasksByStatus['todo'] ?? 0,
                'in_progress' => $tasksByStatus['in_progress'] ?? 0,
                'review' => $tasksByStatus['review'] ?? 0,
                'done' => $tasksByStatus['done'] ?? 0,
            ],
            'tasks_by_priority' => [
                'low' => $tasksByPriority['low'] ?? 0,
                'medium' => $tasksByPriority['medium'] ?? 0,
                'high' => $tasksByPriority['high'] ?? 0,
                'critical' => $tasksByPriority['critical'] ?? 0,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get recent projects (using JOIN)
     */
    public function recentProjects(): JsonResponse
    {
        $user = auth()->user();

        $query = DB::table('projects')
            ->join('users as owner', 'projects.owner_id', '=', 'owner.id')
            ->leftJoin('project_members as pm', function ($join) {
                $join->on('projects.id', '=', 'pm.project_id');
            })
            ->select(
                'projects.*',
                'owner.name as owner_name',
                'owner.email as owner_email',
                DB::raw('COUNT(DISTINCT pm.user_id) as members_count')
            );

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

        $projects = $query
            ->whereNull('projects.deleted_at')
            ->groupBy('projects.id', 'projects.name', 'projects.slug', 'projects.description', 'projects.owner_id', 'projects.status', 'projects.priority', 'projects.start_date', 'projects.end_date', 'projects.budget', 'projects.color', 'projects.is_public', 'projects.created_at', 'projects.updated_at', 'projects.deleted_at', 'owner.id', 'owner.name', 'owner.email')
            ->orderBy('projects.updated_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'status' => $project->status,
                    'priority' => $project->priority,
                    'owner' => [
                        'id' => $project->owner_id,
                        'name' => $project->owner_name,
                        'email' => $project->owner_email,
                    ],
                    'members_count' => $project->members_count,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ];
            }),
            'meta' => [
                'total' => $projects->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get recent tasks (using JOIN)
     */
    public function recentTasks(): JsonResponse
    {
        $user = auth()->user();

        // Get accessible project IDs
        if ($user->role === 'admin') {
            $projectIds = DB::table('projects')
                ->whereNull('deleted_at')
                ->pluck('id');
        } else {
            $projectIds = DB::table('projects')
                ->where(function ($query) use ($user) {
                    $query->where('owner_id', $user->id)
                        ->orWhere('is_public', true)
                        ->orWhereExists(function ($subQuery) use ($user) {
                            $subQuery->select(DB::raw(1))
                                ->from('project_members')
                                ->whereColumn('project_members.project_id', 'projects.id')
                                ->where('project_members.user_id', $user->id);
                        });
                })
                ->whereNull('deleted_at')
                ->pluck('id');
        }

        $tasksQuery = DB::table('tasks')
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
            ->whereIn('tasks.project_id', $projectIds)
            ->whereNull('tasks.deleted_at');

        if ($user->role !== 'admin') {
            $tasksQuery->where('tasks.assigned_to', $user->id);
        }

        $tasks = $tasksQuery
            ->orderBy('tasks.updated_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'project_id' => $task->project_id,
                    'project_name' => $task->project_name,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date,
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
                ];
            }),
            'meta' => [
                'total' => $tasks->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get project report (using JOIN)
     */
    public function projectReport(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        // Get project with owner info
        $projectData = DB::table('projects')
            ->join('users as owner', 'projects.owner_id', '=', 'owner.id')
            ->select(
                'projects.*',
                'owner.name as owner_name',
                'owner.email as owner_email'
            )
            ->where('projects.id', $project->id)
            ->first();

        // Task statistics
        $totalTasks = DB::table('tasks')
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->count();

        $completedTasks = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('status', 'done')
            ->whereNull('deleted_at')
            ->count();

        $inProgressTasks = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('status', 'in_progress')
            ->whereNull('deleted_at')
            ->count();

        $todoTasks = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('status', 'todo')
            ->whereNull('deleted_at')
            ->count();

        $overdueTasks = DB::table('tasks')
            ->where('project_id', $project->id)
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done'])
            ->whereNull('deleted_at')
            ->count();

        $totalMembers = DB::table('project_members')
            ->where('project_id', $project->id)
            ->count() + 1; // +1 for owner

        $tasksByStatus = DB::table('tasks')
            ->select('status', DB::raw('count(*) as count'))
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->pluck('count', 'status');

        $tasksByPriority = DB::table('tasks')
            ->select('priority', DB::raw('count(*) as count'))
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $tasksByAssignee = DB::table('tasks')
            ->join('users', 'tasks.assigned_to', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', DB::raw('count(*) as task_count'))
            ->where('tasks.project_id', $project->id)
            ->whereNotNull('tasks.assigned_to')
            ->whereNull('tasks.deleted_at')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->get();

        $completionPercentage = $totalTasks > 0 ? (int) (($completedTasks / $totalTasks) * 100) : 0;

        $report = [
            'project' => [
                'id' => $projectData->id,
                'name' => $projectData->name,
                'slug' => $projectData->slug,
                'status' => $projectData->status,
                'priority' => $projectData->priority,
                'owner' => [
                    'id' => $projectData->owner_id,
                    'name' => $projectData->owner_name,
                    'email' => $projectData->owner_email,
                ],
            ],
            'statistics' => [
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'in_progress_tasks' => $inProgressTasks,
                'todo_tasks' => $todoTasks,
                'overdue_tasks' => $overdueTasks,
                'total_members' => $totalMembers,
                'completion_percentage' => $completionPercentage,
                'tasks_by_status' => $tasksByStatus,
                'tasks_by_priority' => $tasksByPriority,
                'tasks_by_assignee' => $tasksByAssignee->map(function ($item) {
                    return [
                        'user' => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'email' => $item->email,
                        ],
                        'task_count' => $item->task_count,
                    ];
                }),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $report,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get user report (using JOIN)
     */
    public function userReport(int $userId): JsonResponse
    {
        $user = auth()->user();

        // Only admins can view other users' reports
        if ($user->id !== $userId && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this report',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'UNAUTHORIZED',
                ]
            ], 403);
        }

        // Get user info
        $reportUser = DB::table('users')
            ->select('id', 'name', 'email', 'role')
            ->where('id', $userId)
            ->first();

        if (!$reportUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'NOT_FOUND',
                ]
            ], 404);
        }

        // Projects where user is involved
        $totalProjects = DB::table('projects')
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->select(DB::raw(1))
                            ->from('project_members')
                            ->whereColumn('project_members.project_id', 'projects.id')
                            ->where('project_members.user_id', $userId);
                    });
            })
            ->whereNull('deleted_at')
            ->count();

        $ownedProjects = DB::table('projects')
            ->where('owner_id', $userId)
            ->whereNull('deleted_at')
            ->count();

        $memberProjects = DB::table('project_members')
            ->join('projects', 'project_members.project_id', '=', 'projects.id')
            ->where('project_members.user_id', $userId)
            ->whereNull('projects.deleted_at')
            ->count();

        $totalTasksAssigned = DB::table('tasks')
            ->where('assigned_to', $userId)
            ->whereNull('deleted_at')
            ->count();

        $completedTasks = DB::table('tasks')
            ->where('assigned_to', $userId)
            ->where('status', 'done')
            ->whereNull('deleted_at')
            ->count();

        $inProgressTasks = DB::table('tasks')
            ->where('assigned_to', $userId)
            ->where('status', 'in_progress')
            ->whereNull('deleted_at')
            ->count();

        $overdueTasks = DB::table('tasks')
            ->where('assigned_to', $userId)
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done'])
            ->whereNull('deleted_at')
            ->count();

        $tasksCreated = DB::table('tasks')
            ->where('created_by', $userId)
            ->whereNull('deleted_at')
            ->count();

        $tasksByPriority = DB::table('tasks')
            ->select('priority', DB::raw('count(*) as count'))
            ->where('assigned_to', $userId)
            ->whereNull('deleted_at')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $report = [
            'user' => [
                'id' => $reportUser->id,
                'name' => $reportUser->name,
                'email' => $reportUser->email,
                'role' => $reportUser->role,
            ],
            'statistics' => [
                'total_projects' => $totalProjects,
                'owned_projects' => $ownedProjects,
                'member_projects' => $memberProjects,
                'total_tasks_assigned' => $totalTasksAssigned,
                'completed_tasks' => $completedTasks,
                'in_progress_tasks' => $inProgressTasks,
                'overdue_tasks' => $overdueTasks,
                'tasks_created' => $tasksCreated,
                'tasks_by_priority' => $tasksByPriority,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $report,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }
}
