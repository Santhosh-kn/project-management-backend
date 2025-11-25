<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeLogRequest;
use App\Http\Requests\UpdateTimeLogRequest;
use App\Models\TimeLog;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeLogController extends Controller
{
    /**
     * Display a listing of time logs (with filters)
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $page = $request->get('page', 1);
        $perPage = min($request->get('per_page', 15), 100);

        // Build query
        $query = DB::table('time_logs')
            ->join('tasks', 'time_logs.task_id', '=', 'tasks.id')
            ->join('users', 'time_logs.user_id', '=', 'users.id')
            ->leftJoin('projects', 'tasks.project_id', '=', 'projects.id')
            ->select(
                'time_logs.*',
                'tasks.title as task_title',
                'tasks.project_id',
                'users.name as user_name',
                'users.email as user_email',
                'projects.name as project_name'
            )
            ->whereNull('time_logs.deleted_at');

        // Authorization: Filter by user unless admin
        if ($user->role !== 'admin') {
            $query->where('time_logs.user_id', $user->id);
        }

        // Filters
        if ($request->filled('task_id')) {
            $query->where('time_logs.task_id', $request->task_id);
        }

        if ($request->filled('user_id')) {
            $query->where('time_logs.user_id', $request->user_id);
        }

        if ($request->filled('project_id')) {
            $query->where('tasks.project_id', $request->project_id);
        }

        if ($request->filled('date_from')) {
            $query->where('time_logs.log_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('time_logs.log_date', '<=', $request->date_to);
        }

        if ($request->filled('is_running')) {
            $query->where('time_logs.is_running', $request->is_running);
        }

        // Sorting
        $sortField = $request->get('sort', 'log_date');
        $sortOrder = $request->get('order', 'desc');
        
        if (in_array($sortField, ['log_date', 'hours', 'created_at'])) {
            $query->orderBy("time_logs.{$sortField}", $sortOrder);
        }

        // Get total count
        $totalQuery = clone $query;
        $total = $totalQuery->count();

        // Apply pagination
        $timeLogs = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $lastPage = ceil($total / $perPage);

        return response()->json([
            'success' => true,
            'data' => $timeLogs,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Store a manually created time log
     */
    public function store(StoreTimeLogRequest $request): JsonResponse
    {
        $timeLog = TimeLog::create([
            'task_id' => $request->task_id,
            'user_id' => auth()->id(),
            'hours' => $request->hours,
            'description' => $request->description,
            'log_date' => $request->log_date,
            'is_running' => false,
        ]);

        // Update task's actual_hours
        $this->updateTaskActualHours($request->task_id);

        return response()->json([
            'success' => true,
            'message' => 'Time log created successfully',
            'data' => $timeLog->load(['task', 'user']),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Display the specified time log
     */
    public function show(TimeLog $timeLog): JsonResponse
    {
        // Authorization: Only owner or admin can view
        if ($timeLog->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $timeLog->load(['task', 'user']),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the specified time log
     */
    public function update(UpdateTimeLogRequest $request, TimeLog $timeLog): JsonResponse
    {
        // Authorization: Only owner or admin can update
        if ($timeLog->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Cannot update running timer
        if ($timeLog->is_running) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a running timer. Please stop it first.',
            ], 400);
        }

        $timeLog->update($request->validated());

        // Update task's actual_hours
        $this->updateTaskActualHours($timeLog->task_id);

        return response()->json([
            'success' => true,
            'message' => 'Time log updated successfully',
            'data' => $timeLog->fresh()->load(['task', 'user']),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Remove the specified time log
     */
    public function destroy(TimeLog $timeLog): JsonResponse
    {
        // Authorization: Only owner or admin can delete
        if ($timeLog->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Cannot delete running timer
        if ($timeLog->is_running) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a running timer. Please stop it first.',
            ], 400);
        }

        $taskId = $timeLog->task_id;
        $timeLog->delete();

        // Update task's actual_hours
        $this->updateTaskActualHours($taskId);

        return response()->json([
            'success' => true,
            'message' => 'Time log deleted successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Start a timer for a task
     */
    public function startTimer(Request $request, Task $task): JsonResponse
    {
        $user = auth()->user();

        // Check if user already has a running timer
        $existingTimer = TimeLog::where('user_id', $user->id)
            ->where('is_running', true)
            ->first();

        if ($existingTimer) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a timer running. Please stop it first.',
                'data' => [
                    'running_timer' => $existingTimer->load(['task']),
                ],
            ], 400);
        }

        // Create new timer
        $timeLog = TimeLog::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'hours' => 0,
            'log_date' => now()->toDateString(),
            'started_at' => now(),
            'is_running' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Timer started successfully',
            'data' => $timeLog->load(['task', 'user']),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Stop a running timer
     */
    public function stopTimer(TimeLog $timeLog): JsonResponse
    {
        // Authorization: Only owner can stop their timer
        if ($timeLog->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check if timer is actually running
        if (!$timeLog->is_running) {
            return response()->json([
                'success' => false,
                'message' => 'This timer is not running',
            ], 400);
        }

        // Stop timer
        $timeLog->stopped_at = now();
        $timeLog->is_running = false;
        $timeLog->hours = $timeLog->calculateHours();
        $timeLog->save();

        // Update task's actual_hours
        $this->updateTaskActualHours($timeLog->task_id);

        return response()->json([
            'success' => true,
            'message' => 'Timer stopped successfully',
            'data' => $timeLog->fresh()->load(['task', 'user']),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get user's active (running) timer
     */
    public function activeTimer(): JsonResponse
    {
        $activeTimer = TimeLog::where('user_id', auth()->id())
            ->where('is_running', true)
            ->with(['task', 'user'])
            ->first();

        return response()->json([
            'success' => true,
            'data' => $activeTimer,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get all time logs for a specific task
     */
    public function taskTimeLogs(Task $task): JsonResponse
    {
        $timeLogs = TimeLog::where('task_id', $task->id)
            ->with(['user'])
            ->orderBy('log_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalHours = $timeLogs->sum('hours');

        return response()->json([
            'success' => true,
            'data' => [
                'time_logs' => $timeLogs,
                'total_hours' => round($totalHours, 2),
                'estimated_hours' => $task->estimated_hours ?? 0,
                'remaining_hours' => $task->estimated_hours 
                    ? round($task->estimated_hours - $totalHours, 2) 
                    : null,
            ],
            'meta' => [
                'count' => $timeLogs->count(),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get time summary for a task
     */
    public function taskTimeTotal(Task $task): JsonResponse
    {
        $totalHours = TimeLog::where('task_id', $task->id)->sum('hours');

        return response()->json([
            'success' => true,
            'data' => [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'total_hours' => round($totalHours, 2),
                'estimated_hours' => $task->estimated_hours ?? 0,
                'remaining_hours' => $task->estimated_hours 
                    ? round($task->estimated_hours - $totalHours, 2) 
                    : null,
                'percentage_complete' => $task->estimated_hours 
                    ? min(round(($totalHours / $task->estimated_hours) * 100, 2), 100)
                    : null,
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Helper: Update task's actual_hours field
     */
    private function updateTaskActualHours(int $taskId): void
    {
        $totalHours = TimeLog::where('task_id', $taskId)->sum('hours');
        
        DB::table('tasks')
            ->where('id', $taskId)
            ->update([
                'actual_hours' => round($totalHours, 2),
                'updated_at' => now(),
            ]);
    }
}
