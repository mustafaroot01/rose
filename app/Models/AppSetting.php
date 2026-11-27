<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * إعدادات التطبيق (مفتاح ← قيمة JSON).
 * المفاتيح المعروفة ومعانيها في DEFAULTS — أي مفتاح آخر يُتجاهل.
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'app_settings.all';

    /** القيم الافتراضية لكل الإعدادات المدعومة. */
    public const DEFAULTS = [
        // روابط التواصل الاجتماعي (تظهر في إعدادات التطبيق إن لم تكن فارغة)
        'social_instagram' => '',
        'social_facebook' => '',
        'social_whatsapp' => '',
        'social_tiktok' => '',
        // قفل التحديث: عند التفعيل، النسخ الأقدم من أقل إصدار لمنصّتها لا تعمل حتى تُحدَّث.
        // لكل منصة رقمها (فارغ = لا قفل على تلك المنصة).
        'force_update' => false,
        'min_version_android' => '',
        'min_version_ios' => '',
        'update_message' => 'يتوفر إصدار جديد من التطبيق. يرجى التحديث للمتابعة.',
        'android_url' => '',
        'ios_url' => '',
    ];

    /** كل الإعدادات (مع الافتراضيات) — مخزّنة مؤقتاً. */
    public static function all_values(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $stored = static::query()->pluck('value', 'key')
                ->map(fn ($v) => json_decode($v, true))
                ->all();

            return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        });
    }

    /** حفظ عدّة إعدادات دفعة واحدة (المفاتيح غير المعروفة تُتجاهل). */
    public static function setMany(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
