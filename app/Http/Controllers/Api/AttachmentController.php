<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Maximum file size in bytes (10MB)
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Allowed file types
     */
    private const ALLOWED_MIMES = [
        // Images
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Text
        'text/plain',
        'text/csv',
        // Archives
        'application/zip',
        'application/x-rar-compressed',
    ];

    /**
     * Upload attachment to a project.
     */
    public function uploadToProject(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . (self::MAX_FILE_SIZE / 1024), // Convert to KB
                'mimes:jpeg,jpg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar',
            ],
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('attachments/projects/' . $project->id, $filename, 'public');

        $attachment = $project->attachments()->create([
            'user_id' => auth()->id(),
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data' => new AttachmentResource($attachment),
            'message' => 'File uploaded successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 201);
    }

    /**
     * Upload attachment to a task.
     */
    public function uploadToTask(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . (self::MAX_FILE_SIZE / 1024), // Convert to KB
                'mimes:jpeg,jpg,png,gif,webp,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar',
            ],
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('attachments/tasks/' . $task->id, $filename, 'public');

        $attachment = $task->attachments()->create([
            'user_id' => auth()->id(),
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'success' => true,
            'data' => new AttachmentResource($attachment),
            'message' => 'File uploaded successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 201);
    }

    /**
     * Get attachment details.
     */
    public function show(Attachment $attachment): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new AttachmentResource($attachment->load(['user', 'attachable'])),
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Preview attachment (inline display).
     */
    public function preview(Attachment $attachment): StreamedResponse
    {
        if (!Storage::disk('public')->exists($attachment->path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->response(
            $attachment->path,
            $attachment->original_filename,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->original_filename . '"',
            ]
        );
    }

    /**
     * Download attachment.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        if (!Storage::disk('public')->exists($attachment->path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download(
            $attachment->path,
            $attachment->original_filename
        );
    }

    /**
     * Delete attachment.
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        // Check if user has permission to delete
        if (auth()->id() !== $attachment->user_id) {
            $attachable = $attachment->attachable;
            
            if ($attachable instanceof Project) {
                $this->authorize('update', $attachable);
            } elseif ($attachable instanceof Task) {
                $this->authorize('update', $attachable);
            }
        }

        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully',
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ]
        ], 204);
    }

    /**
     * Get attachments for a project.
     */
    public function projectAttachments(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $attachments = $project->attachments()->with('user')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => AttachmentResource::collection($attachments),
            'meta' => [
                'total' => $attachments->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get attachments for a task.
     */
    public function taskAttachments(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $attachments = $task->attachments()->with('user')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => AttachmentResource::collection($attachments),
            'meta' => [
                'total' => $attachments->count(),
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }
}
