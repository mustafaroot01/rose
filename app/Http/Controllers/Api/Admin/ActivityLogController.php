<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AdminActivityLog::with('user:id,name,role')
            ->latest();

        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where('description', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"));
        });

        $query->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')));
        $query->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')));

        $perPage = (int) $request->input('itemsPerPage', 15);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return JsonResource::collection($query->paginate($perPage));
    }
}
