<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Services\Otp\OtpException;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CitizenAuthController extends Controller
{
    public function __construct(protected OtpService $otp) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
            'account_type_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('account_types', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'governorate_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('governorates', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'district_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('districts', 'id')->where('governorate_id', $request->input('governorate_id'))->whereNull('deleted_at')],
            'avatar' => ['nullable', 'image', 'max:20480'],
        ]);

        $phone = $this->otp->normalize($data['phone']);

        if (Citizen::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال مسجّل مسبقاً.']]);
        }

        $citizen = Citizen::create([
            'name' => $data['name'],
            'phone' => $phone,
            'password' => $data['password'],
            'account_type_id' => $data['account_type_id'],
            'governorate_id' => $data['governorate_id'],
            'district_id' => $data['district_id'],
            'avatar' => $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'public') : null,
            'status' => 'active',
        ]);

        try {
            $this->otp->send($citizen->phone, 'register');
        } catch (OtpException $e) {
            $citizen->delete();

            return response()->json([
                'message' => 'فشل إرسال رمز التحقق. حاول مرة أخرى.',
            ], 500);
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق. أدخل الرمز لتفعيل حسابك.',
            'phone' => $citizen->phone,
        ], 201);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'min:4', 'max:6'],
        ]);

        $citizen = Citizen::where('phone', $this->otp->normalize($data['phone']))->first();

        if (! $citizen) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال غير مسجّل.']]);
        }

        try {
            $verified = $this->otp->verify($citizen->phone, $data['code'], 'register');
        } catch (OtpException $e) {
            return response()->json(['message' => 'فشل التحقق. حاول مرة أخرى.'], 500);
        }

        if (! $verified) {
            throw ValidationException::withMessages(['code' => ['الرمز غير صحيح أو منتهي الصلاحية.']]);
        }

        $citizen->forceFill(['phone_verified_at' => now()])->save();

        $token = $citizen->createToken('citizen-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->citizenPayload($citizen),
        ], 201);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $citizen = Citizen::where('phone', $this->otp->normalize($data['phone']))->first();

        if (! $citizen) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال غير مسجّل.']]);
        }

        try {
            $this->otp->send($citizen->phone, 'register');
        } catch (OtpException $e) {
            return response()->json(['message' => 'فشل إرسال الرمز.'], 500);
        }

        return response()->json(['message' => 'تم إعادة إرسال رمز التحقق.']);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        $citizen = Citizen::where('phone', $this->otp->normalize($data['phone']))->first();

        if (! $citizen || ! Hash::check($data['password'], $citizen->password)) {
            throw ValidationException::withMessages(['phone' => ['بيانات الدخول غير صحيحة.']]);
        }

        if ($citizen->status === 'blocked') {
            return response()->json(['message' => 'حسابك محظور. تواصل مع الإدارة.'], 403);
        }

        if (! $citizen->phone_verified_at) {
            try {
                $this->otp->send($citizen->phone, 'register');
            } catch (\Throwable) {
            }

            return response()->json([
                'message' => 'الحساب غير مفعّل. تم إرسال رمز تحقق.',
                'phone' => $citizen->phone,
                'requires_otp' => true,
            ], 403);
        }

        $citizen->forceFill(['last_login_at' => now()])->save();

        $token = $citizen->createToken('citizen-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->citizenPayload($citizen),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    /** نسيت كلمة المرور: إرسال رمز تحقق للجوال. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = $this->otp->normalize($data['phone']);

        // لا نكشف إن كان الرقم مسجّلاً أم لا (أمان)، لكن نرسل الرمز إن وُجد
        $citizen = Citizen::where('phone', $phone)->first();
        if ($citizen) {
            try {
                $this->otp->send($phone, 'reset');
            } catch (OtpException) {
                return response()->json(['message' => 'تعذّر إرسال الرمز. حاول لاحقاً.'], 500);
            }
        }

        return response()->json([
            'message' => 'إن كان الرقم مسجّلاً، فسيصلك رمز التحقق.',
            'phone' => $phone,
        ]);
    }

    /** إعادة تعيين كلمة المرور بعد التحقق من الرمز. */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'min:4', 'max:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $phone = $this->otp->normalize($data['phone']);
        $citizen = Citizen::where('phone', $phone)->first();

        if (! $citizen) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال غير مسجّل.']]);
        }

        try {
            $verified = $this->otp->verify($phone, $data['code'], 'reset');
        } catch (OtpException) {
            return response()->json(['message' => 'فشل التحقق. حاول مرة أخرى.'], 500);
        }

        if (! $verified) {
            throw ValidationException::withMessages(['code' => ['الرمز غير صحيح أو منتهي الصلاحية.']]);
        }

        $citizen->update(['password' => $data['password']]);

        $token = $citizen->createToken('citizen-token')->plainTextToken;

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح.',
            'token' => $token,
            'user' => $this->citizenPayload($citizen),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->citizenPayload($request->user())]);
    }

    /** حمولة بيانات المواطن الموحّدة المستخدمة في التسجيل/الدخول/الملف. */
    private function citizenPayload(Citizen $citizen): array
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
