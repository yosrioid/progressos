<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'milestone_id' => $this->milestone_id,
            'work_log_id' => $this->work_log_id,
            'title' => $this->title,
            'notes' => $this->notes,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toJSON(),
            'recurrence_rule' => $this->recurrence_rule,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_days' => $this->recurrence_days,
            'recurrence_ends_at' => $this->recurrence_ends_at?->toDateString(),
            'parent_task_id' => $this->parent_task_id,
            'project' => $this->whenLoaded('project'),
            'references' => $this->whenLoaded('references'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
