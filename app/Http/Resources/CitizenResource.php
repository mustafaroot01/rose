<?php

namespace App\Http\Resources;

use App\Models\Citizen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Citizen */
class CitizenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'role' => 'citizen',
            'account_type_id' => $this->account_type_id,
            'account_type' => $this->whenLoaded('accountType', fn () => $this->accountType?->only('id', 'name', 'icon')),
            'avatar' => $this->avatarUrl(),
            'status' => $this->status,
            'phone_verified' => (bool) $this->phone_verified_at,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'plants_count' => $this->whenCounted('plants'),
            'governorate_id' => $this->governorate_id,
            'district_id' => $this->district_id,
            'nahiya_id' => $this->nahiya_id,
            'governorate' => $this->whenLoaded('governorate', fn () => $this->governorate?->only('id', 'name')),
            'district' => $this->whenLoaded('district', fn () => $this->district?->only('id', 'name')),
            'nahiya' => $this->whenLoaded('nahiya', fn () => $this->nahiya?->only('id', 'name')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
