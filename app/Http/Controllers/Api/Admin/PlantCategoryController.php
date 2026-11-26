<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlantCategory\StorePlantCategoryRequest;
use App\Http\Requests\PlantCategory\UpdatePlantCategoryRequest;
use App\Http\Resources\PlantCategoryResource;
use App\Models\PlantCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlantCategoryController extends Controller
{
    use HandlesBulkActions;

    protected function bulkModel(): string
    {
        return PlantCategory::class;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PlantCategory::query()->withCount('plants');

        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where('name', 'like', "%{$term}%");
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('is_active', $request->input('status') === 'active');
        });

        $sortBy = in_array($request->input('sortBy'), ['name', 'is_active', 'plants_count', 'created_at'], true)
            ? $request->input('sortBy')
            : 'created_at';
        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return PlantCategoryResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => PlantCategory::count(),
                'active' => PlantCategory::where('is_active', true)->count(),
                'hidden' => PlantCategory::where('is_active', false)->count(),
            ]]);
    }

    public function store(StorePlantCategoryRequest $request): JsonResponse
    {
        $category = PlantCategory::create($request->validated());

        return response()->json([
            'message' => 'تمت إضافة الصنف بنجاح.',
            'data' => new PlantCategoryResource($category->loadCount('plants')),
        ], 201);
    }

    public function update(UpdatePlantCategoryRequest $request, PlantCategory $plantCategory): JsonResponse
    {
        $plantCategory->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث الصنف بنجاح.',
            'data' => new PlantCategoryResource($plantCategory->loadCount('plants')),
        ]);
    }

    public function toggleVisibility(PlantCategory $plantCategory): JsonResponse
    {
        $plantCategory->update(['is_active' => ! $plantCategory->is_active]);

        return response()->json([
            'message' => $plantCategory->is_active ? 'تم إظهار الصنف.' : 'تم إخفاء الصنف.',
            'data' => new PlantCategoryResource($plantCategory->loadCount('plants')),
        ]);
    }

    public function destroy(PlantCategory $plantCategory): JsonResponse
    {
        $plantCategory->delete();

        return response()->json(['message' => 'تم حذف الصنف.']);
    }
}
