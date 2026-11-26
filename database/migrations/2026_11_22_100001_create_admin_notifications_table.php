<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 30)->default('primary');
            $table->string('link')->nullable();
            $table->boolean('is_seen')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_seen', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
