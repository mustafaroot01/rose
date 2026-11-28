<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CitizenPlantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $plants = Plant::where('citizen_id', $request->user()->id)
            ->with(['category:id,name,color,icon', 'governorate:id,name', 'images'])
            ->withCount(['reports', 'waterings'])
            // آخر سقية (من أي حساب) — لمعرفة النباتات الجاهزة للسقي اليوم في الرئيسية
            ->withMax('waterings', 'watered_at')
            ->latest()
            ->paginate((int) $request->input('per_page', 15));

        return PlantResource::collection($plants);
    }

    public function show(Request $request, Plant $plant): PlantResource
    {
        abort_unless($plant->citizen_id === $request->user()->id, 403);

        $plant->load(['category', 'governorate', 'district', 'campaign'])
            ->loadCount(['reports', 'waterings', 'likes']);

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
            'image' => ['nullable', 'image', 'max:20480'],
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where('status', 'active')],
        ]);

        // التحقق أن المواطن منضمّ للحملة قبل الزراعة ضمنها
        if (! empty($data['campaign_id'])) {
            $joined = \App\Models\Campaign::find($data['campaign_id'])
                ->participants()->where('citizen_id', $request->user()->id)->exists();
            if (! $joined) {
                return response()->json(['message' => 'يجب الانضمام للحملة أولًا قبل الزراعة ضمنها.'], 422);
            }
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('plants', 'public');
        }

        // تاريخ الزرع: إن لم يُحدَّد يُعتمد تاريخ اليوم (ومنه يُحسب عمر النبتة)
        $data['planting_date'] = $data['planting_date'] ?? now()->toDateString();

        $data['citizen_id'] = $request->user()->id;
        $data['status'] = 'pending';

        $plant = Plant::create($data);

        $plant->logActivity('planted', "أضاف المواطن «{$request->user()->name}» النبتة");

        return response()->json([
            'message' => 'تمت إضافة النبتة بنجاح، وهي قيد المراجعة.',
            'data' => new PlantResource($plant->load(['category', 'campaign'])),
        ], 201);
    }

    public function update(Request $request, Plant $plant): JsonResponse
    {
        abort_unless($plant->citizen_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'planting_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:tomorrow'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'image' => ['sometimes', 'nullable', 'image', 'max:20480'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('plants', 'public');
        }

        $data['status'] = 'pending';

        $plant->update($data);

        return response()->json([
            'message' => 'تم تعديل النبتة.',
            'data' => new PlantResource($plant->fresh()->load('category')),
        ]);
    }

    public function destroy(Request $request, Plant $plant): JsonResponse
    {
        abort_unless($plant->citizen_id === $request->user()->id, 403);
        $plant->delete();

        return response()->json(['message' => 'تم حذف النبتة.']);
    }

    public function report(Request $request, Plant $plant): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(['withered', 'dead', 'needs_water', 'vandalism', 'wrong_location', 'other'])],
            'reason_note' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:20480'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('report-evidence', 'public');
        }

        $plant->reports()->create([
            'citizen_reporter_id' => $request->user()->id,
            'reason' => $data['reason'],
            'reason_note' => $data['reason_note'] ?? null,
            'image_path' => $imagePath,
            'status' => 'new',
        ]);

        $plant->logActivity('reported', "بلاغ جديد من المواطن «{$request->user()->name}»");

        return response()->json(['message' => 'تم استلام البلاغ، شكراً لك.'], 201);
    }

    public function water(Request $request, Plant $plant): JsonResponse
    {
        // قيد: لا تُسقى النبتة إلا مرة واحدة كل 24 ساعة — من أي حساب كان
        $lastWatering = $plant->waterings()->latest('watered_at')->first();
        if ($lastWatering && $lastWatering->watered_at->gt(now()->subDay())) {
            $nextAt = $lastWatering->watered_at->copy()->addDay();
            $remaining = (int) ceil(now()->diffInHours($nextAt, false));

            return response()->json([
                'message' => 'سُقِيت هذه النبتة خلال الـ 24 ساعة الماضية، لا يمكن سقيها مرّة أخرى الآن.',
                'next_watering_at' => $nextAt->toIso8601String(),
                'remaining_hours' => max($remaining, 1),
            ], 422);
        }

        $plant->waterings()->create([
            'user_id' => null,
            'citizen_id' => $request->user()->id,
            'watered_at' => now(),
        ]);

        $plant->logActivity('watered', "سقى المواطن «{$request->user()->name}» النبتة");

        return response()->json(['message' => 'تم تسجيل السقي.']);
    }

    /** إعجاب/إلغاء إعجاب بالنبتة (toggle). */
    public function toggleLike(Request $request, Plant $plant): JsonResponse
    {
        $citizenId = $request->user()->id;
        $existing = $plant->likes()->where('citizen_id', $citizenId)->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $plant->likes()->create(['citizen_id' => $citizenId]);
            $liked = true;
        }

        return response()->json([
            'liked' => $liked,
            'likes_count' => $plant->likes()->count(),
        ]);
    }
}
