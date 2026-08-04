<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HolidaysSeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            [1, 0, 'Public Holiday'],
            [3, 1, 'Public Holiday'],
            [3, 3, 'Public Holiday'],
            [5, 2, 'Public Holiday'],
            [7, 4, 'Public Holiday'],
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
