<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // نوع الحساب (مرتبط بجدول account_types المُدار)
            $table->foreignId('account_type_id')->nullable()->after('role')->constrained()->nullOnDelete();

            // الصورة الرمزية
            $table->string('avatar')->nullable()->after('account_type_id');

            // ربط الموقع
            $table->foreignId('governorate_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('governorate_id')->constrained()->nullOnDelete();
            $table->foreignId('nahiya_id')->nullable()->after('district_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('governorate_id');
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('nahiya_id');
            $table->dropConstrainedForeignId('account_type_id');
            $table->dropColumn('avatar');
        });
    }
};
