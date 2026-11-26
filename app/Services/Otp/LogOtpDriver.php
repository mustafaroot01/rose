<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * درايفر التجربة المحلي.
 * ما يرسل أي رسالة حقيقية — يكتب الرمز الثابت في اللوق، ويقبل نفس الرمز عند التحقق.
 * مثالي للتطوير بدون استهلاك رصيد Arqam.
 */
class LogOtpDriver implements OtpDriver
{
    public function send(string $phone, string $purpose = 'register'): string
    {
        $code = (string) config('otp.log_fixed_code', '123456');
        $messageId = (string) Str::uuid();

        Log::info("[OTP:log] رمز التحقق للرقم {$phone} (الغرض: {$purpose}) هو: {$code}", [
            'messageId' => $messageId,
        ]);

        return $messageId;
    }

    public function verify(string $messageId, string $code): bool
    {
        return $code === (string) config('otp.log_fixed_code', '123456');
    }
}
