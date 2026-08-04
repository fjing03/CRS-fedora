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
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained();
            $table->foreignId('class_session_id')->nullable()->constrained('class_sessions');
            $table->unsignedTinyInteger('week_number');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('venue_id')->constrained();
            $table->string('status', 20);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE time_slots ADD CONSTRAINT time_slots_week_number_check CHECK (week_number BETWEEN 1 AND 14)');
        DB::statement('ALTER TABLE time_slots ADD CONSTRAINT time_slots_day_of_week_check CHECK (day_of_week BETWEEN 0 AND 5)');
        DB::statement('ALTER TABLE time_slots ADD CONSTRAINT time_slots_status_check CHECK (status IN (\'available\', \'pending\', \'occupied\'))');
        DB::statement('ALTER TABLE time_slots ADD CONSTRAINT time_slots_start_time_minute_check CHECK (extract(minute FROM start_time) IN (0, 30))');

        DB::statement('CREATE INDEX time_slots_venue_day_start_week_idx ON time_slots (venue_id, day_of_week, start_time, week_number)');
        DB::statement('CREATE INDEX time_slots_status_idx ON time_slots (status)');

        DB::statement('
            CREATE UNIQUE INDEX time_slots_no_double_book_idx
                ON time_slots (venue_id, day_of_week, start_time, week_number)
                WHERE status IN (\'pending\', \'occupied\')
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
