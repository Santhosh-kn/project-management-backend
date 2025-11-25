<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks.
     * All authenticated users can view tasks (filtered in controller)
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the task.
     * User can view if they can access the project
     */
    public function view(User $user, Task $task): bool
    {
        $project = $task->project;

        return $user->isAdmin()
            || $project->isOwner($user)
            || $project->hasMember($user)
            || $project->is_public;
    }

    /**
     * Determine whether the user can create tasks.
     * User can create if they have access to the project
     */
    public function create(User $user): bool
    {
        // Authorization is checked against project access in controller
        return true;
    }

    /**
     * Determine whether the user can update the task.
     * Admins, project owners, managers, task creator, and assigned user can update
     */
    public function update(User $user, Task $task): bool
    {
        $project = $task->project;

        // Admin can update any task
        if ($user->isAdmin()) {
            return true;
        }

        // Project owner can update any task in their project
        if ($project->isOwner($user)) {
            return true;
        }

        // Project manager can update tasks in the project
        $projectRole = $project->getMemberRole($user);
        if ($projectRole === 'manager' || $projectRole === 'owner') {
            return true;
        }

        // Task creator can update their task
        if ($task->created_by === $user->id) {
            return true;
        }

        // Assigned user can update the task
        if ($task->assigned_to === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the task.
     * Only admins, project owners, managers, and task creator can delete
     */
    public function delete(User $user, Task $task): bool
    {
        $project = $task->project;

        // Admin can delete any task
        if ($user->isAdmin()) {
            return true;
        }

        // Project owner can delete any task in their project
        if ($project->isOwner($user)) {
            return true;
        }

        // Project manager can delete tasks in the project
        $projectRole = $project->getMemberRole($user);
        if ($projectRole === 'manager' || $projectRole === 'owner') {
            return true;
        }

        // Task creator can delete their task
        if ($task->created_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the task.
     * Only admins and project owners can restore
     */
    public function restore(User $user, Task $task): bool
    {
        $project = $task->project;

        return $user->isAdmin() || $project->isOwner($user);
    }

    /**
     * Determine whether the user can permanently delete the task.
     * Only admins can force delete
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can assign the task to someone.
     * Admins, project owners, and managers can assign tasks
     */
    public function assign(User $user, Task $task): bool
    {
        $project = $task->project;

        // Admin can assign any task
        if ($user->isAdmin()) {
            return true;
        }

        // Project owner can assign any task
        if ($project->isOwner($user)) {
            return true;
        }

        // Project manager can assign tasks
        $projectRole = $project->getMemberRole($user);
        if ($projectRole === 'manager' || $projectRole === 'owner') {
            return true;
        }

        return false;
    }
}
