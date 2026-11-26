<?php

namespace App\Services\Otp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * درايفر Arqam OTP — الإنتاج عبر واتساب.
 *
 * التوثيق: https://otp.arqam.tech/apidoc/
 *  - المصادقة: هيدر X-API-Key
 *  - إرسال:    POST /sms/otp            { phoneNumber }           => { success, messageId, status, cost }
 *  - إعادة:    POST /sms/reset-password { phoneNumber }           => { success, messageId, ... }
 *  - تحقق:     POST /sms/verify         { messageId, code }       => { success, message }
 *
 * ملاحظة: الرمز نتركه لـ Arqam يولّده (ما نرسل otpCode)، والرمز ينتهي بعد 5 دقائق.
 */
class ArqamOtpDriver implements OtpDriver
{
    public function __construct(
        protected string $baseUrl,
        protected ?string $apiKey,
        protected string $otpTemplate = 'otp',
        protected int $timeout = 15,
    ) {}

    public function send(string $phone, string $purpose = 'register'): string
    {
        // رمز إعادة تعيين كلمة السر له endpoint خاص بقالب مختلف.
        $endpoint = $purpose === 'reset' ? '/sms/reset-password' : '/sms/otp';

        $payload = ['phoneNumber' => $phone];

        if ($purpose !== 'reset') {
            $payload['templateName'] = $this->otpTemplate;
        }

        $response = $this->client()->post($endpoint, $payload);
        $body = $response->json() ?? [];

        if (! $response->successful() || ($body['success'] ?? false) !== true) {
            Log::warning('[OTP:arqam] فشل إرسال الرمز', ['status' => $response->status(), 'body' => $body]);

            throw OtpException::fromArqam(
                $body['errorCode'] ?? null,
                $body['errorMessage'] ?? null,
                $response->status() ?: 422,
            );
        }

        $messageId = $body['messageId'] ?? null;

        if (! is_string($messageId) || $messageId === '') {
            throw new OtpException('لم يرجّع المزوّد messageId صالحاً.', 'INVALID_PROVIDER_RESPONSE');
        }

        return $messageId;
    }

    public function verify(string $messageId, string $code): bool
    {
        $response = $this->client()->post('/sms/verify', [
            'messageId' => $messageId,
            'code' => $code,
        ]);

        $body = $response->json() ?? [];

        // رمز خاطئ أو منتهٍ يرجّع HTTP 200 مع success:false — نعتبره "فشل تحقق" لا "خطأ طلب".
        if ($response->status() === 200) {
            return ($body['success'] ?? false) === true;
        }

        // 400/401 وغيرها = خطأ في الطلب نفسه (صيغة رمز غير صحيحة، مفتاح غير صالح...).
        throw OtpException::fromArqam(
            $body['errorCode'] ?? null,
            $body['errorMessage'] ?? 'تعذّر التحقق من الرمز.',
            $response->status() ?: 422,
        );
    }

    protected function client(): PendingRequest
    {
        if (empty($this->apiKey)) {
            throw new OtpException('مفتاح Arqam (ARQAM_API_KEY) غير مضبوط في .env', 'MISSING_API_KEY', 500);
        }

        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeaders(['X-API-Key' => $this->apiKey]);
    }
}
