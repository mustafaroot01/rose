<?php

namespace App\Http\Resources;

use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plant */
class PlantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => $this->category?->only('id', 'name', 'color', 'icon')),
            'campaign' => $this->whenLoaded('campaign', fn () => $this->campaign ? [
                'id' => $this->campaign->id,
                'title' => $this->campaign->title,
                'slug' => $this->campaign->slug,
                'cover' => $this->campaign->coverUrl(),
            ] : null),
            'owner' => $this->getOwnerData(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'governorate' => $this->whenLoaded('governorate', fn () => $this->governorate?->only('id', 'name')),
            'district' => $this->whenLoaded('district', fn () => $this->district?->only('id', 'name')),
            'planting_date' => $this->planting_date?->toDateString(),
            'age_label' => $this->resource->ageLabel(),
            'image' => $this->resource->imageUrl(),
            'images' => $this->resource->imageUrls(),
            'gallery' => $this->whenLoaded('images', fn () => $this->images
                ->map(fn ($i) => ['id' => $i->id, 'url' => $i->url()])->values()),
            'status' => $this->status,
            'reports_count' => $this->whenCounted('reports'),
            'waterings_count' => $this->whenCounted('waterings'),
            'likes_count' => $this->whenCounted('likes'),
            'activities' => PlantActivityResource::collection($this->whenLoaded('activities')),
            'reports' => PlantReportResource::collection($this->whenLoaded('reports')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    protected function getOwnerData(): ?array
    {
        if ($this->relationLoaded('citizen') && $this->citizen) {
            return [
                'id' => $this->citizen->id,
                'name' => $this->citizen->name,
                'phone' => $this->citizen->phone,
                'type' => 'citizen',
            ];
        }

        if ($this->relationLoaded('user') && $this->user) {
            return [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
                'type' => 'admin',
            ];
        }

        return null;
    }
}
