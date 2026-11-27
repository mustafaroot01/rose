<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /**
     * قائمة الحملات (عامة للزوّار والمسجّلين).
     * افتراضياً: النشطة فقط (تستخدمها الخريطة وإضافة النبتة).
     * مع ?include_ended=1: النشطة أولاً ثم المنتهية — حتى تبقى الحملة ظاهرة بعد إنهائها.
     */
    public function index(Request $request): JsonResponse
    {
        $citizenId = optional($request->user('sanctum'))->id;
        $includeEnded = $request->boolean('include_ended');

        $campaigns = Campaign::query()
            ->with(['governorate:id,name'])
            ->withCount('participants')
            ->withApprovedCount()
            ->when($citizenId, fn ($q) => $q->withExists([
                'participants as is_joined_flag' => fn ($p) => $p->where('citizen_id', $citizenId),
            ]))
            ->when(
                $includeEnded,
                fn ($q) => $q->whereIn('status', ['active', 'ended'])
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END"),
                fn ($q) => $q->where('status', 'active'),
            )
            ->latest()
            ->get()
            ->each(function (Campaign $c) {
                $c->is_joined = (bool) ($c->is_joined_flag ?? false);
            });

        return response()->json(['data' => CampaignResource::collection($campaigns)]);
    }

    /** تفاصيل حملة عبر slug (عامة). */
    public function show(Request $request, string $slug): JsonResponse
    {
        $campaign = Campaign::where('slug', $slug)
            ->with(['governorate:id,name', 'district:id,name'])
            ->withCount('participants')
            ->withApprovedCount()
            ->firstOrFail();

        $citizenId = optional($request->user('sanctum'))->id;
        $campaign->is_joined = $citizenId
            ? $campaign->participants()->where('citizen_id', $citizenId)->exists()
            : false;

        return response()->json(['data' => new CampaignResource($campaign)]);
    }

    /** المشاركون مرتّبون حسب عدد الأشجار المزروعة ضمن الحملة. */
    public function participants(Request $request, Campaign $campaign): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $page = $campaign->participants()
            ->withCount(['plants' => fn ($q) => $q->where('campaign_id', $campaign->id)
                ->where('status', 'approved')->whereNull('deleted_at')])
            ->orderByDesc('plants_count')
            ->orderByPivot('joined_at', 'asc')
            ->paginate($perPage);

        $items = collect($page->items())->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'avatar' => $c->avatarUrl(),
            'plants_count' => (int) $c->plants_count,
        ]);

        return response()->json([
            'data' => $items,
            'total' => $page->total(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    /** انضمام المواطن للحملة. */
    public function join(Request $request, Campaign $campaign): JsonResponse
    {
        if ($campaign->status !== 'active') {
            return response()->json(['message' => 'هذه الحملة غير متاحة للانضمام.'], 422);
        }

        $campaign->participants()->syncWithoutDetaching([
            $request->user()->id => ['joined_at' => now()],
        ]);

        return response()->json(['message' => 'انضممت للحملة بنجاح.']);
    }

    /** انسحاب المواطن من الحملة. */
    public function leave(Request $request, Campaign $campaign): JsonResponse
    {
        $campaign->participants()->detach($request->user()->id);

        return response()->json(['message' => 'تم الانسحاب من الحملة.']);
    }
}
