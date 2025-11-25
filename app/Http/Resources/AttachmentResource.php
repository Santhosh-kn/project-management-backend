<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'formatted_size' => $this->formatted_size,
            'is_image' => $this->isImage(),
            'is_document' => $this->isDocument(),
            'url' => $this->url,
            'download_url' => route('attachments.download', $this->id),
            'uploaded_by' => new UserResource($this->whenLoaded('user')),
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
