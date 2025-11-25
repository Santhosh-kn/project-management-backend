<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'email_notifications',
        'push_notifications',
        'task_assigned',
        'task_completed',
        'task_commented',
        'task_mentioned',
        'task_due_soon',
        'project_updates',
        'digest_frequency',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'task_assigned' => 'boolean',
        'task_completed' => 'boolean',
        'task_commented' => 'boolean',
        'task_mentioned' => 'boolean',
        'task_due_soon' => 'boolean',
        'project_updates' => 'boolean',
        'quiet_hours_enabled' => 'boolean',
    ];
}
