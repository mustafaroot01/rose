<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** إعدادات التطبيق: روابط التواصل + قفل التحديث. */
class AppSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => AppSetting::all_values()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'social_instagram' => ['nullable', 'url', 'max:500'],
            'social_facebook' => ['nullable', 'url', 'max:500'],
            'social_whatsapp' => ['nullable', 'string', 'max:500'],
            'social_tiktok' => ['nullable', 'url', 'max:500'],
            'force_update' => ['sometimes', 'boolean'],
            'min_version_android' => ['nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+){0,2}$/'],
            'min_version_ios' => ['nullable', 'string', 'max:20', 'regex:/^\d+(\.\d+){0,2}$/'],
            'update_message' => ['nullable', 'string', 'max:500'],
            'android_url' => ['nullable', 'url', 'max:500'],
            'ios_url' => ['nullable', 'url', 'max:500'],
        ], [
            'min_version_android.regex' => 'رقم الإصدار يجب أن يكون بالشكل 1.2.0',
            'min_version_ios.regex' => 'رقم الإصدار يجب أن يكون بالشكل 1.2.0',
        ]);

        if (($data['force_update'] ?? false) && empty($data['min_version_android']) && empty($data['min_version_ios'])) {
            $msg = 'حدّد أقل إصدار لمنصة واحدة على الأقل قبل تفعيل قفل التحديث.';

            return response()->json([
                'message' => $msg,
                'errors' => ['min_version_android' => [$msg], 'min_version_ios' => [$msg]],
            ], 422);
        }

        // الحقول الفارغة تُحفظ كنص فارغ (null → '')
        AppSetting::setMany(array_map(fn ($v) => $v ?? '', $data));

        return response()->json(['message' => 'تم حفظ إعدادات التطبيق.', 'data' => AppSetting::all_values()]);
    }
}
