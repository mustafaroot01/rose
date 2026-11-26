<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\District\StoreDistrictRequest;
use App\Http\Requests\District\UpdateDistrictRequest;
use App\Http\Resources\DistrictResource;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DistrictController extends Controller
{
    use HandlesBulkActions;

    protected function bulkModel(): string
    {
        return District::class;
    }

    /**
     * قائمة الأقضية — بحث + فلترة (محافظة/حالة) + ترتيب + ترقيم صفحات.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = District::query()->with('governorate')->withCount('nahiyas');

        // 🔍 بحث بالاسم أو الرمز
        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%"));
        });

        // 🎛️ فلترة المحافظة
        $query->when($request->filled('governorate_id'), function ($q) use ($request) {
            $q->where('governorate_id', $request->integer('governorate_id'));
        });

        // 🎛️ فلترة الحالة
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('is_active', $request->input('status') === 'active');
        });

        // ↕️ الترتيب
        $sortBy = in_array($request->input('sortBy'), ['name', 'code', 'is_active', 'created_at'], true)
            ? $request->input('sortBy')
            : 'created_at';
        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return DistrictResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => District::count(),
                'active' => District::where('is_active', true)->count(),
                'hidden' => District::where('is_active', false)->count(),
            ]]);
    }

    public function store(StoreDistrictRequest $request): JsonResponse
    {
        $district = District::create($request->validated());

        return response()->json([
            'message' => 'تمت إضافة القضاء بنجاح.',
            'data' => new DistrictResource($district->load('governorate')),
        ], 201);
    }

    public function update(UpdateDistrictRequest $request, District $district): JsonResponse
    {
        $district->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث القضاء بنجاح.',
            'data' => new DistrictResource($district->load('governorate')),
        ]);
    }

    public function toggleVisibility(District $district): JsonResponse
    {
        $district->update(['is_active' => ! $district->is_active]);

        return response()->json([
            'message' => $district->is_active ? 'تم إظهار القضاء.' : 'تم إخفاء القضاء.',
            'data' => new DistrictResource($district->load('governorate')),
        ]);
    }

    public function destroy(District $district): JsonResponse
    {
        $district->delete();

        return response()->json(['message' => 'تم حذف القضاء.']);
    }
}
