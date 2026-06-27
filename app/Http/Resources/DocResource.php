<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'reference_urls' => $this->reference_urls ?? [],
            'files' => DocFileResource::collection($this->whenLoaded('files')),
            'share_token' => $this->share_token,
            'share_url' => $this->share_token ? url('/share/doc/'.$this->share_token) : null,
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
