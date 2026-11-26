<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            // nullOnDelete: يبقى سجل الطلب بعد حذف الحساب (للتدقيق)
            $table->foreignId('citizen_id')->nullable()->constrained()->nullOnDelete();
            $table->string('citizen_name')->nullable();   // لقطة للاسم تبقى بعد الحذف
            $table->string('citizen_phone')->nullable();  // لقطة للهاتف
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
