<?php

namespace App\Services\Otp;

use RuntimeException;

/**
 * استثناء يُرمى عند فشل عملية OTP (إرسال أو تحقق) مع رمز خطأ قابل للقراءة آلياً.
 */
class OtpException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    public static function fromArqam(?string $errorCode, ?string $errorMessage, int $status): self
    {
        return new self(
            $errorMessage ?: 'فشل طلب OTP لدى المزوّد.',
            $errorCode,
            $status,
        );
    }
}
