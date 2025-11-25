<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'commentable_id',
        'commentable_type',
        'user_id',
        'content',
    ];

    /**
     * Get the parent commentable model (Project or Task).
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all mentions for this comment.
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }

    /**
     * Get users mentioned in this comment.
     */
    public function mentionedUsers()
    {
        return $this->belongsToMany(User::class, 'mentions', 'comment_id', 'mentioned_user_id')
            ->withTimestamps();
    }

    /**
     * Scope to get recent comments.
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
