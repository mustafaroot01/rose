<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Campaign */
class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $approved = $this->whenCounted('plants') ?? $this->resource->approvedCount();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover' => $this->resource->coverUrl(),
            'governorate' => $this->whenLoaded('governorate', fn () => $this->governorate?->only('id', 'name')),
            'district' => $this->whenLoaded('district', fn () => $this->district?->only('id', 'name')),
            'goal_trees' => $this->goal_trees,
            'trees_count' => $this->resource->approvedCount(),
            'progress' => $this->resource->progressPercent(),
            'participants_count' => $this->whenCounted('participants'),
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'status' => $this->status,
            'is_joined' => $this->when(isset($this->is_joined), fn () => (bool) $this->is_joined),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
