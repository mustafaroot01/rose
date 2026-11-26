<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlantReportResource;
use App\Models\PlantReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PlantReportController extends Controller
{
    use HandlesBulkActions;

    protected function bulkModel(): string
    {
        return PlantReport::class;
    }

    /**
     * قائمة البلاغات — بحث + فلترة (حالة/سبب/نبتة) + ترتيب + ترقيم.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PlantReport::query()
            ->with(['plant' => fn ($q) => $q->withCount('reports'), 'reporter', 'citizenReporter', 'handler']);

        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where(fn ($sub) => $sub
                ->whereHas('plant', fn ($p) => $p->where('name', 'like', "%{$term}%"))
                ->orWhereHas('reporter', fn ($r) => $r
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))
                ->orWhereHas('citizenReporter', fn ($r) => $r
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")));
        });

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));
        $query->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->input('reason')));
        $query->when($request->filled('plant_id'), fn ($q) => $q->where('plant_id', $request->integer('plant_id')));
        $query->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')));
        $query->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')));

        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        $sortBy = in_array($request->input('sortBy'), ['status', 'reason', 'created_at'], true)
            ? $request->input('sortBy')
            : 'created_at';
        $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return PlantReportResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => PlantReport::count(),
                'new' => PlantReport::where('status', 'new')->count(),
                'in_review' => PlantReport::where('status', 'in_review')->count(),
                'action_taken' => PlantReport::where('status', 'action_taken')->count(),
            ]]);
    }

    public function show(PlantReport $plantReport): PlantReportResource
    {
        return new PlantReportResource(
            $plantReport->load(['plant' => fn ($q) => $q->withCount('reports'), 'plant.user', 'plant.citizen', 'reporter', 'citizenReporter', 'handler']),
        );
    }

    /**
     * تحديث حالة البلاغ واتخاذ الإجراء.
     */
    public function updateStatus(Request $request, PlantReport $plantReport): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_review', 'action_taken', 'dismissed'])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $plantReport->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? $plantReport->admin_note,
            'handled_by' => $request->user()->id,
        ]);

        // توثيق في هستوري النبتة
        $labels = ['new' => 'جديد', 'in_review' => 'قيد المراجعة', 'action_taken' => 'تم اتخاذ إجراء', 'dismissed' => 'مرفوض'];
        $plantReport->plant?->logActivity(
            'report_handled',
            "بلاغ #{$plantReport->id}: {$labels[$data['status']]}",
            $request->user()->id,
        );

        return response()->json([
            'message' => 'تم تحديث حالة البلاغ.',
            'data' => new PlantReportResource($plantReport->load(['plant', 'reporter', 'citizenReporter', 'handler'])),
        ]);
    }

    /**
     * تغيير حالة جماعي لعدّة بلاغات.
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'status' => ['required', Rule::in(['new', 'in_review', 'action_taken', 'dismissed'])],
        ]);

        $count = PlantReport::whereIn('id', $data['ids'])->update([
            'status' => $data['status'],
            'handled_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => "تم تحديث {$count} بلاغ.",
            'count' => $count,
        ]);
    }

    public function destroy(PlantReport $plantReport): JsonResponse
    {
        $plantReport->delete();

        return response()->json(['message' => 'تم حذف البلاغ.']);
    }
}
