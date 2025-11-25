<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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
            'description' => $this->description,
            'properties' => $this->properties,
            'user' => new UserResource($this->whenLoaded('user')),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject' => $this->whenLoaded('subject', function () {
                if ($this->subject_type === 'App\Models\Project') {
                    return new ProjectResource($this->subject);
                } elseif ($this->subject_type === 'App\Models\Task') {
                    return new TaskResource($this->subject);
                }
                return null;
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
