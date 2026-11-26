<?php

namespace App\Http\Resources;

use App\Models\PlantReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlantReport */
class PlantReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plant_id' => $this->plant_id,
            'plant' => $this->whenLoaded('plant', fn () => [
                'id' => $this->plant?->id,
                'name' => $this->plant?->name,
                'type' => $this->plant?->type,
                'reports_count' => $this->plant?->reports_count,
            ]),
            'reason' => $this->reason,
            'reason_note' => $this->reason_note,
            'image' => $this->image_path ? url('api/media/'.$this->image_path) : null,
            'status' => $this->status,
            'admin_note' => $this->admin_note,
            'reporter' => $this->getReporterData(),
            'handler' => $this->whenLoaded('handler', fn () => $this->handler?->only('id', 'name')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function getReporterData(): ?array
    {
        if ($this->relationLoaded('citizenReporter') && $this->citizenReporter) {
            return [
                'id' => $this->citizenReporter->id,
                'name' => $this->citizenReporter->name,
                'phone' => $this->citizenReporter->phone,
                'type' => 'citizen',
            ];
        }

        if ($this->relationLoaded('reporter') && $this->reporter) {
            return [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
                'phone' => $this->reporter->phone,
                'type' => 'admin',
            ];
        }

        return null;
    }
}
