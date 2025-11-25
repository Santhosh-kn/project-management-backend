<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeLog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'task_id',
        'user_id',
        'hours',
        'description',
        'log_date',
        'started_at',
        'stopped_at',
        'is_running',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'hours' => 'decimal:2',
        'log_date' => 'date',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'is_running' => 'boolean',
    ];

    /**
     * Get the task that owns the time log.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user that owns the time log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate hours from started_at and stopped_at timestamps.
     */
    public function calculateHours(): float
    {
        if (!$this->started_at || !$this->stopped_at) {
            return 0;
        }

        $diffInSeconds = $this->stopped_at->diffInSeconds($this->started_at);
        return round($diffInSeconds / 3600, 2); // Convert to hours with 2 decimals
    }

    /**
     * Get duration in human readable format.
     */
    public function getDurationAttribute(): string
    {
        $hours = floor($this->hours);
        $minutes = round(($this->hours - $hours) * 60);
        
        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }
}
