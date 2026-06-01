<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'topic' => $this->topic,
            'category' => $this->category,
            'source_type' => $this->source_type,
            'duration_minutes' => $this->duration_minutes,
            'progress_notes' => $this->progress_notes,
            'takeaway' => $this->takeaway,
            'next_action' => $this->next_action,
            'rating' => $this->rating,
            'references' => $this->whenLoaded('references'),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
