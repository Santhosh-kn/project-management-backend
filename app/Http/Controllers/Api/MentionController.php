<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mention;
use App\Services\MentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentionController extends Controller
{
    protected MentionService $mentionService;

    public function __construct(MentionService $mentionService)
    {
        $this->mentionService = $mentionService;
    }

    /**
     * Get all mentions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Mention::with(['comment.commentable', 'comment.user', 'mentionedBy'])
            ->forUser(auth()->id())
            ->orderBy('created_at', 'desc');

        // Filter by read status
        if ($request->has('unread_only') && $request->boolean('unread_only')) {
            $query->unread();
        }

        $mentions = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $mentions->map(function ($mention) {
                return [
                    'id' => $mention->id,
                    'comment' => [
                        'id' => $mention->comment->id,
                        'content' => $mention->comment->content,
                        'user' => [
                            'id' => $mention->comment->user->id,
                            'name' => $mention->comment->user->name,
                            'email' => $mention->comment->user->email,
                        ],
                        'commentable_type' => $mention->comment->commentable_type,
                        'commentable_id' => $mention->comment->commentable_id,
                        'commentable' => $this->formatCommentable($mention->comment->commentable),
                    ],
                    'mentioned_by' => [
                        'id' => $mention->mentionedBy->id,
                        'name' => $mention->mentionedBy->name,
                        'email' => $mention->mentionedBy->email,
                    ],
                    'is_read' => $mention->is_read,
                    'created_at' => $mention->created_at,
                ];
            }),
            'meta' => [
                'total' => $mentions->total(),
                'current_page' => $mentions->currentPage(),
                'per_page' => $mentions->perPage(),
                'last_page' => $mentions->lastPage(),
                'unread_count' => $this->mentionService->getUnreadMentionCount(auth()->id()),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get unread mention count.
     */
    public function unreadCount(): JsonResponse
    {
        $count = $this->mentionService->getUnreadMentionCount(auth()->id());

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Mark mentions as read.
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mention_ids' => ['required', 'array'],
            'mention_ids.*' => ['required', 'integer', 'exists:mentions,id'],
        ]);

        // Verify mentions belong to authenticated user
        $mentions = Mention::whereIn('id', $validated['mention_ids'])
            ->forUser(auth()->id())
            ->get();

        $count = $mentions->count();
        
        foreach ($mentions as $mention) {
            $mention->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'marked_count' => $count,
                'unread_count' => $this->mentionService->getUnreadMentionCount(auth()->id()),
            ],
            'message' => "$count mention(s) marked as read",
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Mark all mentions as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $count = Mention::forUser(auth()->id())
            ->unread()
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'data' => [
                'marked_count' => $count,
                'unread_count' => 0,
            ],
            'message' => "$count mention(s) marked as read",
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Format commentable data based on type.
     */
    private function formatCommentable($commentable): array
    {
        if (!$commentable) {
            return [];
        }

        $data = [
            'id' => $commentable->id,
        ];

        if ($commentable instanceof \App\Models\Task) {
            $data['title'] = $commentable->title;
            $data['type'] = 'task';
            $data['project_id'] = $commentable->project_id;
        } elseif ($commentable instanceof \App\Models\Project) {
            $data['name'] = $commentable->name;
            $data['type'] = 'project';
        }

        return $data;
    }
}
