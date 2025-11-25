<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddProjectMemberRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectMemberRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    /**
     * Display a listing of accessible projects (using JOIN)
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        // Build base query with JOINs
        $query = DB::table('projects')
            ->leftJoin('users as owner', 'projects.owner_id', '=', 'owner.id')
            ->leftJoin('project_members as pm', 'projects.id', '=', 'pm.project_id')
            ->select(
                'projects.*',
                'owner.name as owner_name',
                'owner.email as owner_email',
                DB::raw('COUNT(DISTINCT pm.user_id) as members_count'),
                DB::raw('(SELECT COUNT(*) FROM tasks WHERE tasks.project_id = projects.id AND tasks.deleted_at IS NULL) as tasks_count')
            )
            ->whereNull('projects.deleted_at')
            ->groupBy('projects.id', 'projects.name', 'projects.slug', 'projects.description', 'projects.owner_id', 'projects.status', 'projects.priority', 'projects.start_date', 'projects.end_date', 'projects.budget', 'projects.color', 'projects.is_public', 'projects.created_at', 'projects.updated_at', 'projects.deleted_at', 'owner.id', 'owner.name', 'owner.email');

        // Authorization: Admins see all projects, others see only accessible projects
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
        // Note: Admin users skip this filter and see ALL projects

        // Filters
        if ($request->filled('status')) {
            $query->where('projects.status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('projects.priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('projects.name', 'like', "%{$search}%")
                  ->orWhere('projects.description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        
        if (in_array($sortField, ['name', 'created_at', 'updated_at', 'status', 'priority'])) {
            $query->orderBy("projects.{$sortField}", $sortOrder);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $page = $request->get('page', 1);

        // Build separate count query (DEFINED HERE!)
        $countQuery = DB::table('projects')
            ->leftJoin('project_members as pm', 'projects.id', '=', 'pm.project_id')
            ->select('projects.id')
            ->whereNull('projects.deleted_at')
            ->groupBy('projects.id');

        // Apply same authorization
        if ($user->role !== 'admin') {
            $countQuery->where(function ($q) use ($user) {
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

        // Apply same filters
        if ($request->filled('status')) {
            $countQuery->where('projects.status', $request->status);
        }

        if ($request->filled('priority')) {
            $countQuery->where('projects.priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $countQuery->where(function ($q) use ($search) {
                $q->where('projects.name', 'like', "%{$search}%")
                ->orWhere('projects.description', 'like', "%{$search}%");
            });
        }

        // Count grouped results correctly
        $total = $countQuery->get()->count();

        // Apply pagination
        $projects = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $lastPage = $total > 0 ? ceil($total / $perPage) : 1;

        return response()->json([
            'success' => true,
            'data' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'description' => $project->description,
                    'status' => $project->status,
                    'priority' => $project->priority,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'budget' => $project->budget,
                    'color' => $project->color,
                    'is_public' => (bool) $project->is_public,
                    'owner' => [
                        'id' => $project->owner_id,
                        'name' => $project->owner_name,
                        'email' => $project->owner_email,
                    ],
                    'members_count' => $project->members_count ?? 0,
                    'tasks_count' => $project->tasks_count ?? 0,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ];
            }),
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'timestamp' => now()->toIso8601String(),
            ],
            'links' => [
                'first' => url()->current() . '?page=1',
                'last' => url()->current() . "?page={$lastPage}",
                'prev' => $page > 1 ? url()->current() . '?page=' . ($page - 1) : null,
                'next' => $page < $lastPage ? url()->current() . '?page=' . ($page + 1) : null,
            ],
        ]);
    }

    /**
     * Store a newly created project
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $projectData = $request->validated();
        $projectData['owner_id'] = auth('api')->id();
        
        // Generate unique slug from project name
        $baseSlug = Str::slug($projectData['name']);
        $slug = $baseSlug;
        $count = 1;
        
        // Ensure slug is unique
        while (DB::table('projects')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $count;
            $count++;
        }
        
        $projectData['slug'] = $slug;
        $projectData['created_at'] = now();
        $projectData['updated_at'] = now();

        $projectId = DB::table('projects')->insertGetId($projectData);

        // Add owner as a member with 'owner' role
        DB::table('project_members')->insert([
            'project_id' => $projectId,
            'user_id' => auth('api')->id(),
            'role' => 'owner',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get the created project with owner info
        $project = DB::table('projects')
            ->leftJoin('users as owner', 'projects.owner_id', '=', 'owner.id')
            ->select('projects.*', 'owner.name as owner_name', 'owner.email as owner_email')
            ->where('projects.id', $projectId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully',
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'status' => $project->status,
                'priority' => $project->priority,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'budget' => $project->budget,
                'color' => $project->color,
                'is_public' => (bool) $project->is_public,
                'owner' => [
                    'id' => $project->owner_id,
                    'name' => $project->owner_name,
                    'email' => $project->owner_email,
                ],
                'members_count' => 1,
                'tasks_count' => 0,
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Display the specified project
     */
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $projectData = DB::table('projects')
            ->leftJoin('users as owner', 'projects.owner_id', '=', 'owner.id')
            ->select(
                'projects.*',
                'owner.name as owner_name',
                'owner.email as owner_email',
                DB::raw('(SELECT COUNT(*) FROM project_members WHERE project_members.project_id = projects.id) as members_count'),
                DB::raw('(SELECT COUNT(*) FROM tasks WHERE tasks.project_id = projects.id AND tasks.deleted_at IS NULL) as tasks_count')
            )
            ->where('projects.id', $project->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $projectData->id,
                'name' => $projectData->name,
                'slug' => $projectData->slug,
                'description' => $projectData->description,
                'status' => $projectData->status,
                'priority' => $projectData->priority,
                'start_date' => $projectData->start_date,
                'end_date' => $projectData->end_date,
                'budget' => $projectData->budget,
                'color' => $projectData->color,
                'is_public' => (bool) $projectData->is_public,
                'owner' => [
                    'id' => $projectData->owner_id,
                    'name' => $projectData->owner_name,
                    'email' => $projectData->owner_email,
                ],
                'members_count' => $projectData->members_count,
                'tasks_count' => $projectData->tasks_count,
                'created_at' => $projectData->created_at,
                'updated_at' => $projectData->updated_at,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the specified project
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        DB::table('projects')
            ->where('id', $project->id)
            ->update(array_merge($request->validated(), ['updated_at' => now()]));

        $updatedProject = DB::table('projects')
            ->leftJoin('users as owner', 'projects.owner_id', '=', 'owner.id')
            ->select('projects.*', 'owner.name as owner_name', 'owner.email as owner_email')
            ->where('projects.id', $project->id)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully',
            'data' => [
                'id' => $updatedProject->id,
                'name' => $updatedProject->name,
                'slug' => $updatedProject->slug,
                'description' => $updatedProject->description,
                'status' => $updatedProject->status,
                'priority' => $updatedProject->priority,
                'start_date' => $updatedProject->start_date,
                'end_date' => $updatedProject->end_date,
                'budget' => $updatedProject->budget,
                'color' => $updatedProject->color,
                'is_public' => (bool) $updatedProject->is_public,
                'owner' => [
                    'id' => $updatedProject->owner_id,
                    'name' => $updatedProject->owner_name,
                    'email' => $updatedProject->owner_email,
                ],
                'created_at' => $updatedProject->created_at,
                'updated_at' => $updatedProject->updated_at,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Remove the specified project (soft delete)
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        DB::table('projects')
            ->where('id', $project->id)
            ->update(['deleted_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Restore a soft deleted project
     */
    public function restore($id): JsonResponse
    {
        $project = DB::table('projects')->where('id', $id)->first();
        
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        $projectModel = Project::withTrashed()->findOrFail($id);
        $this->authorize('restore', $projectModel);

        DB::table('projects')
            ->where('id', $id)
            ->update(['deleted_at' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Project restored successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get project tasks
     */
    public function tasks(Project $project, Request $request): JsonResponse
    {
        $this->authorize('view', $project);

        $page = $request->get('page', 1);
        $perPage = 15;

        $tasks = DB::table('tasks')
            ->leftJoin('users as assigned_user', 'tasks.assigned_to', '=', 'assigned_user.id')
            ->leftJoin('users as creator', 'tasks.created_by', '=', 'creator.id')
            ->select(
                'tasks.*',
                'assigned_user.name as assigned_to_name',
                'assigned_user.email as assigned_to_email',
                'creator.name as created_by_name',
                'creator.email as created_by_email'
            )
            ->where('tasks.project_id', $project->id)
            ->whereNull('tasks.deleted_at')
            ->orderBy('tasks.created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $total = DB::table('tasks')
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->count();

        $lastPage = $total > 0 ? ceil($total / $perPage) : 1;

        return response()->json([
            'success' => true,
            'data' => $tasks->map(function ($task) {
                return [
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
                    'order' => $task->order,
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
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get project members
     */
    public function members(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $members = DB::table('project_members')
            ->join('users', 'project_members.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.role as user_role',
                'project_members.role as project_role',
                'project_members.joined_at'
            )
            ->where('project_members.project_id', $project->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'user_role' => $member->user_role,
                    'project_role' => $member->project_role,
                    'joined_at' => $member->joined_at,
                ];
            }),
            'meta' => [
                'total' => $members->count(),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Add a member to the project
     */
    public function addMember(AddProjectMemberRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $exists = DB::table('project_members')
            ->where('project_id', $project->id)
            ->where('user_id', $request->user_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a member of this project',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'ALREADY_MEMBER',
                ],
            ], 422);
        }

        DB::table('project_members')->insert([
            'project_id' => $project->id,
            'user_id' => $request->user_id,
            'role' => $request->role,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Member added successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Update member role
     */
    public function updateMember(UpdateProjectMemberRequest $request, Project $project, $userId): JsonResponse
    {
        $this->authorize('update', $project);

        DB::table('project_members')
            ->where('project_id', $project->id)
            ->where('user_id', $userId)
            ->update([
                'role' => $request->role,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Member role updated successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Remove member from project
     */
    public function removeMember(Project $project, $userId): JsonResponse
    {
        $this->authorize('update', $project);

        DB::table('project_members')
            ->where('project_id', $project->id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get project activities
     */
    public function activities(Project $project, Request $request): JsonResponse
    {
        $this->authorize('view', $project);

        $page = $request->get('page', 1);
        $perPage = 15;

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
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $total = DB::table('activities')
            ->where('subject_type', 'App\\Models\\Project')
            ->where('subject_id', $project->id)
            ->count();

        $lastPage = $total > 0 ? ceil($total / $perPage) : 1;

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
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Archive project
     */
    public function archive(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        DB::table('projects')
            ->where('id', $project->id)
            ->update([
                'status' => 'archived',
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Project archived successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Unarchive project
     */
    public function unarchive(Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        DB::table('projects')
            ->where('id', $project->id)
            ->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Project unarchived successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
