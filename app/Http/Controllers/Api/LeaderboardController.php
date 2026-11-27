<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /** نقاط كل نبتة معتمدة. */
    private const PLANT_POINTS = 10;

    /** نقاط كل سقية صحيحة (سقية واحدة لكل نبتة كل 24 ساعة). */
    private const WATER_POINTS = 2;

    /**
     * أبطال التشجير — أعلى 25 مستخدماً زرعوا نباتات (معتمدة أو قيد المراجعة).
     * النقاط = (عدد النباتات المعتمدة × 10) + (عدد سقياتها × 2).
     * أول 3 يُعتبرون "أبطال التشجير".
     *
     * فلاتر اختيارية:
     *  - governorate_id: أبطال محافظة معيّنة (حسب محافظة المواطن المسجّلة).
     *  - period=month: نشاط هذا الشهر فقط (النباتات المضافة والسقيات خلال الشهر الحالي).
     *
     * استعلام تجميعي واحد (بدل N+1): JOIN + GROUP BY.
     */
    public function index(Request $request): JsonResponse
    {
        $citizenId = optional($request->user('sanctum'))->id;
        $pp = self::PLANT_POINTS;
        $wp = self::WATER_POINTS;

        $governorateId = $request->integer('governorate_id') ?: null;
        $since = $request->input('period') === 'month' ? now()->startOfMonth() : null;

        // شرط الفترة على النباتات (فارغ = كل الأوقات)
        $inPeriod = $since ? 'plants.created_at >= ?' : '1 = 1';
        $periodBindings = $since ? [$since] : [];
        $approvedInPeriod = "plants.status = 'approved' AND $inPeriod";

        $rows = DB::table('citizens')
            ->join('plants', function ($j) {
                $j->on('plants.citizen_id', '=', 'citizens.id')
                    ->whereIn('plants.status', ['approved', 'pending'])
                    ->whereNull('plants.deleted_at');
            })
            // السقيات: كلها، أو سقيات هذا الشهر فقط عند period=month
            ->leftJoin('plant_waterings', function ($j) use ($since) {
                $j->on('plant_waterings.plant_id', '=', 'plants.id');
                if ($since) {
                    $j->where('plant_waterings.created_at', '>=', $since);
                }
            })
            ->when($governorateId, fn ($q) => $q->where('citizens.governorate_id', $governorateId))
            ->groupBy('citizens.id', 'citizens.name', 'citizens.avatar')
            ->select(['citizens.id', 'citizens.name', 'citizens.avatar'])
            ->selectRaw("COUNT(DISTINCT CASE WHEN $approvedInPeriod THEN plants.id END) as plants_count", $periodBindings)
            ->selectRaw("COUNT(CASE WHEN plants.status = 'approved' THEN plant_waterings.id END) as waterings")
            ->selectRaw("COUNT(DISTINCT CASE WHEN $inPeriod THEN plants.id END) as all_plants", $periodBindings)
            ->selectRaw(
                "(COUNT(DISTINCT CASE WHEN $approvedInPeriod THEN plants.id END) * $pp"
                ." + COUNT(CASE WHEN plants.status = 'approved' THEN plant_waterings.id END) * $wp) as score",
                $periodBindings,
            )
            // ضمن فترة الشهر: من له نشاط خلاله فقط
            ->when($since, fn ($q) => $q->havingRaw('(all_plants + waterings) > 0'))
            ->orderByDesc('score')
            ->orderByDesc('all_plants')
            ->orderBy('citizens.id')
            ->limit(25)
            ->get();

        $data = $rows->values()->map(function ($row, $i) use ($citizenId) {
            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'avatar' => $row->avatar ? url('api/media/'.$row->avatar) : null,
                'plants_count' => (int) $row->plants_count,
                'waterings' => (int) $row->waterings,
                'score' => (int) $row->score,
                'rank' => $i + 1,
                'is_hero' => $i < 3,
                'is_me' => (int) $row->id === $citizenId,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
