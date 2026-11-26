<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plant_waterings', function (Blueprint $table) {
            $table->foreignId('citizen_id')
                ->nullable()
                ->after('user_id')
                ->constrained('citizens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plant_waterings', function (Blueprint $table) {
            $table->dropForeign(['citizen_id']);
            $table->dropColumn('citizen_id');
        });
    }
};
