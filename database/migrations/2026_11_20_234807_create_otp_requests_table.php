<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول تتبّع طلبات OTP. مزوّد Arqam هو اللي يولّد الرمز ويتحقق منه،
     * فما نخزّن الرمز هنا — نخزّن فقط سجل الطلب للتدقيق وتحديد المعدّل (rate limit).
     */
    public function up(): void
    {
        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->enum('purpose', ['register', 'login', 'reset'])->default('register');
            $table->enum('channel', ['whatsapp', 'sms'])->default('whatsapp');
            $table->string('provider_ref')->nullable();     // معرّف الرسالة من Arqam إن وُجد
            $table->enum('status', ['sent', 'verified', 'failed'])->default('sent');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_requests');
    }
};
