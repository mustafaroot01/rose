<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * عمليات جماعية موحّدة (حذف / إخفاء-إظهار) لأي مورد.
 * يكفي أن يُعرّف الكنترولر دالة bulkModel() التي تُرجع اسم الموديل.
 */
trait HandlesBulkActions
{
    /** @return class-string<Model> */
    abstract protected function bulkModel(): string;

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $count = $this->bulkModel()::whereIn('id', $ids)->delete();

        return response()->json([
            'message' => "تم حذف {$count} عنصر.",
            'count' => $count,
        ]);
    }

    public function bulkToggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $count = $this->bulkModel()::whereIn('id', $data['ids'])
            ->update(['is_active' => $data['is_active']]);

        return response()->json([
            'message' => $data['is_active'] ? "تم إظهار {$count} عنصر." : "تم إخفاء {$count} عنصر.",
            'count' => $count,
        ]);
    }
}
