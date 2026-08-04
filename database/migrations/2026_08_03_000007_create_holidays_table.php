<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->unsignedTinyInteger('day_of_week');
            $table->string('label');
            $table->timestamps();

            $table->index(['semester_id', 'week_number', 'day_of_week']);
        });

        DB::statement('ALTER TABLE holidays ADD CONSTRAINT holidays_day_of_week_check CHECK (day_of_week BETWEEN 0 AND 5)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
