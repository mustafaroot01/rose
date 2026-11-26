<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    /** قائمة الحملات النشطة (عامة للزوّار والمسجّلين). */
    public function index(Request $request): JsonResponse
    {
        $citizenId = optional($request->user('sanctum'))->id;

        $campaigns = Campaign::query()
            ->with(['governorate:id,name'])
            ->withCount('participants')
            ->where('status', 'active')
            ->latest()
            ->get()
            ->each(function (Campaign $c) use ($citizenId) {
                $c->is_joined = $citizenId
                    ? $c->participants()->where('citizen_id', $citizenId)->exists()
                    : false;
            });

        return response()->json(['data' => CampaignResource::collection($campaigns)]);
    }

    /** تفاصيل حملة عبر slug (عامة). */
    public function show(Request $request, string $slug): JsonResponse
    {
        $campaign = Campaign::where('slug', $slug)
            ->with(['governorate:id,name', 'district:id,name'])
            ->withCount('participants')
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
