<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    /**
     * Display a listing of tags (using JOIN)
     */
    public function index(): JsonResponse
    {
        $tags = DB::table('tags')
            ->select(
                'tags.*',
                DB::raw('(SELECT COUNT(*) FROM taggables WHERE taggables.tag_id = tags.id AND taggables.taggable_type = "App\\\\Models\\\\Task") as tasks_count'),
                DB::raw('(SELECT COUNT(*) FROM taggables WHERE taggables.tag_id = tags.id AND taggables.taggable_type = "App\\\\Models\\\\Project") as projects_count')
            )
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'tasks_count' => $tag->tasks_count,
                    'projects_count' => $tag->projects_count,
                    'created_at' => $tag->created_at,
                    'updated_at' => $tag->updated_at,
                ];
            }),
            'meta' => [
                'total' => $tags->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Store a newly created tag
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
        ]);

        // Set default color if not provided
        if (!isset($validated['color'])) {
            $validated['color'] = '#3B82F6';
        }

        $tagId = DB::table('tags')->insertGetId([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tag = DB::table('tags')->where('id', $tagId)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'created_at' => $tag->created_at,
                'updated_at' => $tag->updated_at,
            ],
            'message' => 'Tag created successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 201);
    }

    /**
     * Display the specified tag
     */
    public function show(Tag $tag): JsonResponse
    {
        $tagData = DB::table('tags')
            ->select(
                'tags.*',
                DB::raw('(SELECT COUNT(*) FROM taggables WHERE taggables.tag_id = tags.id AND taggables.taggable_type = "App\\\\Models\\\\Task") as tasks_count'),
                DB::raw('(SELECT COUNT(*) FROM taggables WHERE taggables.tag_id = tags.id AND taggables.taggable_type = "App\\\\Models\\\\Project") as projects_count')
            )
            ->where('tags.id', $tag->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tagData->id,
                'name' => $tagData->name,
                'color' => $tagData->color,
                'tasks_count' => $tagData->tasks_count,
                'projects_count' => $tagData->projects_count,
                'created_at' => $tagData->created_at,
                'updated_at' => $tagData->updated_at,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Update the specified tag
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('tags', 'name')->ignore($tag->id)],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
        ]);

        DB::table('tags')
            ->where('id', $tag->id)
            ->update(array_merge($validated, ['updated_at' => now()]));

        $updatedTag = DB::table('tags')->where('id', $tag->id)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $updatedTag->id,
                'name' => $updatedTag->name,
                'color' => $updatedTag->color,
                'created_at' => $updatedTag->created_at,
                'updated_at' => $updatedTag->updated_at,
            ],
            'message' => 'Tag updated successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Remove the specified tag
     */
    public function destroy(Tag $tag): JsonResponse
    {
        // Delete from taggables first
        DB::table('taggables')
            ->where('tag_id', $tag->id)
            ->delete();

        // Delete the tag
        DB::table('tags')
            ->where('id', $tag->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag deleted successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 204);
    }

    /**
     * Get all tasks with the specified tag (using JOIN)
     */
    public function tasks(Tag $tag): JsonResponse
    {
        $tasks = DB::table('taggables')
            ->join('tasks', 'taggables.taggable_id', '=', 'tasks.id')
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
            ->where('taggables.tag_id', $tag->id)
            ->where('taggables.taggable_type', 'App\\Models\\Task')
            ->whereNull('tasks.deleted_at')
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
     * Get all projects with the specified tag (using JOIN)
     */
    public function projects(Tag $tag): JsonResponse
    {
        $projects = DB::table('taggables')
            ->join('projects', 'taggables.taggable_id', '=', 'projects.id')
            ->join('users as owner', 'projects.owner_id', '=', 'owner.id')
            ->select(
                'projects.*',
                'owner.name as owner_name',
                'owner.email as owner_email'
            )
            ->where('taggables.tag_id', $tag->id)
            ->where('taggables.taggable_type', 'App\\Models\\Project')
            ->whereNull('projects.deleted_at')
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
}
