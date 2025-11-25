<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MentionController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskDependencyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication Routes (Public)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        // Protected authentication routes
        Route::middleware('auth:api')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            
            // Profile & Settings
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
            Route::put('settings', [AuthController::class, 'updateSettings']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Protected API Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {

        /*
        |----------------------------------------------------------------------
        | User Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']); // List all users (admin only)
            Route::post('/', [UserController::class, 'store']); // Create new user (admin only)
            Route::get('statistics', [UserController::class, 'statistics']); // Get user statistics (admin only)
            Route::get('{user}', [UserController::class, 'show']); // Get user details
            Route::put('{user}', [UserController::class, 'update']); // Update user
            Route::delete('{user}', [UserController::class, 'destroy']); // Delete user (soft delete)
        });

        /*
        |----------------------------------------------------------------------
        | Project Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('projects')->group(function () {
            Route::get('/', [ProjectController::class, 'index']); // List all accessible projects
            Route::post('/', [ProjectController::class, 'store']); // Create new project
            Route::get('{project}', [ProjectController::class, 'show']); // Get project details
            Route::put('{project}', [ProjectController::class, 'update']); // Update project
            Route::delete('{project}', [ProjectController::class, 'destroy']); // Delete project (soft delete)
            Route::post('{id}/restore', [ProjectController::class, 'restore']); // Restore deleted project
            
            // Project Tasks
            Route::get('{project}/tasks', [ProjectController::class, 'tasks']); // Get project tasks
            
            // Project Members
            Route::get('{project}/members', [ProjectController::class, 'members']); // Get project members
            Route::post('{project}/members', [ProjectController::class, 'addMember']); // Add member to project
            Route::put('{project}/members/{userId}', [ProjectController::class, 'updateMember']); // Update member role
            Route::delete('{project}/members/{userId}', [ProjectController::class, 'removeMember']); // Remove member
            
            // Project Comments
            Route::get('{project}/comments', [CommentController::class, 'projectComments']); // Get project comments
            Route::post('{project}/comments', [CommentController::class, 'addToProject']); // Add comment to project
            
            // Project Attachments
            Route::get('{project}/attachments', [AttachmentController::class, 'projectAttachments']); // Get project attachments
            Route::post('{project}/attachments', [AttachmentController::class, 'uploadToProject']); // Upload file to project
            
            // Project Activities
            Route::get('{project}/activities', [ActivityController::class, 'projectActivities']); // Get project activity log
            
            // Project Status
            Route::post('{project}/archive', [ProjectController::class, 'archive']); // Archive project
            Route::post('{project}/unarchive', [ProjectController::class, 'unarchive']); // Unarchive project
            
            // Project Reports
            Route::get('{project}/reports/progress', [ReportController::class, 'projectProgress']); // Get project progress report
            Route::get('{project}/reports/workload', [ReportController::class, 'teamWorkload']); // Get team workload report
            Route::get('{project}/reports/trends', [ReportController::class, 'completionTrends']); // Get completion trends
        });

        /*
        |----------------------------------------------------------------------
        | Task Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('tasks')->group(function () {
            Route::get('/', [TaskController::class, 'index']); // List all accessible tasks
            Route::post('/', [TaskController::class, 'store']); // Create new task
            Route::get('{task}', [TaskController::class, 'show']); // Get task details
            Route::put('{task}', [TaskController::class, 'update']); // Update task
            Route::delete('{task}', [TaskController::class, 'destroy']); // Delete task (soft delete)
            
            // Task Assignment
            Route::post('{task}/assign', [TaskController::class, 'assign']); // Assign task to user
            
            // Subtasks
            Route::post('{task}/subtasks', [TaskController::class, 'createSubtask']); // Create subtask
            Route::get('{task}/subtasks', [TaskController::class, 'subtasks']); // Get subtasks
            
            // Task Status & Priority
            Route::put('{task}/status', [TaskController::class, 'updateStatus']); // Update task status
            Route::put('{task}/priority', [TaskController::class, 'updatePriority']); // Update task priority
            
            // Task Tags
            Route::post('{task}/tags', [TaskController::class, 'addTags']); // Add tags to task
            Route::delete('{task}/tags/{tagId}', [TaskController::class, 'removeTag']); // Remove tag from task
            
            // Task Comments
            Route::get('{task}/comments', [CommentController::class, 'taskComments']); // Get task comments
            Route::post('{task}/comments', [CommentController::class, 'addToTask']); // Add comment to task
            
            // Task Attachments
            Route::get('{task}/attachments', [AttachmentController::class, 'taskAttachments']); // Get task attachments
            Route::post('{task}/attachments', [AttachmentController::class, 'uploadToTask']); // Upload file to task
            
            // Task Activities
            Route::get('{task}/activities', [ActivityController::class, 'taskActivities']); // Get task activities
        });

        /*
        |----------------------------------------------------------------------
        | Comment Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('comments')->group(function () {
            Route::get('{comment}', [CommentController::class, 'show']); // Get comment
            Route::put('{comment}', [CommentController::class, 'update']); // Update comment
            Route::delete('{comment}', [CommentController::class, 'destroy']); // Delete comment
        });

        /*
        |----------------------------------------------------------------------
        | Tag Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('tags')->group(function () {
            Route::get('/', [TagController::class, 'index']); // List all tags
            Route::post('/', [TagController::class, 'store']); // Create tag
            Route::get('{tag}', [TagController::class, 'show']); // Get tag details
            Route::put('{tag}', [TagController::class, 'update']); // Update tag
            Route::delete('{tag}', [TagController::class, 'destroy']); // Delete tag
            Route::get('{tag}/tasks', [TagController::class, 'tasks']); // Get tasks with this tag
            Route::get('{tag}/projects', [TagController::class, 'projects']); // Get projects with this tag
        });

        /*
        |----------------------------------------------------------------------
        | Attachment Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('attachments')->group(function () {
            Route::get('{attachment}', [AttachmentController::class, 'show'])->name('attachments.show'); // Get attachment details
            Route::get('{attachment}/preview', [AttachmentController::class, 'preview'])->name('attachments.preview'); // Preview attachment inline
            Route::get('{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download'); // Download attachment
            Route::delete('{attachment}', [AttachmentController::class, 'destroy']); // Delete attachment
        });

        /*
        |----------------------------------------------------------------------
        | Activity Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('activities')->group(function () {
            Route::get('/', [ActivityController::class, 'index']); // Get user's activity feed
        });

        /*
        |----------------------------------------------------------------------
        | Mention Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('mentions')->group(function () {
            Route::get('/', [MentionController::class, 'index']); // Get all mentions for authenticated user
            Route::get('unread-count', [MentionController::class, 'unreadCount']); // Get unread mention count
            Route::post('mark-as-read', [MentionController::class, 'markAsRead']); // Mark mentions as read
            Route::post('mark-all-read', [MentionController::class, 'markAllAsRead']); // Mark all mentions as read
        });

        /*
        |----------------------------------------------------------------------
        | Task Dependency Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('tasks/{task}')->group(function () {
            Route::get('dependencies', [TaskDependencyController::class, 'index']); // Get task dependencies
            Route::post('dependencies', [TaskDependencyController::class, 'store']); // Add dependency
            Route::get('dependency-tree', [TaskDependencyController::class, 'tree']); // Get dependency tree
            Route::get('dependents', [TaskDependencyController::class, 'dependents']); // Get tasks that depend on this task
        });
        
        Route::prefix('dependencies')->group(function () {
            Route::delete('{dependency}', [TaskDependencyController::class, 'destroy']); // Remove dependency
        });

        /*
        |----------------------------------------------------------------------
        | Dashboard & Analytics Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('dashboard')->group(function () {
            Route::get('stats', [DashboardController::class, 'stats']); // Get overview statistics
            Route::get('recent-projects', [DashboardController::class, 'recentProjects']); // Get recent projects
            Route::get('recent-tasks', [DashboardController::class, 'recentTasks']); // Get recent tasks
        });

        /*
        |----------------------------------------------------------------------
        | Reports Routes
        |----------------------------------------------------------------------
        */
        Route::prefix('reports')->group(function () {
            Route::get('dashboard', [ReportController::class, 'dashboard']); // Get overall dashboard
        });

        /*
        |----------------------------------------------------------------------
        | TIME TRACKING ROUTES (NEW)
        |----------------------------------------------------------------------
        */
        Route::prefix('time-entries')->group(function () {
            Route::get('/', [TimeEntryController::class, 'index']); // List time entries
            Route::get('today', [TimeEntryController::class, 'today']); // Get today's entries
            Route::get('active', [TimeEntryController::class, 'getActiveTimer']); // Get active timer
            Route::get('reports', [TimeEntryController::class, 'reports']); // Get time reports
            Route::post('/', [TimeEntryController::class, 'store']); // Create time entry
            Route::post('start', [TimeEntryController::class, 'startTimer']); // Start timer
            Route::post('{id}/stop', [TimeEntryController::class, 'stopTimer']); // Stop timer
            Route::get('{id}', [TimeEntryController::class, 'show']); // Get time entry
            Route::put('{id}', [TimeEntryController::class, 'update']); // Update time entry
            Route::delete('{id}', [TimeEntryController::class, 'destroy']); // Delete time entry
        });

        /*
        |----------------------------------------------------------------------
        | NOTIFICATION ROUTES (NEW)
        |----------------------------------------------------------------------
        */
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']); // List notifications
            Route::get('unread-count', [NotificationController::class, 'unreadCount']); // Get unread count
            Route::get('stats', [NotificationController::class, 'stats']); // Get stats
            Route::get('settings', [NotificationController::class, 'getSettings']); // Get settings
            Route::put('settings', [NotificationController::class, 'updateSettings']); // Update settings
            Route::post('test', [NotificationController::class, 'sendTestNotification']); // Send test
            Route::post('push/subscribe', [NotificationController::class, 'subscribeToPush']); // Subscribe push
            Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead']); // Mark all read
            Route::delete('delete-all-read', [NotificationController::class, 'deleteAllRead']); // Delete all read
            Route::get('{id}', [NotificationController::class, 'show']); // Get notification
            Route::post('{id}/read', [NotificationController::class, 'markAsRead']); // Mark as read
            Route::post('{id}/unread', [NotificationController::class, 'markAsUnread']); // Mark as unread
            Route::delete('{id}', [NotificationController::class, 'destroy']); // Delete notification
        });

        /*
        |----------------------------------------------------------------------
        | FILE MANAGEMENT ROUTES (NEW)
        |----------------------------------------------------------------------
        */
        Route::prefix('files')->group(function () {
            Route::get('/', [FileController::class, 'index']); // List files
            Route::post('/', [FileController::class, 'store']); // Upload file
            Route::get('stats', [FileController::class, 'stats']); // Get stats
            Route::post('bulk-delete', [FileController::class, 'bulkDelete']); // Bulk delete
            Route::post('bulk-download', [FileController::class, 'bulkDownload']); // Bulk download
            Route::post('bulk-categorize', [FileController::class, 'bulkCategorize']); // Bulk categorize
            Route::get('{id}', [FileController::class, 'show']); // Get file
            Route::put('{id}', [FileController::class, 'update']); // Update file
            Route::delete('{id}', [FileController::class, 'destroy']); // Delete file
            Route::get('{id}/download', [FileController::class, 'download']); // Download file
            Route::get('{id}/preview', [FileController::class, 'preview']); // Preview file
            Route::get('{id}/versions', [FileController::class, 'versions']); // Get versions
            Route::post('{id}/versions', [FileController::class, 'uploadVersion']); // Upload new version
            Route::post('{fileId}/versions/{versionId}/restore', [FileController::class, 'restoreVersion']); // Restore version
            Route::delete('{fileId}/versions/{versionId}', [FileController::class, 'deleteVersion']); // Delete version
        });

        /*
        |----------------------------------------------------------------------
        | FILE CATEGORY ROUTES (NEW)
        |----------------------------------------------------------------------
        */
        Route::prefix('file-categories')->group(function () {
            Route::get('/', [FileController::class, 'getCategories']); // List categories
            Route::post('/', [FileController::class, 'storeCategory']); // Create category
            Route::put('{id}', [FileController::class, 'updateCategory']); // Update category
            Route::delete('{id}', [FileController::class, 'destroyCategory']); // Delete category
        });

    });
});
