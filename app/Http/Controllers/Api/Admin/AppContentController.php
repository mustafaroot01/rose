<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AppContentController extends Controller
{
    /** كل المحتويات القابلة للتعديل (سياسة الخصوصية + عن التطبيق). */
    public function index(): JsonResponse
    {
        return response()->json(['data' => AppContent::orderBy('id')->get()]);
    }

    /** محتوى واحد حسب المفتاح. */
    public function show(string $key): JsonResponse
    {
        $content = AppContent::where('key', $key)->firstOrFail();

        return response()->json(['data' => $content]);
    }

    /** تحديث العنوان والأقسام. */
    public function update(Request $request, string $key): JsonResponse
    {
        $content = AppContent::where('key', $key)->firstOrFail();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string', 'max:255'],
            'sections.*.body' => ['required', 'string', 'max:5000'],
        ]);

        $content->update([
            'title' => $data['title'],
            'sections' => array_map(fn ($s) => ['title' => $s['title'], 'body' => $s['body']], $data['sections']),
        ]);

        Cache::forget("app_content:{$key}"); // إبطال الكاش فورًا بعد التعديل

        return response()->json(['message' => 'تم حفظ المحتوى بنجاح.', 'data' => $content]);
    }
}
