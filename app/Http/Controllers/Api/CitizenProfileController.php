<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CitizenProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $citizen = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'avatar' => ['sometimes', 'image', 'max:20480'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($citizen->avatar) {
                Storage::disk('public')->delete($citizen->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $citizen->update($data);

        return response()->json([
            'message' => 'تم تحديث الملف الشخصي.',
            'data' => $this->payload($citizen->fresh()),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $citizen = $request->user();

        if (! Hash::check($request->current_password, $citizen->password)) {
            return response()->json(['message' => 'كلمة المرور الحالية غير صحيحة.'], 422);
        }

        $citizen->update(['password' => $request->password]);

        return response()->json(['message' => 'تم تغيير كلمة المرور.']);
    }

    /** حمولة بيانات الملف الشخصي مع رابط صورة كامل. */
    private function payload(Citizen $citizen): array
    {
        $citizen->loadMissing(['governorate:id,name', 'district:id,name', 'accountType:id,name']);

        return [
            'id' => $citizen->id,
            'name' => $citizen->name,
            'phone' => $citizen->phone,
            'email' => $citizen->email,
            'role' => 'citizen',
            'status' => $citizen->status,
            'avatar' => $citizen->avatarUrl(),
            'account_type' => $citizen->accountType,
            'governorate' => $citizen->governorate,
            'district' => $citizen->district,
        ];
    }
}
