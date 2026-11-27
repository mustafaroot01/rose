<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** إدارة المدونة من لوحة التحكم. */
class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('is_published', $request->input('status') === 'published'))
            ->orderByRaw('COALESCE(published_at, created_at) DESC');

        $perPage = (int) $request->input('itemsPerPage', 10);
        $page = $query->paginate($perPage < 1 ? 100 : $perPage);

        return response()->json([
            'data' => collect($page->items())->map->toApi(),
            'total' => $page->total(),
            'stats' => [
                'total' => Post::count(),
                'published' => Post::where('is_published', true)->count(),
                'hidden' => Post::where('is_published', false)->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('posts', 'public');
        }
        $data['created_by'] = $request->user()->id;
        $post = Post::create($data);

        return response()->json(['message' => 'تم نشر المنشور.', 'data' => $post->toApi()], 201);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        $data = $this->validated($request, $post);
        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            if ($post->image_path) {
                Storage::disk('public')->delete($post->image_path);
            }
            $data['image_path'] = $request->hasFile('image') ? $request->file('image')->store('posts', 'public') : null;
        }
        $post->update($data);

        return response()->json(['message' => 'تم تحديث المنشور.', 'data' => $post->fresh()->toApi()]);
    }

    public function destroy(Post $post): JsonResponse
    {
        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }
        $post->delete();

        return response()->json(['message' => 'تم حذف المنشور.']);
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        $data = $request->validate([
            'title' => [$post ? 'sometimes' : 'required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'image' => ['nullable', 'image', 'max:20480'],
            'link_url' => ['nullable', 'url', 'max:1000'],
            'link_label' => ['nullable', 'string', 'max:100'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
        unset($data['image']);

        return $data;
    }
}
