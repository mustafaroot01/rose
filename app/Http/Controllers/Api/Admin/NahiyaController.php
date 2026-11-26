<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nahiya\StoreNahiyaRequest;
use App\Http\Requests\Nahiya\UpdateNahiyaRequest;
use App\Http\Resources\NahiyaResource;
use App\Models\Nahiya;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NahiyaController extends Controller
{
    use HandlesBulkActions;

    protected function bulkModel(): string
    {
        return Nahiya::class;
    }

    /**
     * قائمة النواحي — بحث + فلترة (قضاء/محافظة/حالة) + ترتيب + ترقيم.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Nahiya::query()->with('district.governorate');

        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%"));
        });

        $query->when($request->filled('district_id'), function ($q) use ($request) {
            $q->where('district_id', $request->integer('district_id'));
        });

        // فلترة حسب المحافظة (عبر القضاء)
        $query->when($request->filled('governorate_id'), function ($q) use ($request) {
            $q->whereHas('district', fn ($d) => $d->where('governorate_id', $request->integer('governorate_id')));
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('is_active', $request->input('status') === 'active');
        });

        $sortBy = in_array($request->input('sortBy'), ['name', 'code', 'is_active', 'created_at'], true)
            ? $request->input('sortBy')
            : 'created_at';
        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return NahiyaResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => Nahiya::count(),
                'active' => Nahiya::where('is_active', true)->count(),
                'hidden' => Nahiya::where('is_active', false)->count(),
            ]]);
    }

    public function store(StoreNahiyaRequest $request): JsonResponse
    {
        $nahiya = Nahiya::create($request->validated());

        return response()->json([
            'message' => 'تمت إضافة الناحية بنجاح.',
            'data' => new NahiyaResource($nahiya->load('district.governorate')),
        ], 201);
    }

    public function update(UpdateNahiyaRequest $request, Nahiya $nahiya): JsonResponse
    {
        $nahiya->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث الناحية بنجاح.',
            'data' => new NahiyaResource($nahiya->load('district.governorate')),
        ]);
    }

    public function toggleVisibility(Nahiya $nahiya): JsonResponse
    {
        $nahiya->update(['is_active' => ! $nahiya->is_active]);

        return response()->json([
            'message' => $nahiya->is_active ? 'تم إظهار الناحية.' : 'تم إخفاء الناحية.',
            'data' => new NahiyaResource($nahiya->load('district.governorate')),
        ]);
    }

    public function destroy(Nahiya $nahiya): JsonResponse
    {
        $nahiya->delete();

        return response()->json(['message' => 'تم حذف الناحية.']);
    }
}
