<?php

namespace App\Http\Resources;

use App\Models\PlantActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlantActivity */
class PlantActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'meta' => $this->meta,
            'user' => $this->whenLoaded('user', fn () => $this->user?->only('id', 'name')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
