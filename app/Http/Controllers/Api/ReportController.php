<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get project progress report
     */
    public function projectProgress(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        // Get task statistics
        $totalTasks = Task::where('project_id', $project->id)->count();
        $completedTasks = Task::where('project_id', $project->id)
            ->where('status', 'done')
            ->count();
        $inProgressTasks = Task::where('project_id', $project->id)
            ->where('status', 'in_progress')
            ->count();
        $todoTasks = Task::where('project_id', $project->id)
            ->where('status', 'todo')
            ->count();
        $blockedTasks = Task::where('project_id', $project->id)
            ->where('status', 'blocked')
            ->count();

        // Calculate completion percentage
        $completionPercentage = $totalTasks > 0
            ? round(($completedTasks / $totalTasks) * 100, 2)
            : 0;

        // Get tasks by priority
        $highPriorityTasks = Task::where('project_id', $project->id)
            ->where('priority', 'high')
            ->count();
        $mediumPriorityTasks = Task::where('project_id', $project->id)
            ->where('priority', 'medium')
            ->count();
        $lowPriorityTasks = Task::where('project_id', $project->id)
            ->where('priority', 'low')
            ->count();

        // Get overdue tasks
        $overdueTasks = Task::where('project_id', $project->id)
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done'])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'total_tasks' => $totalTasks,
                'completion_percentage' => $completionPercentage,
                'status_breakdown' => [
                    'todo' => $todoTasks,
                    'in_progress' => $inProgressTasks,
                    'done' => $completedTasks,
                    'blocked' => $blockedTasks,
                ],
                'priority_breakdown' => [
                    'high' => $highPriorityTasks,
                    'medium' => $mediumPriorityTasks,
                    'low' => $lowPriorityTasks,
                ],
                'overdue_tasks' => $overdueTasks,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get team workload report for a project
     */
    public function teamWorkload(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        // Get all team members with their task counts
        $members = DB::table('project_members as pm')
            ->join('users as u', 'pm.user_id', '=', 'u.id')
            ->leftJoin(DB::raw('(
                SELECT assigned_to, status, COUNT(*) as count
                FROM tasks
                WHERE project_id = ' . $project->id . '
                AND deleted_at IS NULL
                GROUP BY assigned_to, status
            ) as task_counts'), 'u.id', '=', 'task_counts.assigned_to')
            ->select(
                'u.id',
                'u.name',
                'u.email',
                'pm.role'
            )
            ->where('pm.project_id', $project->id)
            ->groupBy('u.id', 'u.name', 'u.email', 'pm.role')
            ->get();

        $workloadData = [];
        foreach ($members as $member) {
            // Get task counts by status for this member
            $taskCounts = DB::table('tasks')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->where('project_id', $project->id)
                ->where('assigned_to', $member->id)
                ->whereNull('deleted_at')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $totalTasks = array_sum($taskCounts);
            $completedTasks = $taskCounts['done'] ?? 0;
            $inProgressTasks = $taskCounts['in_progress'] ?? 0;
            $todoTasks = $taskCounts['todo'] ?? 0;
            $blockedTasks = $taskCounts['blocked'] ?? 0;

            $workloadData[] = [
                'user_id' => $member->id,
                'user_name' => $member->name,
                'user_email' => $member->email,
                'role' => $member->role,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'in_progress_tasks' => $inProgressTasks,
                'todo_tasks' => $todoTasks,
                'blocked_tasks' => $blockedTasks,
                'completion_rate' => $totalTasks > 0
                    ? round(($completedTasks / $totalTasks) * 100, 2)
                    : 0,
            ];
        }

        // Sort by total tasks descending
        usort($workloadData, function ($a, $b) {
            return $b['total_tasks'] - $a['total_tasks'];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'team_members' => $workloadData,
            ],
            'meta' => [
                'total_members' => count($workloadData),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get task completion trends for a project
     */
    public function completionTrends(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        // Get date range (default to last 30 days)
        $days = $request->input('days', 30);
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Get daily completion counts
        $completions = DB::table('tasks')
            ->select(
                DB::raw('DATE(updated_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('project_id', $project->id)
            ->where('status', 'done')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->orderBy('date', 'asc')
            ->get();

        // Get daily creation counts
        $creations = DB::table('tasks')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('project_id', $project->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        // Create date array with all dates in range
        $dateArray = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dateArray[$dateStr] = [
                'date' => $dateStr,
                'completed' => 0,
                'created' => 0,
            ];
            $currentDate->addDay();
        }

        // Fill in completion data
        foreach ($completions as $completion) {
            if (isset($dateArray[$completion->date])) {
                $dateArray[$completion->date]['completed'] = $completion->count;
            }
        }

        // Fill in creation data
        foreach ($creations as $creation) {
            if (isset($dateArray[$creation->date])) {
                $dateArray[$creation->date]['created'] = $creation->count;
            }
        }

        // Convert to indexed array
        $trendsData = array_values($dateArray);

        // Calculate summary statistics
        $totalCompleted = array_sum(array_column($trendsData, 'completed'));
        $totalCreated = array_sum(array_column($trendsData, 'created'));
        $avgCompletedPerDay = count($trendsData) > 0
            ? round($totalCompleted / count($trendsData), 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'days' => $days,
                ],
                'trends' => $trendsData,
                'summary' => [
                    'total_completed' => $totalCompleted,
                    'total_created' => $totalCreated,
                    'avg_completed_per_day' => $avgCompletedPerDay,
                ],
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get overall dashboard statistics (all projects)
     */
    public function dashboard(Request $request): JsonResponse
    {
        $userId = auth()->id();

        // Get user's projects (where they are members)
        $projectIds = DB::table('project_members')
            ->where('user_id', $userId)
            ->pluck('project_id')
            ->toArray();

        // Total statistics
        $totalProjects = count($projectIds);
        $totalTasks = Task::whereIn('project_id', $projectIds)->count();
        $completedTasks = Task::whereIn('project_id', $projectIds)
            ->where('status', 'done')
            ->count();
        $inProgressTasks = Task::whereIn('project_id', $projectIds)
            ->where('status', 'in_progress')
            ->count();

        // Tasks assigned to user
        $myTasks = Task::whereIn('project_id', $projectIds)
            ->where('assigned_to', $userId)
            ->count();
        $myCompletedTasks = Task::whereIn('project_id', $projectIds)
            ->where('assigned_to', $userId)
            ->where('status', 'done')
            ->count();
        $myInProgressTasks = Task::whereIn('project_id', $projectIds)
            ->where('assigned_to', $userId)
            ->where('status', 'in_progress')
            ->count();

        // Overdue tasks
        $overdueTasks = Task::whereIn('project_id', $projectIds)
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done'])
            ->count();
        $myOverdueTasks = Task::whereIn('project_id', $projectIds)
            ->where('assigned_to', $userId)
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done'])
            ->count();

        // Project progress breakdown
        $projectsProgress = [];
        foreach ($projectIds as $projectId) {
            $project = Project::find($projectId);
            if (!$project) continue;

            $projectTotalTasks = Task::where('project_id', $projectId)->count();
            $projectCompletedTasks = Task::where('project_id', $projectId)
                ->where('status', 'done')
                ->count();

            $projectsProgress[] = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'total_tasks' => $projectTotalTasks,
                'completed_tasks' => $projectCompletedTasks,
                'completion_percentage' => $projectTotalTasks > 0
                    ? round(($projectCompletedTasks / $projectTotalTasks) * 100, 2)
                    : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_projects' => $totalProjects,
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'in_progress_tasks' => $inProgressTasks,
                    'overdue_tasks' => $overdueTasks,
                ],
                'my_tasks' => [
                    'total' => $myTasks,
                    'completed' => $myCompletedTasks,
                    'in_progress' => $myInProgressTasks,
                    'overdue' => $myOverdueTasks,
                ],
                'projects_progress' => $projectsProgress,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }
}
