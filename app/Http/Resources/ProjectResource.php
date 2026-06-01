<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'archived' => (bool) $this->archived,
            'tasks_count' => $this->whenCounted('tasks'),
            'open_tasks_count' => $this->whenCounted('open_tasks'),
            'work_logs_count' => $this->whenCounted('workLogs'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
