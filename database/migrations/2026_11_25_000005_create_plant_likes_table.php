<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('citizen_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['plant_id', 'citizen_id']); // إعجاب واحد لكل مواطن لكل نبتة
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_likes');
    }
};
