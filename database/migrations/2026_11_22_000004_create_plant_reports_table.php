<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete(); // مَن بلّغ
            $table->enum('reason', ['withered', 'dead', 'needs_water', 'vandalism', 'wrong_location', 'other']);
            $table->text('reason_note')->nullable();   // تفصيل السبب
            $table->enum('status', ['new', 'in_review', 'action_taken', 'dismissed'])->default('new');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete(); // المشرف المعالِج
            $table->text('admin_note')->nullable();     // ملاحظة/إجراء المشرف
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_reports');
    }
};
