<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Mention;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MentionService
{
    /**
     * Extract mentioned user IDs from comment content.
     * Looks for @username or @[User Name] patterns.
     *
     * @param string $content
     * @return array Array of user IDs
     */
    public function extractMentions(string $content): array
    {
        // Pattern matches @username or @[Display Name]
        preg_match_all('/@\[([^\]]+)\]\((\d+)\)/', $content, $matches);
        
        if (empty($matches[2])) {
            return [];
        }

        // Return unique user IDs
        return array_unique(array_map('intval', $matches[2]));
    }

    /**
     * Create mentions for a comment.
     *
     * @param Comment $comment
     * @param array $userIds Array of user IDs to mention
     * @return int Number of mentions created
     */
    public function createMentions(Comment $comment, array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $mentionedByUserId = $comment->user_id;
        $createdCount = 0;

        DB::transaction(function () use ($comment, $userIds, $mentionedByUserId, &$createdCount) {
            foreach ($userIds as $userId) {
                // Skip if mentioning self
                if ($userId == $mentionedByUserId) {
                    continue;
                }

                // Verify user exists
                if (!User::find($userId)) {
                    continue;
                }

                // Create mention (will skip if already exists due to unique constraint)
                try {
                    Mention::create([
                        'comment_id' => $comment->id,
                        'mentioned_user_id' => $userId,
                        'mentioned_by_user_id' => $mentionedByUserId,
                        'is_read' => false,
                    ]);
                    $createdCount++;
                } catch (\Exception $e) {
                    // Duplicate mention, skip
                    continue;
                }
            }
        });

        return $createdCount;
    }

    /**
     * Process mentions in comment content and create mention records.
     *
     * @param Comment $comment
     * @return int Number of mentions created
     */
    public function processMentions(Comment $comment): int
    {
        $userIds = $this->extractMentions($comment->content);
        return $this->createMentions($comment, $userIds);
    }

    /**
     * Get unread mentions for a user.
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnreadMentions(int $userId)
    {
        return Mention::with(['comment.commentable', 'comment.user', 'mentionedBy'])
            ->forUser($userId)
            ->unread()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mark mentions as read.
     *
     * @param array $mentionIds
     * @return int Number of mentions marked as read
     */
    public function markMentionsAsRead(array $mentionIds): int
    {
        return Mention::whereIn('id', $mentionIds)->update(['is_read' => true]);
    }

    /**
     * Get mention count for a user.
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadMentionCount(int $userId): int
    {
        return Mention::forUser($userId)->unread()->count();
    }

    /**
     * Format content for display (convert mention format to HTML).
     *
     * @param string $content
     * @return string
     */
    public function formatMentionsForDisplay(string $content): string
    {
        // Convert @[Name](id) to <span class="mention">@Name</span>
        return preg_replace(
            '/@\[([^\]]+)\]\(\d+\)/',
            '<span class="mention">@$1</span>',
            $content
        );
    }
}
