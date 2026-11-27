<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\CitizenResource;
use App\Http\Resources\PlantResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Campaign::query()
            ->with(['governorate:id,name', 'district:id,name'])
            ->withCount('participants')
            ->withApprovedCount();

        $query->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        $sortBy = in_array($request->input('sortBy'), ['title', 'status', 'goal_trees', 'ends_at', 'created_at'], true)
            ? $request->input('sortBy') : 'created_at';
        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return CampaignResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => Campaign::count(),
                'active' => Campaign::where('status', 'active')->count(),
                'draft' => Campaign::where('status', 'draft')->count(),
                'ended' => Campaign::where('status', 'ended')->count(),
            ]]);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        return new CampaignResource(
            $campaign->load(['governorate:id,name', 'district:id,name', 'creator:id,name'])
                ->loadCount(['participants', 'plants as approved_plants_count' => fn ($q) => $q->where('status', 'approved')]),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        if ($request->hasFile('cover')) {
            $data['cover_path'] = $request->file('cover')->store('campaigns', 'public');
        }

        if ($request->hasFile('sponsor_logo')) {
            $data['sponsor_logo_path'] = $request->file('sponsor_logo')->store('campaigns/sponsors', 'public');
        }

        $data['created_by'] = $request->user()->id;

        $campaign = Campaign::create($data);

        return response()->json([
            'message' => 'تم إنشاء الحملة.',
            'data' => new CampaignResource($campaign),
        ], 201);
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $data = $this->validateData($request, $campaign);

        if ($request->hasFile('cover')) {
            if ($campaign->cover_path) {
                Storage::disk('public')->delete($campaign->cover_path);
            }
            $data['cover_path'] = $request->file('cover')->store('campaigns', 'public');
        }

        // شعار الراعي: رفع جديد يستبدل القديم، أو حذفه صراحة
        if ($request->hasFile('sponsor_logo') || $request->boolean('remove_sponsor_logo')) {
            if ($campaign->sponsor_logo_path) {
                Storage::disk('public')->delete($campaign->sponsor_logo_path);
            }
            $data['sponsor_logo_path'] = $request->hasFile('sponsor_logo')
                ? $request->file('sponsor_logo')->store('campaigns/sponsors', 'public')
                : null;
        }

        $campaign->update($data);

        return response()->json([
            'message' => 'تم تحديث الحملة.',
            'data' => new CampaignResource($campaign->fresh()->loadCount('participants')),
        ]);
    }

    public function updateStatus(Request $request, Campaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'ended'])],
        ]);

        $campaign->update(['status' => $data['status']]);

        return response()->json([
            'message' => 'تم تحديث حالة الحملة.',
            'data' => new CampaignResource($campaign),
        ]);
    }

    public function participants(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        $query = $campaign->participants()
            ->with(['governorate:id,name', 'district:id,name'])
            ->withCount('plants')
            ->orderByPivot('joined_at', 'desc');

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return CitizenResource::collection($query->paginate($perPage));
    }

    public function plants(Request $request, Campaign $campaign): AnonymousResourceCollection
    {
        $query = $campaign->plants()
            ->with(['category:id,name,color,icon', 'citizen:id,name', 'user:id,name', 'images'])
            ->withCount('reports')
            ->latest();

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return PlantResource::collection($query->paginate($perPage));
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        foreach ([$campaign->cover_path, $campaign->sponsor_logo_path] as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }

        $campaign->delete();

        return response()->json(['message' => 'تم حذف الحملة.']);
    }

    private function validateData(Request $request, ?Campaign $campaign = null): array
    {
        $sometimes = $campaign ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$sometimes, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'cover' => ['nullable', 'image', 'max:20480'],
            'governorate_id' => ['nullable', 'integer', Rule::exists('governorates', 'id')->whereNull('deleted_at')],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->whereNull('deleted_at')],
            'goal_trees' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'ended'])],
            'sponsor_name' => ['nullable', 'string', 'max:255'],
            'sponsor_url' => ['nullable', 'url', 'max:500'],
            'sponsor_logo' => ['nullable', 'image', 'max:5120'],
        ]);
    }
}
