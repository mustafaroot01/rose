<?php

namespace App\Http\Resources;

use App\Models\AccountType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccountType */
class AccountTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            // مجموع مستخدمي التطبيق (citizens) والموظفين (users) لهذا النوع.
            // سابقاً كان يعدّ الموظفين فقط فيظهر صفر لأن مستخدمي التطبيق في جدول citizens.
            'users_count' => $this->when(
                isset($this->users_count) || isset($this->citizens_count),
                fn () => (int) ($this->users_count ?? 0) + (int) ($this->citizens_count ?? 0),
            ),
            'citizens_count' => $this->whenCounted('citizens'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
