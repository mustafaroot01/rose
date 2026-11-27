<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الجهة الراعية للحملة: اسم + شعار + رابط (كلها اختيارية).
 * تظهر في صفحة الحملة بالتطبيق وفي شهادة المشاركة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('sponsor_name')->nullable()->after('cover_path');
            $table->string('sponsor_logo_path')->nullable()->after('sponsor_name');
            $table->string('sponsor_url', 500)->nullable()->after('sponsor_logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['sponsor_name', 'sponsor_logo_path', 'sponsor_url']);
        });
    }
};
