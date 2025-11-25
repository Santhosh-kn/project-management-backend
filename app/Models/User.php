<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'is_active',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a manager
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Relationships
     */

    /**
     * Get projects owned by the user
     */
    public function ownedProjects()
    {
        return $this->hasMany(\App\Models\Project::class, 'owner_id');
    }

    /**
     * Get projects the user is a member of
     */
    public function projects()
    {
        return $this->belongsToMany(\App\Models\Project::class, 'project_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Alias for projects() - Get projects the user is a member of
     */
    public function memberProjects()
    {
        return $this->projects();
    }

    /**
     * Get tasks assigned to the user
     */
    public function assignedTasks()
    {
        return $this->hasMany(\App\Models\Task::class, 'assigned_to');
    }

    /**
     * Get tasks created by the user
     */
    public function createdTasks()
    {
        return $this->hasMany(\App\Models\Task::class, 'created_by');
    }

    /**
     * Get comments created by the user
     */
    public function comments()
    {
        return $this->hasMany(\App\Models\Comment::class);
    }

    /**
     * Get activities performed by the user
     */
    public function activities()
    {
        return $this->hasMany(\App\Models\Activity::class);
    }

    /**
     * Get mentions where this user was mentioned
     */
    public function mentions()
    {
        return $this->hasMany(\App\Models\Mention::class, 'mentioned_user_id');
    }

    /**
     * Get unread mentions for this user
     */
    public function unreadMentions()
    {
        return $this->mentions()->where('is_read', false);
    }

    /**
     * Get mentions created by this user
     */
    public function createdMentions()
    {
        return $this->hasMany(\App\Models\Mention::class, 'mentioned_by_user_id');
    }
}
