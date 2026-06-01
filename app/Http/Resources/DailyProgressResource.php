<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'title' => $this->title,
            'in_progress' => $this->in_progress,
            'todo' => $this->todo,
            'blockers' => $this->blockers,
            'notes' => $this->notes,
            'completed_items' => $this->completed_items ?? [],
            'mood' => $this->mood,
            'archived' => (bool) $this->archived,
            'tags' => $this->whenLoaded('tags'),
            'references' => $this->whenLoaded('references'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
