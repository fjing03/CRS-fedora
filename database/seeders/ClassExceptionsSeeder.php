<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassExceptionsSeeder extends Seeder
{
    public function run(): void
    {
        $exceptions = [
            [1, 1, 'public_holiday'],
            [3, 3, 'public_holiday'],
            [5, 6, 'annual_leave'],
            [8, 9, 'annual_leave'],
            [10, 10, 'medical_leave'],
            [15, 11, 'medical_leave'],
            [12, 12, 'official_event'],
            [18, 13, 'official_event'],
            [20, 14, 'emergency_leave'],
            [2, 2, 'public_holiday'],
            [7, 8, 'annual_leave'],
            [14, 5, 'medical_leave'],
            [17, 7, 'official_event'],
            [19, 11, 'emergency_leave'],
        ];

        foreach ($exceptions as [$sessionId, $weekNumber, $reason]) {
            DB::table('class_exceptions')->insert([
                'class_session_id' => $sessionId,
                'week_number' => $weekNumber,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
