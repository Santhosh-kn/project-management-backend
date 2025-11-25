<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'project_id' => $this->project_id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'assigned_to_id' => $this->assigned_to,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'created_by_id' => $this->created_by,
            'due_date' => $this->due_date?->toIso8601String(),
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            'order' => $this->order,
            'is_overdue' => $this->isOverdue(),
            'is_completed' => $this->isCompleted(),
            'is_subtask' => $this->isSubtask(),
            'has_subtasks' => $this->when(isset($this->subtasks_count), $this->subtasks_count > 0),
            'subtasks_count' => $this->when(isset($this->subtasks_count), $this->subtasks_count),
            'completion_percentage' => $this->when($request->has('include_completion'), $this->getCompletionPercentage()),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
