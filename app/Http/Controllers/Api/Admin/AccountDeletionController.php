<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountDeletionController extends Controller
{
    /** قائمة طلبات حذف الحسابات. */
    public function index(Request $request): JsonResponse
    {
        $query = AccountDeletionRequest::with(['citizen:id,name,phone,avatar', 'processor:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('requested_at');

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? 1000 : $perPage;

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'total' => $page->total(),
            'stats' => [
                'pending' => AccountDeletionRequest::where('status', 'pending')->count(),
                'approved' => AccountDeletionRequest::where('status', 'approved')->count(),
            ],
        ]);
    }

    /** الموافقة على الطلب: حذف الحساب وبياناته الشخصية نهائيًا. */
    public function approve(Request $request, AccountDeletionRequest $accountDeletionRequest): JsonResponse
    {
        if ($accountDeletionRequest->status !== 'pending') {
            return response()->json(['message' => 'الطلب غير معلّق.'], 422);
        }

        DB::transaction(function () use ($accountDeletionRequest, $request) {
            $citizen = $accountDeletionRequest->citizen;

            // توثيق الطلب قبل حذف المواطن (حتى لا يُحذف بالـ cascade)
            $accountDeletionRequest->update([
                'status' => 'approved',
                'processed_at' => now(),
                'processed_by' => $request->user()->id,
            ]);

            if ($citizen) {
                // إبطال التوكنات ثم حذف الحساب — النباتات تُفرَّغ (SET NULL) والبيانات الشخصية تُحذف
                $citizen->tokens()->delete();
                $citizen->delete();
            }
        });

        return response()->json(['message' => 'تمت الموافقة وحذف الحساب نهائيًا.']);
    }

    /** رفض الطلب مع ملاحظة. */
    public function reject(Request $request, AccountDeletionRequest $accountDeletionRequest): JsonResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($accountDeletionRequest->status !== 'pending') {
            return response()->json(['message' => 'الطلب غير معلّق.'], 422);
        }

        $accountDeletionRequest->update([
            'status' => 'rejected',
            'admin_note' => $data['admin_note'] ?? null,
            'processed_at' => now(),
            'processed_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'تم رفض الطلب.']);
    }
}
