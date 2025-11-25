<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mention extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'comment_id',
        'mentioned_user_id',
        'mentioned_by_user_id',
        'is_read',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the comment that contains this mention.
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * Get the user who was mentioned.
     */
    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }

    /**
     * Get the user who created the mention.
     */
    public function mentionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_by_user_id');
    }

    /**
     * Scope to get unread mentions.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to get mentions for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('mentioned_user_id', $userId);
    }

    /**
     * Mark mention as read.
     */
    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }

    /**
     * Mark mention as unread.
     */
    public function markAsUnread(): bool
    {
        return $this->update(['is_read' => false]);
    }
}
