<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governorates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();      // اسم المحافظة
            $table->string('code', 20)->nullable(); // رمز اختياري
            $table->boolean('is_active')->default(true); // ظاهر / مخفي
            $table->softDeletes();                  // حذف قابل للاسترجاع
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governorates');
    }
};
