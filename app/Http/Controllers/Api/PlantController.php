<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PlantController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $plants = $request->user()
            ->plants()
            ->with(['category:id,name,color,icon', 'governorate:id,name'])
            ->withCount('reports')
            ->latest()
            ->paginate((int) $request->input('per_page', 15));

        return PlantResource::collection($plants);
    }

    public function show(Request $request, Plant $plant): PlantResource
    {
        $this->authorize('view', $plant);

        $plant->load(['category', 'governorate', 'district', 'reports.reporter', 'waterings.user'])
            ->loadCount(['reports', 'waterings']);

        return new PlantResource($plant);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('plant_categories', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'type' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'planting_date' => ['nullable', 'date', 'before_or_equal:tomorrow'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'governorate_id' => ['nullable', 'integer', Rule::exists('governorates', 'id')->whereNull('deleted_at')],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('plants', 'public');
        }

        $data['user_id'] = $request->user()->id;
        $data['status'] = 'pending';

        $plant = Plant::create($data);
        $plant->logActivity('planted', 'تمت إضافة النبتة', $request->user()->id);

        return response()->json([
            'message' => 'تمت إضافة النبتة بنجاح، وهي قيد المراجعة.',
            'data' => new PlantResource($plant->load('category')),
        ], 201);
    }

    public function update(Request $request, Plant $plant): JsonResponse
    {
        $this->authorize('update', $plant);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'planting_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:tomorrow'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'image' => ['sometimes', 'nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('plants', 'public');
        }

        // citizen edits reset status to pending
        if ($request->user()->isCitizen()) {
            $data['status'] = 'pending';
        }

        $plant->update($data);
        $plant->logActivity('updated', 'تم تعديل بيانات النبتة', $request->user()->id);

        return response()->json([
            'message' => 'تم تعديل النبتة.',
            'data' => new PlantResource($plant->fresh()->load('category')),
        ]);
    }

    public function destroy(Request $request, Plant $plant): JsonResponse
    {
        $this->authorize('delete', $plant);

        $plant->delete();
        $plant->logActivity('deleted', 'تم حذف النبتة', $request->user()->id);

        return response()->json(['message' => 'تم حذف النبتة.']);
    }

    public function report(Request $request, Plant $plant): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(['withered', 'dead', 'needs_water', 'vandalism', 'wrong_location', 'other'])],
            'reason_note' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('report-evidence', 'public');
        }

        $report = $plant->reports()->create([
            'reporter_id' => $request->user()->id,
            'reason' => $data['reason'],
            'reason_note' => $data['reason_note'] ?? null,
            'image_path' => $imagePath,
            'status' => 'new',
        ]);

        $plant->logActivity('reported', "بلاغ جديد #{$report->id}", $request->user()->id);

        return response()->json(['message' => 'تم استلام البلاغ، شكراً لك.'], 201);
    }

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

        $plant->logActivity('watered', 'تم سقي النبتة', $request->user()->id);

        return response()->json(['message' => 'تم تسجيل السقي.']);
    }
}
