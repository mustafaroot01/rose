<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // مَن نفّذ الحدث
            // نوع الحدث: planted | watered | reported | report_handled | status_changed | updated
            $table->string('type', 40);
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_activities');
    }
};
