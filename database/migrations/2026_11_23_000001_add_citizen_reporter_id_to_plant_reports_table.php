<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plant_reports', function (Blueprint $table) {
            $table->foreignId('citizen_reporter_id')
                ->nullable()
                ->after('reporter_id')
                ->constrained('citizens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plant_reports', function (Blueprint $table) {
            $table->dropForeign(['citizen_reporter_id']);
            $table->dropColumn('citizen_reporter_id');
        });
    }
};
