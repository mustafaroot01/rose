<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * توسيع قيم عمود الدور ليشمل "supervisor" (المشرف) إلى جانب admin و citizen.
     * كان العمود enum('admin','citizen') فقط، ما يمنع إنشاء/تعيين دور المشرف.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin', 'supervisor', 'citizen') NOT NULL DEFAULT 'citizen'");
    }

    public function down(): void
    {
        // إعادة أي مشرف إلى admin قبل تضييق القيم لتجنّب اقتطاع البيانات
        DB::table('users')->where('role', 'supervisor')->update(['role' => 'admin']);
        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin', 'citizen') NOT NULL DEFAULT 'citizen'");
    }
};
