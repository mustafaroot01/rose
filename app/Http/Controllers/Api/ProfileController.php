<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['accountType:id,name', 'governorate:id,name', 'district:id,name', 'nahiya:id,name']);

        return response()->json(['data' => $user]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('users')->ignore($user->id)],
            'governorate_id' => ['sometimes', 'nullable', 'integer', 'exists:governorates,id'],
            'district_id' => ['sometimes', 'nullable', 'integer', 'exists:districts,id'],
            'nahiya_id' => ['sometimes', 'nullable', 'integer', 'exists:nahiyas,id'],
            'avatar' => ['sometimes', 'nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return response()->json([
            'message' => 'تم تحديث البيانات.',
            'data' => $user->fresh()->load(['accountType:id,name', 'governorate:id,name', 'district:id,name']),
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'تم تغيير كلمة المرور.']);
    }
}
