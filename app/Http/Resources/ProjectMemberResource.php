<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMemberResource extends JsonResource
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
            'user' => new UserResource($this->whenLoaded('user')),
            'user_id' => $this->user_id,
            'project_id' => $this->project_id,
            'role' => $this->role,
            'joined_at' => $this->joined_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
