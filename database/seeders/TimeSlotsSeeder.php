<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TimeSlotsSeeder extends Seeder
{
    public function run(): void
    {
        $venueIds = DB::table('venues')->pluck('id')->toArray();
        $rows = [];

        foreach ($venueIds as $venueId) {
            for ($week = 1; $week <= 14; $week++) {
                for ($day = 0; $day <= 5; $day++) {
                    for ($hour = 8; $hour < 18; $hour++) {
                        foreach ([0, 30] as $minute) {
                            $startH = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
                            $startM = str_pad((string) $minute, 2, '0', STR_PAD_LEFT);
                            $endMinute = $minute + 30;
                            $endHour = $hour;
                            if ($endMinute >= 60) {
                                $endMinute -= 60;
                                $endHour += 1;
                            }
                            $endH = str_pad((string) $endHour, 2, '0', STR_PAD_LEFT);
                            $endM = str_pad((string) $endMinute, 2, '0', STR_PAD_LEFT);

                            $rows[] = [
                                'semester_id' => 1,
                                'venue_id' => $venueId,
                                'class_session_id' => null,
                                'week_number' => $week,
                                'day_of_week' => $day,
                                'start_time' => "{$startH}:{$startM}:00",
                                'end_time' => "{$endH}:{$endM}:00",
                                'status' => 'available',
                                'version' => 1,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            if (count($rows) >= 5000) {
                                DB::table('time_slots')->insert($rows);
                                $rows = [];
                            }
                        }
                    }
                }
            }
        }

        if (! empty($rows)) {
            DB::table('time_slots')->insert($rows);
        }
    }
}
