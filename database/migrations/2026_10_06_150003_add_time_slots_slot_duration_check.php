<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // enforce the 30-minute grid-cell invariant so time_slots_no_double_book_idx is provably correct
        DB::statement("ALTER TABLE time_slots ADD CONSTRAINT time_slots_slot_duration_check CHECK (end_time = start_time + interval '30 minutes')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE time_slots DROP CONSTRAINT IF EXISTS time_slots_slot_duration_check');
    }
};
