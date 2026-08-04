<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SemestersSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('semesters')->insert([
            'id' => 1,
            'semester_code' => '202605',
            'label' => '202605 Semester',
            'start_date' => '2026-08-31',
            'end_date' => '2026-12-06',
            'week_count' => 14,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
