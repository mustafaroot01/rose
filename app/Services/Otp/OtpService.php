<?php

namespace App\Services\Otp;

use App\Models\OtpRequest;
use Illuminate\Support\Str;

/**
 * الواجهة الرئيسية للتعامل مع OTP في التطبيق.
 *
 * تستخدم كذا:
 *   $otp = app(OtpService::class);
 *   $request = $otp->send('0555555555', 'register');   // يرسل الرمز ويسجّله
 *   $ok = $otp->verify('0555555555', '123456', 'register'); // يتحقق
 *
 * تختار الدرايفر تلقائياً حسب config('otp.driver').
 */
class OtpService
{
    protected OtpDriver $driver;

    public function __construct(?OtpDriver $driver = null)
    {
        $this->driver = $driver ?? $this->resolveDriver();
    }

    /**
     * يرسل رمز تحقق ويسجّل الطلب. يرجّع سجل OtpRequest.
     */
    public function send(string $phone, string $purpose = 'register'): OtpRequest
    {
        $intl = $this->normalize($phone);

        $messageId = $this->driver->send($intl, $purpose);

        return OtpRequest::create([
            'phone' => $phone,
            'purpose' => $purpose,
            'channel' => config('otp.channel', 'whatsapp'),
            'provider_ref' => $messageId,
            'status' => 'sent',
        ]);
    }

    /**
     * يتحقق من الرمز لأحدث طلب OTP نشِط لنفس الرقم والغرض.
     */
    public function verify(string $phone, string $code, string $purpose = 'register'): bool
    {
        $request = OtpRequest::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('status', 'sent')
            ->latest()
            ->first();

        if (! $request || ! $request->provider_ref) {
            return false;
        }

        $verified = $this->driver->verify($request->provider_ref, $code);

        if ($verified) {
            $request->update(['status' => 'verified', 'verified_at' => now()]);
        }

        return $verified;
    }

    /**
     * يوحّد أي صيغة رقم إلى الصيغة الدولية (+<رمز الدولة><الرقم>).
     * يقبل الرقم بصفر أو بدون صفر أو مع رمز الدولة أو بالصيغة الدولية.
     *
     * أمثلة (رمز الدولة الافتراضي 964):
     *   07710030147    => +9647710030147   (محلي بصفر)
     *   7710030147     => +9647710030147   (محلي بدون صفر)
     *   9647710030147  => +9647710030147   (يحوي رمز الدولة بدون +)
     *   +9647710030147 => +9647710030147   (دولي كما هو)
     *   009647710030147=> +9647710030147   (بادئة 00 الدولية)
     */
    public function normalize(string $phone): string
    {
        $phone = trim($phone);
        $hadPlus = Str::startsWith($phone, '+');

        // إزالة أي رموز غير رقمية (مسافات، شرطات، أقواس...)
        $digits = preg_replace('/\D+/', '', $phone);
        $cc = (string) config('otp.default_country_code', '964');

        if ($digits === '') {
            return $phone;
        }

        // جاء بالصيغة الدولية أصلاً
        if ($hadPlus) {
            return '+'.$digits;
        }

        // بادئة 00 الدولية => +
        if (Str::startsWith($digits, '00')) {
            return '+'.substr($digits, 2);
        }

        // رقم محلي يبدأ بصفر => نحذف الصفر ونضيف رمز الدولة
        if (Str::startsWith($digits, '0')) {
            return '+'.$cc.ltrim($digits, '0');
        }

        // يحوي رمز الدولة أصلاً (طويل بما يكفي) => نضيف + فقط
        if (strlen($digits) >= 11) {
            return '+'.$digits;
        }

        // رقم محلي بدون صفر => نضيف رمز الدولة
        return '+'.$cc.$digits;
    }

    protected function resolveDriver(): OtpDriver
    {
        return match (config('otp.driver', 'log')) {
            'arqam' => new ArqamOtpDriver(
                baseUrl: config('otp.arqam.base_url'),
                apiKey: config('otp.arqam.api_key'),
                otpTemplate: config('otp.arqam.otp_template', 'otp'),
                timeout: (int) config('otp.arqam.timeout', 15),
            ),
            default => new LogOtpDriver,
        };
    }
}
