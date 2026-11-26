<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountDeletionController extends Controller
{
    /** حالة طلب الحذف الحالي للمواطن (للعرض داخل التطبيق). */
    public function status(Request $request): JsonResponse
    {
        $pending = AccountDeletionRequest::where('citizen_id', $request->user()->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        return response()->json([
            'has_pending' => (bool) $pending,
            'requested_at' => $pending?->requested_at?->toIso8601String(),
        ]);
    }

    /** إنشاء طلب حذف الحساب (يتطلب تأكيد كلمة المرور). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $citizen = $request->user();

        if (! Hash::check($data['password'], $citizen->password)) {
            return response()->json(['message' => 'كلمة المرور غير صحيحة.'], 422);
        }

        // منع التكرار: طلب معلّق واحد فقط
        $existing = AccountDeletionRequest::where('citizen_id', $citizen->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'لديك طلب حذف قيد المراجعة بالفعل.',
                'data' => ['requested_at' => $existing->requested_at->toIso8601String()],
            ], 200);
        }

        $req = AccountDeletionRequest::create([
            'citizen_id' => $citizen->id,
            'citizen_name' => $citizen->name,
            'citizen_phone' => $citizen->phone,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم استلام طلب حذف حسابك. سيُعالَج خلال مدة أقصاها 30 يومًا، ويمكنك إلغاؤه قبل ذلك.',
            'data' => ['requested_at' => $req->requested_at->toIso8601String()],
        ], 201);
    }

    /** إلغاء طلب الحذف المعلّق. */
    public function cancel(Request $request): JsonResponse
    {
        $updated = AccountDeletionRequest::where('citizen_id', $request->user()->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'processed_at' => now()]);

        if (! $updated) {
            return response()->json(['message' => 'لا يوجد طلب حذف معلّق.'], 404);
        }

        return response()->json(['message' => 'تم إلغاء طلب حذف الحساب.']);
    }
}
