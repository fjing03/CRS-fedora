<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Canonical demo-semester holidays (KNOWLEDGE.md §6 / DATASET-NOTES.md §4):
 * Week-1 Monday = 2026-09-21; Deepavali itself (Sun 8 Nov) is outside the
 * Mon–Sat grid and is docs-only. Replaces the 5 stale placeholder rows.
 */
class HolidaysSeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            [8, 0, 'Deepavali Holiday (In Lieu)'],
            [14, 3, 'Christmas Eve'],
            [14, 4, 'Christmas Day'],
        ];

        foreach ($holidays as [$week, $dayOfWeek, $label]) {
            DB::table('holidays')->insert([
                'semester_id' => 1,
                'week_number' => $week,
                'day_of_week' => $dayOfWeek,
                'label' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
