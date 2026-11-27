<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Otp\OtpException;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected OtpService $otp) {}

    /**
     * تسجيل مواطن جديد → ينشئ حساب غير مُفعّل ويرسل رمز OTP عبر واتساب.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
            'account_type_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('account_types', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'governorate_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('governorates', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'district_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('districts', 'id')->where('governorate_id', $request->input('governorate_id'))->whereNull('deleted_at')],
        ]);

        $phone = $this->otp->normalize($data['phone']);

        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال مسجّل مسبقاً.']]);
        }

        $user = User::create([
            'name' => $data['name'],
            'phone' => $phone,
            'password' => $data['password'],
            'account_type_id' => $data['account_type_id'],
            'governorate_id' => $data['governorate_id'],
            'district_id' => $data['district_id'],
            'role' => 'citizen',
            'status' => 'active',
        ]);

        $user->assignRole('citizen');

        try {
            $this->otp->send($user->phone, 'register');
        } catch (OtpException $e) {
            // نحذف الحساب لو فشل الإرسال عشان يقدر يعيد التسجيل
            $user->delete();

            return $this->otpError($e);
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق إلى واتساب. فضلاً أدخل الرمز لتفعيل حسابك.',
            'phone' => $user->phone,
        ], 201);
    }

    /**
     * التحقق من رمز OTP → يفعّل الحساب ويرجّع توكن الدخول.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'min:4', 'max:6'],
        ]);

        $user = User::where('phone', $this->otp->normalize($data['phone']))->first();

        if (! $user) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال غير مسجّل.']]);
        }

        try {
            $verified = $this->otp->verify($user->phone, $data['code'], 'register');
        } catch (OtpException $e) {
            return $this->otpError($e);
        }

        if (! $verified) {
            throw ValidationException::withMessages(['code' => ['الرمز غير صحيح أو منتهي الصلاحية.']]);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();

        return $this->tokenResponse($user, 201);
    }

    /**
     * إعادة إرسال رمز OTP.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $user = User::where('phone', $this->otp->normalize($data['phone']))->first();

        if (! $user) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال غير مسجّل.']]);
        }

        try {
            $this->otp->send($user->phone, 'register');
        } catch (OtpException $e) {
            return $this->otpError($e);
        }

        return response()->json(['message' => 'تم إعادة إرسال رمز التحقق.']);
    }

    /**
     * تسجيل الدخول بالجوال + كلمة السر.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('phone', $this->otp->normalize($data['phone']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال أو كلمة السر غير صحيحة.']]);
        }

        if ($user->status === 'blocked') {
            throw ValidationException::withMessages(['phone' => ['هذا الحساب محظور. راجع الإدارة.']]);
        }

        if (is_null($user->phone_verified_at)) {
            throw ValidationException::withMessages(['phone' => ['الحساب غير مُفعّل. فعّل رقمك عبر رمز التحقق أولاً.']]);
        }

        return $this->tokenResponse($user);
    }

    /**
     * نسيت كلمة السر — يرسل رمز استعادة عبر واتساب.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $user = User::where('phone', $this->otp->normalize($data['phone']))->first();

        if (! $user) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال غير مسجّل.']]);
        }

        try {
            $this->otp->send($user->phone, 'reset');
        } catch (OtpException $e) {
            return $this->otpError($e);
        }

        return response()->json([
            'message' => 'تم إرسال رمز استعادة كلمة السر إلى واتساب.',
            'phone' => $user->phone,
        ]);
    }

    /**
     * إعادة تعيين كلمة السر بعد التحقق من رمز الاستعادة.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'min:4', 'max:6'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::where('phone', $this->otp->normalize($data['phone']))->first();

        if (! $user) {
            throw ValidationException::withMessages(['phone' => ['رقم الجوال غير مسجّل.']]);
        }

        try {
            $verified = $this->otp->verify($user->phone, $data['code'], 'reset');
        } catch (OtpException $e) {
            return $this->otpError($e);
        }

        if (! $verified) {
            throw ValidationException::withMessages(['code' => ['رمز الاستعادة غير صحيح أو منتهي الصلاحية.']]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'phone_verified_at' => $user->phone_verified_at ?? now(),
        ])->save();

        return response()->json(['message' => 'تم تغيير كلمة السر بنجاح. يمكنك تسجيل الدخول الآن.']);
    }

    /**
     * تسجيل الخروج — إلغاء التوكن الحالي.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    /**
     * بيانات المستخدم الحالي.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userData($request->user()));
    }

    /**
     * تعديل الملف الشخصي لعضو الإدارة (الاسم، البريد، الصورة) — صفحة الإعدادات في الداشبورد.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars/staff', 'public');
        } else {
            unset($data['avatar']);
        }

        $user->update($data);

        return response()->json(['message' => 'تم حفظ الملف الشخصي.', 'data' => $this->userData($user->fresh())]);
    }

    /**
     * تغيير كلمة المرور لعضو الإدارة (يتطلب كلمة المرور الحالية).
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
        ]);

        $request->user()->forceFill(['password' => Hash::make($request->input('password'))])->save();

        return response()->json(['message' => 'تم تغيير كلمة المرور.']);
    }

    // ===================== Helpers =====================

    /**
     * يبني الرد الموحّد الذي يتوقعه قالب Vuexy:
     * { accessToken, userData, userAbilityRules }
     */
    protected function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'accessToken' => $token,
            'userData' => $this->userData($user),
            'userAbilityRules' => $this->abilityRules($user),
        ], $status);
    }

    protected function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role,
            'avatar' => $user->avatar ? url('api/media/'.$user->avatar) : null,
        ];
    }

    /**
     * صلاحيات CASL حسب الدور.
     */
    protected function abilityRules(User $user): array
    {
        if ($user->isAdmin()) {
            return [['action' => 'manage', 'subject' => 'all']];
        }

        // المواطن: يقرأ العام ويدير نباتاته
        return [
            ['action' => 'read', 'subject' => 'all'],
            ['action' => 'manage', 'subject' => 'Plant'],
        ];
    }

    protected function otpError(OtpException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'errors' => ['phone' => [$e->getMessage()]],
            'code' => $e->errorCode,
        ], $e->status >= 400 && $e->status < 600 ? $e->status : 422);
    }
}
