<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->string('type')->nullable()->after('category_id');       // نوع النبتة (نص يكتبه المواطن)
            $table->date('planting_date')->nullable()->after('name');        // تاريخ الزرع
            $table->foreignId('governorate_id')->nullable()->after('longitude')->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('governorate_id')->constrained()->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('governorate_id');
            $table->dropConstrainedForeignId('district_id');
            $table->dropSoftDeletes();
            $table->dropColumn(['type', 'planting_date']);
        });
    }
};
