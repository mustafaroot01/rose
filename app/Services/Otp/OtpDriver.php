<?php

namespace App\Services\Otp;

/**
 * عقد موحّد لأي مزوّد OTP.
 * كل درايفر (Arqam / Log) يطبّق هذا العقد، فالباقي من التطبيق ما يهمه مين المزوّد.
 */
interface OtpDriver
{
    /**
     * يرسل رمز تحقق إلى رقم جوال (بالصيغة الدولية، مثل +9665XXXXXXXX).
     *
     * @param  string  $phone  الرقم بالصيغة الدولية.
     * @param  string  $purpose  الغرض: register | login | reset
     * @return string messageId الذي يُستخدم لاحقاً في التحقق.
     *
     * @throws OtpException عند فشل الإرسال.
     */
    public function send(string $phone, string $purpose = 'register'): string;

    /**
     * يتحقق من الرمز الذي أدخله المستخدم مقابل الرمز الذي أُرسل.
     *
     * @param  string  $messageId  المعرّف الراجع من send().
     * @param  string  $code  الرمز الذي أدخله المستخدم.
     * @return bool true إذا كان الرمز صحيحاً وغير منتهٍ.
     *
     * @throws OtpException عند خطأ في الطلب نفسه (مثل صيغة رمز غير صحيحة أو مفتاح غير صالح).
     */
    public function verify(string $messageId, string $code): bool;
}
