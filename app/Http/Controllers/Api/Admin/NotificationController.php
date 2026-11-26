<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = AdminNotification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(30)
            ->get();

        $unreadCount = AdminNotification::where('user_id', $request->user()->id)
            ->where('is_seen', false)
            ->count();

        return response()->json([
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        AdminNotification::where('user_id', $request->user()->id)
            ->whereIn('id', $data['ids'])
            ->update(['is_seen' => true]);

        return response()->json(['message' => 'تم التعليم كمقروء.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        AdminNotification::where('user_id', $request->user()->id)
            ->where('is_seen', false)
            ->update(['is_seen' => true]);

        return response()->json(['message' => 'تم تعليم الكل كمقروء.']);
    }

    public function destroy(Request $request, AdminNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->delete();

        return response()->json(['message' => 'تم الحذف.']);
    }
}
