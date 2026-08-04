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
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('session_type', 5);
            $table->timestamps();

            $table->index(['lecturer_id', 'day_of_week', 'start_time']);
            $table->index(['venue_id', 'day_of_week', 'start_time']);
        });

        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_day_of_week_check CHECK (day_of_week BETWEEN 0 AND 5)');
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT class_sessions_session_type_check CHECK (session_type IN (\'L\', \'T\', \'P\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
