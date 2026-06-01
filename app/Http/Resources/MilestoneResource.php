<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'progress_percent' => method_exists($this->resource, 'progressPercent') ? $this->resource->progressPercent() : null,
        ];
    }
}
