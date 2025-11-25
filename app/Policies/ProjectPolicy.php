<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any projects.
     * All authenticated users can view projects (filtered in controller)
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the project.
     * Users can view if: admin, owner, member, or if public
     */
    public function view(User $user, Project $project): bool
    {
        return $user->isAdmin()
            || $project->isOwner($user)
            || $project->hasMember($user)
            || $project->is_public;
    }

    /**
     * Determine whether the user can create projects.
     * Admins and Managers can create projects
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Determine whether the user can update the project.
     * Only admins, owners, and project managers can update
     */
    public function update(User $user, Project $project): bool
    {
        if ($user->isAdmin() || $project->isOwner($user)) {
            return true;
        }

        // Check if user is a project manager
        $role = $project->getMemberRole($user);
        return $role === 'manager' || $role === 'owner';
    }

    /**
     * Determine whether the user can delete the project.
     * Only admins and project owners can delete
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin() || $project->isOwner($user);
    }

    /**
     * Determine whether the user can restore the project.
     * Only admins and project owners can restore
     */
    public function restore(User $user, Project $project): bool
    {
        return $user->isAdmin() || $project->isOwner($user);
    }

    /**
     * Determine whether the user can permanently delete the project.
     * Only admins can force delete
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can add members to the project.
     * Admins, owners, and project managers can add members
     */
    public function addMember(User $user, Project $project): bool
    {
        if ($user->isAdmin() || $project->isOwner($user)) {
            return true;
        }

        $role = $project->getMemberRole($user);
        return $role === 'manager' || $role === 'owner';
    }

    /**
     * Determine whether the user can update member roles in the project.
     * Only admins and project owners can update member roles
     */
    public function updateMember(User $user, Project $project): bool
    {
        return $user->isAdmin() || $project->isOwner($user);
    }

    /**
     * Determine whether the user can remove members from the project.
     * Admins, owners, and project managers can remove members
     */
    public function removeMember(User $user, Project $project): bool
    {
        if ($user->isAdmin() || $project->isOwner($user)) {
            return true;
        }

        $role = $project->getMemberRole($user);
        return $role === 'manager' || $role === 'owner';
    }
}
