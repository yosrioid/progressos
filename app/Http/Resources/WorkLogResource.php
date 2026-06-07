<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'task_id' => $this->task_id,
            'task' => $this->whenLoaded('task', fn () => ['id' => $this->task->id, 'title' => $this->task->title]),
            'date' => $this->date?->toDateString(),
            'project_name' => $this->project_name,
            'ticket_code' => $this->ticket_code,
            'title' => $this->title,
            'category' => $this->category,
            'status' => $this->status,
            'priority' => $this->priority,
            'description' => $this->description,
            'resolution_or_outcome' => $this->resolution_or_outcome,
            'estimated_duration' => $this->estimated_duration,
            'actual_duration' => $this->actual_duration,
            'tags' => $this->whenLoaded('tags'),
            'references' => $this->whenLoaded('references'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
