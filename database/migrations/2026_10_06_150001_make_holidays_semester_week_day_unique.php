<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // (1) add unique first — no window where the guardrail is missing
        Schema::table('holidays', function (Blueprint $table): void {
            $table->unique(['semester_id', 'week_number', 'day_of_week'], 'holidays_semester_id_week_number_day_of_week_unique');
        });

        // (2) then retire the plain duplicate
        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropIndex('holidays_semester_id_week_number_day_of_week_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table): void {
            $table->index(['semester_id', 'week_number', 'day_of_week'], 'holidays_semester_id_week_number_day_of_week_index');
        });

        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropUnique('holidays_semester_id_week_number_day_of_week_unique');
        });
    }
};
