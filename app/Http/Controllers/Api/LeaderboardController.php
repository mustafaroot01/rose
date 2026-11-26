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
     * أبطال التشجير — أعلى 25 مستخدماً حسب النقاط.
     * النقاط = (عدد النباتات المعتمدة × 10) + (عدد سقياتها × 2).
     * أول 3 يُعتبرون "أبطال التشجير".
     *
     * استعلام تجميعي واحد (بدل N+1): JOIN + GROUP BY.
     */
    public function index(Request $request): JsonResponse
    {
        $citizenId = optional($request->user('sanctum'))->id;
        $pp = self::PLANT_POINTS;
        $wp = self::WATER_POINTS;

        $rows = DB::table('citizens')
            ->join('plants', function ($j) {
                $j->on('plants.citizen_id', '=', 'citizens.id')
                    ->where('plants.status', '=', 'approved')
                    ->whereNull('plants.deleted_at');
            })
            ->leftJoin('plant_waterings', 'plant_waterings.plant_id', '=', 'plants.id')
            ->groupBy('citizens.id', 'citizens.name', 'citizens.avatar')
            ->select([
                'citizens.id',
                'citizens.name',
                'citizens.avatar',
                DB::raw('COUNT(DISTINCT plants.id) as plants_count'),
                DB::raw('COUNT(plant_waterings.id) as waterings'),
                DB::raw("(COUNT(DISTINCT plants.id) * $pp + COUNT(plant_waterings.id) * $wp) as score"),
            ])
            ->orderByDesc('score')
            ->orderByDesc(DB::raw('COUNT(DISTINCT plants.id)'))
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
