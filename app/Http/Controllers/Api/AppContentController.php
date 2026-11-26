<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AppContentController extends Controller
{
    /** محتوى عام للتطبيق حسب المفتاح (privacy_policy | about). */
    public function show(string $key): JsonResponse
    {
        $payload = Cache::remember("app_content:{$key}", now()->addHours(6), function () use ($key) {
            $content = AppContent::where('key', $key)->first();

            return $content ? [
                'key' => $content->key,
                'title' => $content->title,
                'sections' => $content->sections,
            ] : null;
        });

        if (! $payload) {
            return response()->json(['data' => null], 404);
        }

        return response()->json(['data' => $payload]);
    }
}
