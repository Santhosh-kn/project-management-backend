<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    /**
     * Get user's activity feed (using JOIN)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 100); // Max 100 per page
        $page = $request->get('page', 1);
        
        $userId = auth()->id();

        // Get accessible project IDs
        $projectIds = DB::table('projects')
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
            ->pluck('id')
            ->toArray();

        // Get activities related to user or their projects
        $activities = DB::table('activities')
            ->leftJoin('users', 'activities.user_id', '=', 'users.id')
            ->select(
                'activities.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where(function ($query) use ($userId, $projectIds) {
                $query->where('activities.user_id', $userId);
                
                // Include activities on user's projects
                if (!empty($projectIds)) {
                    $query->orWhere(function ($subQuery) use ($projectIds) {
                        $subQuery->where('activities.subject_type', 'App\\Models\\Project')
                            ->whereIn('activities.subject_id', $projectIds);
                    });
                    
                    // Include activities on tasks in user's projects
                    $query->orWhereExists(function ($taskQuery) use ($projectIds) {
                        $taskQuery->select(DB::raw(1))
                            ->from('tasks')
                            ->whereColumn('tasks.id', 'activities.subject_id')
                            ->where('activities.subject_type', 'App\\Models\\Task')
                            ->whereIn('tasks.project_id', $projectIds);
                    });
                }
            })
            ->orderBy('activities.created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $total = DB::table('activities')
            ->where(function ($query) use ($userId, $projectIds) {
                $query->where('activities.user_id', $userId);
                
                if (!empty($projectIds)) {
                    $query->orWhere(function ($subQuery) use ($projectIds) {
                        $subQuery->where('activities.subject_type', 'App\\Models\\Project')
                            ->whereIn('activities.subject_id', $projectIds);
                    });
                    
                    $query->orWhereExists(function ($taskQuery) use ($projectIds) {
                        $taskQuery->select(DB::raw(1))
                            ->from('tasks')
                            ->whereColumn('tasks.id', 'activities.subject_id')
                            ->where('activities.subject_type', 'App\\Models\\Task')
                            ->whereIn('tasks.project_id', $projectIds);
                    });
                }
            })
            ->count();

        $lastPage = ceil($total / $perPage);

        return response()->json([
            'success' => true,
            'data' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'properties' => $activity->properties ? json_decode($activity->properties) : null,
                    'user' => $activity->user_id ? [
                        'id' => $activity->user_id,
                        'name' => $activity->user_name,
                        'email' => $activity->user_email,
                    ] : null,
                    'subject_type' => $activity->subject_type,
                    'subject_id' => $activity->subject_id,
                    'created_at' => $activity->created_at,
                ];
            }),
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get project activities (using JOIN)
     */
    public function projectActivities(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $activities = DB::table('activities')
            ->leftJoin('users', 'activities.user_id', '=', 'users.id')
            ->select(
                'activities.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('activities.subject_type', 'App\\Models\\Project')
            ->where('activities.subject_id', $project->id)
            ->orderBy('activities.created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'properties' => $activity->properties ? json_decode($activity->properties) : null,
                    'user' => $activity->user_id ? [
                        'id' => $activity->user_id,
                        'name' => $activity->user_name,
                        'email' => $activity->user_email,
                    ] : null,
                    'created_at' => $activity->created_at,
                ];
            }),
            'meta' => [
                'total' => $activities->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get task activities (using JOIN)
     */
    public function taskActivities(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $activities = DB::table('activities')
            ->leftJoin('users', 'activities.user_id', '=', 'users.id')
            ->select(
                'activities.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('activities.subject_type', 'App\\Models\\Task')
            ->where('activities.subject_id', $task->id)
            ->orderBy('activities.created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'properties' => $activity->properties ? json_decode($activity->properties) : null,
                    'user' => $activity->user_id ? [
                        'id' => $activity->user_id,
                        'name' => $activity->user_name,
                        'email' => $activity->user_email,
                    ] : null,
                    'created_at' => $activity->created_at,
                ];
            }),
            'meta' => [
                'total' => $activities->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }
}
