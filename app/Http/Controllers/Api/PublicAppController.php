<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** نقاط عامّة للتطبيق: المدونة + إعدادات التطبيق. */
class PublicAppController extends Controller
{
    /** منشورات المدونة الظاهرة (الأحدث أولاً). */
    public function posts(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $page = Post::visible()->orderByRaw('COALESCE(published_at, created_at) DESC')->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map->toApi(),
            'has_more' => $page->hasMorePages(),
        ]);
    }

    public function post(int $id): JsonResponse
    {
        $post = Post::visible()->findOrFail($id);

        return response()->json(['data' => $post->toApi()]);
    }

    /** إعدادات التطبيق العامة: روابط التواصل + معلومات قفل التحديث. */
    public function config(): JsonResponse
    {
        $s = AppSetting::all_values();

        return response()->json([
            'social' => [
                'instagram' => $s['social_instagram'] ?: null,
                'facebook' => $s['social_facebook'] ?: null,
                'whatsapp' => $s['social_whatsapp'] ?: null,
                'tiktok' => $s['social_tiktok'] ?: null,
            ],
            'update' => [
                'force' => (bool) $s['force_update'],
                'min_version_android' => $s['min_version_android'] ?: null,
                'min_version_ios' => $s['min_version_ios'] ?: null,
                'message' => $s['update_message'],
                'android_url' => $s['android_url'] ?: null,
                'ios_url' => $s['ios_url'] ?: null,
            ],
        ]);
    }
}
