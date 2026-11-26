<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index(): JsonResponse
    {
        $banners = Banner::orderBy('sort_order')->orderBy('id')->get();

        return response()->json([
            'data' => $banners->map(fn (Banner $b) => $this->payload($b)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['image', 'max:20480'],
            'title' => ['nullable', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:500'],
        ]);

        $next = (int) Banner::max('sort_order');

        foreach ($request->file('images') as $file) {
            Banner::create([
                'image_path' => $file->store('banners', 'public'),
                'title' => $data['title'] ?? null,
                'link' => $data['link'] ?? null,
                'sort_order' => ++$next,
                'is_active' => true,
            ]);
        }

        return response()->json(['message' => 'تم رفع البنرات.'], 201);
    }

    public function toggle(Banner $banner): JsonResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return response()->json([
            'message' => $banner->is_active ? 'تم تفعيل البنر.' : 'تم إخفاء البنر.',
            'data' => $this->payload($banner),
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($data['ids'] as $order => $id) {
            Banner::where('id', $id)->update(['sort_order' => $order]);
        }

        return response()->json(['message' => 'تم تحديث الترتيب.']);
    }

    public function destroy(Banner $banner): JsonResponse
    {
        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();

        return response()->json(['message' => 'تم حذف البنر.']);
    }

    private function payload(Banner $b): array
    {
        return [
            'id' => $b->id,
            'url' => $b->url(),
            'title' => $b->title,
            'link' => $b->link,
            'is_active' => $b->is_active,
            'sort_order' => $b->sort_order,
        ];
    }
}
