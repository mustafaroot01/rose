<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlantReportResource;
use App\Models\Campaign;
use App\Models\Citizen;
use App\Models\Plant;
use App\Models\PlantCategory;
use App\Models\PlantReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminStatsController extends Controller
{
    /**
     * إحصائيات موحّدة للصفحة الرئيسية (KPIs + مخططات + أحدث البلاغات).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'totals' => [
                'plants' => Plant::count(),
                'users' => Citizen::count(),
                'reports' => PlantReport::count(),
                'categories' => PlantCategory::count(),
            ],

            'plants_by_status' => [
                'approved' => Plant::where('status', 'approved')->count(),
                'pending' => Plant::where('status', 'pending')->count(),
                'rejected' => Plant::where('status', 'rejected')->count(),
            ],

            'reports_by_status' => [
                'new' => PlantReport::where('status', 'new')->count(),
                'in_review' => PlantReport::where('status', 'in_review')->count(),
                'action_taken' => PlantReport::where('status', 'action_taken')->count(),
                'dismissed' => PlantReport::where('status', 'dismissed')->count(),
            ],

            'users' => [
                'citizens' => Citizen::count(),
                'staff' => User::whereIn('role', ['admin', 'supervisor'])->count(),
                'active' => Citizen::where('status', 'active')->count(),
            ],

            'plants_by_category' => $this->plantsByCategory(),
            'plants_by_governorate' => $this->plantsByGovernorate(),
            'plants_growth' => $this->plantsGrowth(),
            'campaigns_performance' => $this->campaignsPerformance(),

            'recent_reports' => PlantReportResource::collection(
                PlantReport::with(['plant', 'reporter', 'citizenReporter'])->latest()->take(6)->get(),
            ),
        ]);
    }

    /** أداء الحملات: الإنجاز مقابل الهدف + عدد المشاركين (أحدث 6). */
    private function campaignsPerformance(): array
    {
        return Campaign::withCount('participants')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Campaign $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'goal' => (int) $c->goal_trees,
                'planted' => $c->approvedCount(),
                'progress' => $c->progressPercent(),
                'participants' => (int) $c->participants_count,
                'status' => $c->status,
            ])
            ->toArray();
    }

    /** توزيع النباتات حسب الصنف (مع اللون). */
    private function plantsByCategory(): array
    {
        return PlantCategory::query()
            ->withCount('plants')
            ->orderByDesc('plants_count')
            ->get()
            ->map(fn (PlantCategory $c) => [
                'name' => $c->name,
                'color' => $c->color,
                'count' => $c->plants_count,
            ])
            ->all();
    }

    /** أعلى 6 محافظات بعدد النباتات. */
    private function plantsByGovernorate(): array
    {
        return Plant::query()
            ->selectRaw('governorate_id, COUNT(*) as aggregate')
            ->whereNotNull('governorate_id')
            ->groupBy('governorate_id')
            ->orderByDesc('aggregate')
            ->take(6)
            ->with('governorate:id,name')
            ->get()
            ->map(fn (Plant $p) => [
                'name' => $p->governorate?->name ?? '—',
                'count' => (int) $p->aggregate,
            ])
            ->all();
    }

    /** نمو النباتات خلال آخر 6 أشهر (مع تعبئة الأشهر الفارغة). */
    private function plantsGrowth(): array
    {
        $counts = Plant::query()
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['created_at'])
            ->groupBy(fn (Plant $p) => $p->created_at->format('Y-m'))
            ->map
            ->count();

        return collect(range(5, 0))
            ->map(function (int $i) use ($counts) {
                $month = now()->subMonths($i);

                return [
                    'month' => $month->startOfMonth()->toDateString(),
                    'count' => $counts->get($month->format('Y-m'), 0),
                ];
            })
            ->all();
    }
}
