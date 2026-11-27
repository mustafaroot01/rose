<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitizenResource;
use App\Http\Resources\PlantResource;
use App\Models\Citizen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CitizenController extends Controller
{
    /**
     * قائمة المواطنين — بحث + فلترة (حالة/نوع/محافظة) + ترتيب + ترقيم.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Citizen::query()->with(['accountType', 'governorate', 'district', 'nahiya'])->withCount('plants');

        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"));
        });

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));
        $query->when($request->filled('account_type_id'), fn ($q) => $q->where('account_type_id', $request->integer('account_type_id')));
        $query->when($request->filled('governorate_id'), fn ($q) => $q->where('governorate_id', $request->integer('governorate_id')));

        $sortBy = in_array($request->input('sortBy'), ['name', 'status', 'created_at'], true)
            ? $request->input('sortBy')
            : 'created_at';
        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return CitizenResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => Citizen::count(),
                'active' => Citizen::where('status', 'active')->count(),
                'blocked' => Citizen::where('status', 'blocked')->count(),
                'verified' => Citizen::whereNotNull('phone_verified_at')->count(),
            ]]);
    }

    public function show(Citizen $citizen): CitizenResource
    {
        return new CitizenResource(
            $citizen->load(['accountType', 'governorate', 'district', 'nahiya'])->loadCount('plants'),
        );
    }

    /**
     * نباتات المواطن — مرقّمة.
     */
    public function plants(Request $request, Citizen $citizen): AnonymousResourceCollection
    {
        $query = $citizen->plants()->with(['category', 'governorate', 'images'])->withCount('reports')->latest();

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return PlantResource::collection($query->paginate($perPage));
    }

    /**
     * حظر/تفعيل حساب المواطن (toggle).
     */
    public function toggleStatus(Citizen $citizen): JsonResponse
    {
        $citizen->update(['status' => $citizen->status === 'active' ? 'blocked' : 'active']);

        return response()->json([
            'message' => $citizen->status === 'active' ? 'تم تفعيل الحساب.' : 'تم حظر الحساب.',
            'data' => new CitizenResource($citizen),
        ]);
    }

    public function destroy(Citizen $citizen): JsonResponse
    {
        $citizen->delete();

        return response()->json(['message' => 'تم حذف المواطن.']);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $count = Citizen::whereIn('id', $ids)->delete();

        return response()->json(['message' => "تم حذف {$count} مواطن.", 'count' => $count]);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'status' => ['required', Rule::in(['active', 'blocked'])],
        ]);

        $count = Citizen::whereIn('id', $data['ids'])->update(['status' => $data['status']]);

        return response()->json([
            'message' => $data['status'] === 'active' ? "تم تفعيل {$count} مواطن." : "تم حظر {$count} مواطن.",
            'count' => $count,
        ]);
    }
}
