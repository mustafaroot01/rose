<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlantActivityResource;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PlantController extends Controller
{
    /**
     * قائمة النباتات — بحث + فلترة (صنف/محافظة/حالة) + ترتيب + ترقيم.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Plant::query()
            ->with(['category', 'user', 'citizen', 'governorate', 'district', 'campaign:id,title', 'images'])
            ->withCount(['reports', 'waterings', 'likes']);

        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('type', 'like', "%{$term}%"));
        });

        $query->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')));
        $query->when($request->filled('governorate_id'), fn ($q) => $q->where('governorate_id', $request->integer('governorate_id')));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        $sortBy = in_array($request->input('sortBy'), ['name', 'status', 'planting_date', 'created_at'], true)
            ? $request->input('sortBy')
            : 'created_at';
        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return PlantResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => Plant::count(),
                'approved' => Plant::where('status', 'approved')->count(),
                'pending' => Plant::where('status', 'pending')->count(),
                'rejected' => Plant::where('status', 'rejected')->count(),
            ]]);
    }

    /**
     * نقاط النباتات للخريطة — GeoJSON خفيف (فلترة: حالة/صنف/محافظة).
     */
    public function map(Request $request): JsonResponse
    {
        $plants = Plant::query()
            ->select('id', 'name', 'latitude', 'longitude', 'status', 'category_id')
            ->with('category:id,name,color,icon')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('governorate_id'), fn ($q) => $q->where('governorate_id', $request->integer('governorate_id')))
            ->get();

        $features = $plants->map(fn (Plant $plant) => [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float) $plant->longitude, (float) $plant->latitude],
            ],
            'properties' => [
                'id' => $plant->id,
                'name' => $plant->name,
                'status' => $plant->status,
                'category' => $plant->category?->name,
                'color' => $plant->category?->color,
                'icon' => $plant->category?->icon,
            ],
        ]);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * عمليات النبتة (الهستوري) — مرقّمة لعرضها في داتا تيبل.
     */
    public function activities(Request $request, Plant $plant): AnonymousResourceCollection
    {
        $query = $plant->activities()->with('user');

        $query->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')));

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return PlantActivityResource::collection($query->paginate($perPage));
    }

    public function show(Plant $plant): PlantResource
    {
        $plant->load([
            'category', 'user', 'citizen', 'governorate', 'district', 'images', 'campaign',
            'activities.user',
            'reports.reporter', 'reports.handler',
            'waterings.user',
        ])->loadCount(['reports', 'waterings', 'likes']);

        return new PlantResource($plant);
    }

    /**
     * تغيير حالة الاعتماد (قيد المراجعة/معتمدة/مرفوضة).
     */
    public function updateStatus(Request $request, Plant $plant): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $plant->update(['status' => $data['status']]);

        $labels = ['pending' => 'قيد المراجعة', 'approved' => 'معتمدة', 'rejected' => 'مرفوضة'];
        $plant->logActivity('status_changed', "تغيّرت حالة النبتة إلى: {$labels[$data['status']]}", $request->user()->id);

        return response()->json([
            'message' => 'تم تحديث حالة النبتة.',
            'data' => new PlantResource($plant->load(['category', 'user', 'citizen'])),
        ]);
    }

    /**
     * تسجيل سقي للنبتة (من لوحة الأدمن).
     */
    public function water(Request $request, Plant $plant): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $plant->waterings()->create([
            'user_id' => $request->user()->id,
            'watered_at' => now(),
            'note' => $data['note'] ?? null,
        ]);

        $plant->logActivity('watered', 'تم تسجيل سقي للنبتة', $request->user()->id);

        return response()->json(['message' => 'تم تسجيل السقي.']);
    }

    public function update(Request $request, Plant $plant): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', Rule::exists('plant_categories', 'id')],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'planting_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:tomorrow'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'governorate_id' => ['sometimes', 'nullable', 'integer', Rule::exists('governorates', 'id')],
            'district_id' => ['sometimes', 'nullable', 'integer', Rule::exists('districts', 'id')],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'image' => ['sometimes', 'nullable', 'image', 'max:20480'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('plants', 'public');
        }

        $plant->update($data);
        $plant->logActivity('updated', 'تم تعديل بيانات النبتة بواسطة الإدارة', $request->user()->id);

        return response()->json([
            'message' => 'تم تعديل النبتة.',
            'data' => new PlantResource($plant->fresh()->load(['category', 'user', 'citizen', 'governorate', 'district'])),
        ]);
    }

    /**
     * رفع صور إضافية لمعرض النبتة (عرض الشرائح في التطبيق).
     */
    public function uploadImages(Request $request, Plant $plant): JsonResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['image', 'max:20480'],
        ]);

        $next = (int) $plant->images()->max('sort_order');

        foreach ($request->file('images') as $file) {
            $plant->images()->create([
                'path' => $file->store('plants/gallery', 'public'),
                'sort_order' => ++$next,
            ]);
        }

        $plant->logActivity('updated', 'تمت إضافة صور للنبتة', $request->user()->id);

        return response()->json([
            'message' => 'تم رفع الصور.',
            'data' => new PlantResource($plant->fresh()->load(['category', 'images'])),
        ], 201);
    }

    /**
     * حذف صورة من معرض النبتة.
     */
    public function destroyImage(Plant $plant, \App\Models\PlantImage $image): JsonResponse
    {
        abort_unless($image->plant_id === $plant->id, 404);

        \Illuminate\Support\Facades\Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'تم حذف الصورة.']);
    }

    public function destroy(Plant $plant): JsonResponse
    {
        $plant->delete();

        return response()->json(['message' => 'تم حذف النبتة.']);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $count = Plant::whereIn('id', $data['ids'])->update(['status' => $data['status']]);

        return response()->json(['message' => "تم تحديث حالة {$count} نبتة.", 'count' => $count]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $count = Plant::whereIn('id', $ids)->delete();

        return response()->json(['message' => "تم حذف {$count} نبتة.", 'count' => $count]);
    }
}
