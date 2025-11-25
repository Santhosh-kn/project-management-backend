<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Services\MentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    /**
     * Mention service instance
     */
    protected MentionService $mentionService;

    /**
     * Create a new controller instance.
     */
    public function __construct(MentionService $mentionService)
    {
        $this->mentionService = $mentionService;
    }
    /**
     * Add comment to a project
     */
    public function addToProject(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $commentId = DB::table('comments')->insertGetId([
            'commentable_id' => $project->id,
            'commentable_type' => 'App\\Models\\Project',
            'user_id' => auth()->id(),
            'content' => $validated['content'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get comment with user info
        $comment = DB::table('comments')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->select(
                'comments.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('comments.id', $commentId)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'user' => [
                    'id' => $comment->user_id,
                    'name' => $comment->user_name,
                    'email' => $comment->user_email,
                ],
                'commentable_type' => $comment->commentable_type,
                'commentable_id' => $comment->commentable_id,
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
            ],
            'message' => 'Comment added successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 201);
    }

    /**
     * Add comment to a task
     */
    public function addToTask(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $commentId = DB::table('comments')->insertGetId([
            'commentable_id' => $task->id,
            'commentable_type' => 'App\\Models\\Task',
            'user_id' => auth()->id(),
            'content' => $validated['content'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Process mentions
        $comment = Comment::find($commentId);
        if ($comment) {
            $this->mentionService->processMentions($comment);
        }

        // Get comment with user info
        $comment = DB::table('comments')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->select(
                'comments.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('comments.id', $commentId)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $comment->id,
                'content' => $comment->content,
                'user' => [
                    'id' => $comment->user_id,
                    'name' => $comment->user_name,
                    'email' => $comment->user_email,
                ],
                'commentable_type' => $comment->commentable_type,
                'commentable_id' => $comment->commentable_id,
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
            ],
            'message' => 'Comment added successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 201);
    }

    /**
     * Get project comments (using JOIN)
     */
    public function projectComments(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $comments = DB::table('comments')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->select(
                'comments.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('comments.commentable_type', 'App\\Models\\Project')
            ->where('comments.commentable_id', $project->id)
            ->whereNull('comments.deleted_at')
            ->orderBy('comments.created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'user' => [
                        'id' => $comment->user_id,
                        'name' => $comment->user_name,
                        'email' => $comment->user_email,
                    ],
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                ];
            }),
            'meta' => [
                'total' => $comments->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get task comments (using JOIN)
     */
    public function taskComments(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $comments = DB::table('comments')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->select(
                'comments.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('comments.commentable_type', 'App\\Models\\Task')
            ->where('comments.commentable_id', $task->id)
            ->whereNull('comments.deleted_at')
            ->orderBy('comments.created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'user' => [
                        'id' => $comment->user_id,
                        'name' => $comment->user_name,
                        'email' => $comment->user_email,
                    ],
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                ];
            }),
            'meta' => [
                'total' => $comments->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get comment details (using JOIN)
     */
    public function show(Comment $comment): JsonResponse
    {
        $commentData = DB::table('comments')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->select(
                'comments.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('comments.id', $comment->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $commentData->id,
                'content' => $commentData->content,
                'user' => [
                    'id' => $commentData->user_id,
                    'name' => $commentData->user_name,
                    'email' => $commentData->user_email,
                ],
                'commentable_type' => $commentData->commentable_type,
                'commentable_id' => $commentData->commentable_id,
                'created_at' => $commentData->created_at,
                'updated_at' => $commentData->updated_at,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Update comment
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        // Only the comment author can update
        if (auth()->id() !== $comment->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this comment',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'UNAUTHORIZED',
                ]
            ], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        DB::table('comments')
            ->where('id', $comment->id)
            ->update([
                'content' => $validated['content'],
                'updated_at' => now(),
            ]);

        // Get updated comment with user info
        $updatedComment = DB::table('comments')
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->select(
                'comments.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('comments.id', $comment->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $updatedComment->id,
                'content' => $updatedComment->content,
                'user' => [
                    'id' => $updatedComment->user_id,
                    'name' => $updatedComment->user_name,
                    'email' => $updatedComment->user_email,
                ],
                'commentable_type' => $updatedComment->commentable_type,
                'commentable_id' => $updatedComment->commentable_id,
                'created_at' => $updatedComment->created_at,
                'updated_at' => $updatedComment->updated_at,
            ],
            'message' => 'Comment updated successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Delete comment (soft delete)
     */
    public function destroy(Comment $comment): JsonResponse
    {
        // Only the comment author or admin can delete
        if (auth()->id() !== $comment->user_id && auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this comment',
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'error_code' => 'UNAUTHORIZED',
                ]
            ], 403);
        }

        DB::table('comments')
            ->where('id', $comment->id)
            ->update(['deleted_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 204);
    }
}
