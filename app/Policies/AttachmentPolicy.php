<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Determine if the user can view the attachment.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        // User can view if they have access to the parent resource
        return true; // Will be checked in controller based on attachable
    }

    /**
     * Determine if the user can create attachments.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can upload attachments
    }

    /**
     * Determine if the user can delete the attachment.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        // Attachment uploader or admin can delete
        return $user->id === $attachment->user_id || $user->isAdmin();
    }
}
