<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Governorate\StoreGovernorateRequest;
use App\Http\Requests\Governorate\UpdateGovernorateRequest;
use App\Http\Resources\GovernorateResource;
use App\Models\Governorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GovernorateController extends Controller
{
    use HandlesBulkActions;

    protected function bulkModel(): string
    {
        return Governorate::class;
    }

    /**
     * قائمة المحافظات — بحث + فلترة حالة + ترتيب + ترقيم صفحات.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Governorate::query()
            ->withCount('districts')
            ->with(['districts' => fn ($q) => $q->orderBy('name')]);

        // 🔍 بحث بالاسم أو الرمز
        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%"));
        });

        // 🎛️ فلترة الحالة: active | hidden
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('is_active', $request->input('status') === 'active');
        });

        // ↕️ الترتيب
        $sortBy = in_array($request->input('sortBy'), ['name', 'code', 'is_active', 'districts_count', 'created_at'], true)
            ? $request->input('sortBy')
            : 'created_at';
        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return GovernorateResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => Governorate::count(),
                'active' => Governorate::where('is_active', true)->count(),
                'hidden' => Governorate::where('is_active', false)->count(),
                'districts_total' => \App\Models\District::count(),
            ]]);
    }

    public function store(StoreGovernorateRequest $request): JsonResponse
    {
        $governorate = Governorate::create($request->validated());

        return response()->json([
            'message' => 'تمت إضافة المحافظة بنجاح.',
            'data' => new GovernorateResource($governorate->loadCount('districts')),
        ], 201);
    }

    public function show(Governorate $governorate): GovernorateResource
    {
        return new GovernorateResource($governorate->loadCount('districts')->load('districts'));
    }

    public function update(UpdateGovernorateRequest $request, Governorate $governorate): JsonResponse
    {
        $governorate->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث المحافظة بنجاح.',
            'data' => new GovernorateResource($governorate->loadCount('districts')),
        ]);
    }

    /**
     * إخفاء/إظهار المحافظة (toggle).
     */
    public function toggleVisibility(Governorate $governorate): JsonResponse
    {
        $governorate->update(['is_active' => ! $governorate->is_active]);

        return response()->json([
            'message' => $governorate->is_active ? 'تم إظهار المحافظة.' : 'تم إخفاء المحافظة.',
            'data' => new GovernorateResource($governorate->loadCount('districts')),
        ]);
    }

    public function destroy(Governorate $governorate): JsonResponse
    {
        $governorate->delete();

        return response()->json(['message' => 'تم حذف المحافظة.']);
    }
}
