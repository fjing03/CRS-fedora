<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassSessionsSeeder extends Seeder
{
    public function run(): void
    {
        $lecturers = DB::table('lecturers')
            ->join('users', 'users.id', '=', 'lecturers.user_id')
            ->pluck('users.id', 'lecturers.staff_id')
            ->toArray();

        $venues = DB::table('venues')
            ->pluck('id', 'room_code')
            ->toArray();

        $moduleIds = DB::table('modules')
            ->pluck('id', 'module_code')
            ->toArray();

        $sessions = $this->getSessionTemplates($lecturers, $venues, $moduleIds);

        foreach ($sessions as $s) {
            $sessionId = DB::table('class_sessions')->insertGetId([
                'semester_id' => 1,
                'module_id' => $s['module_id'],
                'lecturer_id' => $s['lecturer_id'],
                'venue_id' => $s['venue_id'],
                'day_of_week' => $s['day_of_week'],
                'start_time' => $s['start_time'],
                'end_time' => $s['end_time'],
                'session_type' => $s['session_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cohorts = $s['cohorts'] ?? [];
            foreach ($cohorts as $cohortId) {
                DB::table('session_cohorts')->insert([
                    'class_session_id' => $sessionId,
                    'cohort_id' => $cohortId,
                ]);
            }

            $this->markTimeSlotsOccupied($s, $sessionId);
        }
    }

    /**
     * @param  array<string, int>  $map
     */
    private function lookup(array $map, string $key): int
    {
        if (! isset($map[$key])) {
            throw new \RuntimeException("Missing lookup key: {$key}");
        }

        return $map[$key];
    }

    /**
     * @param  array<string, int>  $s
     */
    private function markTimeSlotsOccupied(array $s, int $sessionId): void
    {
        for ($week = 1; $week <= 14; $week++) {
            DB::table('time_slots')
                ->where('semester_id', 1)
                ->where('week_number', $week)
                ->where('day_of_week', $s['day_of_week'])
                ->where('start_time', '>=', $s['start_time'])
                ->where('start_time', '<', $s['end_time'])
                ->where('venue_id', $s['venue_id'])
                ->where('status', 'available')
                ->update([
                    'venue_id' => $s['venue_id'],
                    'class_session_id' => $sessionId,
                    'status' => 'occupied',
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @param  array<string, int>  $lecturers
     * @param  array<string, int>  $venues
     * @param  array<string, int>  $moduleIds
     * @return array<int, array<string, mixed>>
     */
    private function getSessionTemplates(array $lecturers, array $venues, array $moduleIds): array
    {
        $cohortIds = DB::table('cohorts')
            ->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
            ->select('cohorts.id', 'programmes.programme_code', 'cohorts.current_year', 'cohorts.semester', 'cohorts.tutorial_group')
            ->get()
            ->keyBy(function ($c) {
                return sprintf('%s%d(S%d)G%d', $c->programme_code, $c->current_year, $c->semester, $c->tutorial_group);
            });

        return [
            // ===== DFT2(S1)G1 =====
            [
                'module_id' => $moduleIds['BMIT6767'],
                'lecturer_id' => $this->lookup($lecturers, '4288'),
                'venue_id' => $venues['B103'],
                'day_of_week' => 0,
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['DFT2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT6767'],
                'lecturer_id' => $this->lookup($lecturers, '4288'),
                'venue_id' => $venues['B103'],
                'day_of_week' => 3,
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'session_type' => 'T',
                'cohorts' => [$cohortIds['DFT2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT5678'],
                'lecturer_id' => $this->lookup($lecturers, '5770'),
                'venue_id' => $venues['B105'],
                'day_of_week' => 1,
                'start_time' => '11:00:00',
                'end_time' => '13:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['DFT2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT5678'],
                'lecturer_id' => $this->lookup($lecturers, '5770'),
                'venue_id' => $venues['B005'],
                'day_of_week' => 4,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'P',
                'cohorts' => [$cohortIds['DFT2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT3456'],
                'lecturer_id' => $this->lookup($lecturers, '5254'),
                'venue_id' => $venues['B104'],
                'day_of_week' => 2,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['DFT2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT3456'],
                'lecturer_id' => $this->lookup($lecturers, '5254'),
                'venue_id' => $venues['B104'],
                'day_of_week' => 4,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'session_type' => 'T',
                'cohorts' => [$cohortIds['DFT2(S1)G1']->id],
            ],

            // ===== DSF2(S1)G1 =====
            [
                'module_id' => $moduleIds['BMIT6767'],
                'lecturer_id' => $this->lookup($lecturers, '4288'),
                'venue_id' => $venues['B106'],
                'day_of_week' => 1,
                'start_time' => '11:00:00',
                'end_time' => '13:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['DSF2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT6767'],
                'lecturer_id' => $this->lookup($lecturers, '4288'),
                'venue_id' => $venues['B106'],
                'day_of_week' => 3,
                'start_time' => '11:00:00',
                'end_time' => '13:00:00',
                'session_type' => 'T',
                'cohorts' => [$cohortIds['DSF2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT9012'],
                'lecturer_id' => $this->lookup($lecturers, '5425'),
                'venue_id' => $venues['B107'],
                'day_of_week' => 0,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['DSF2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT9012'],
                'lecturer_id' => $this->lookup($lecturers, '5425'),
                'venue_id' => $venues['B009'],
                'day_of_week' => 4,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'P',
                'cohorts' => [$cohortIds['DSF2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT5555'],
                'lecturer_id' => $this->lookup($lecturers, '5652'),
                'venue_id' => $venues['B108'],
                'day_of_week' => 2,
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['DSF2(S1)G1']->id],
            ],

            // ===== Multi-cohort: DFT2(S1)G1 + DSF2(S1)G1 =====
            [
                'module_id' => $moduleIds['BMIT9012'],
                'lecturer_id' => $this->lookup($lecturers, '5425'),
                'venue_id' => $venues['B110'],
                'day_of_week' => 0,
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['DFT2(S1)G1']->id, $cohortIds['DSF2(S1)G1']->id],
            ],

            // ===== RSD2(S1)G1 =====
            [
                'module_id' => $moduleIds['BMIT5555'],
                'lecturer_id' => $this->lookup($lecturers, '5652'),
                'venue_id' => $venues['B102'],
                'day_of_week' => 1,
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RSD2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT6767'],
                'lecturer_id' => $this->lookup($lecturers, '4127'),
                'venue_id' => $venues['B102'],
                'day_of_week' => 3,
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'session_type' => 'T',
                'cohorts' => [$cohortIds['RSD2(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT3456'],
                'lecturer_id' => $this->lookup($lecturers, '5254'),
                'venue_id' => $venues['B101'],
                'day_of_week' => 2,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RSD2(S1)G1']->id],
            ],

            // ===== RSD2(S1)G2 =====
            [
                'module_id' => $moduleIds['BMIT5555'],
                'lecturer_id' => $this->lookup($lecturers, '5652'),
                'venue_id' => $venues['B102'],
                'day_of_week' => 1,
                'start_time' => '11:00:00',
                'end_time' => '13:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RSD2(S1)G2']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT6767'],
                'lecturer_id' => $this->lookup($lecturers, '4127'),
                'venue_id' => $venues['B102'],
                'day_of_week' => 3,
                'start_time' => '11:00:00',
                'end_time' => '13:00:00',
                'session_type' => 'T',
                'cohorts' => [$cohortIds['RSD2(S1)G2']->id],
            ],

            // ===== RSD2(S1)G3 =====
            [
                'module_id' => $moduleIds['BMIT7890'],
                'lecturer_id' => $this->lookup($lecturers, '5599'),
                'venue_id' => $venues['B101'],
                'day_of_week' => 2,
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RSD2(S1)G3']->id],
            ],

            // ===== RSD3(S1)G1 =====
            [
                'module_id' => $moduleIds['BMIT7070'],
                'lecturer_id' => $this->lookup($lecturers, '3221'),
                'venue_id' => $venues['B109'],
                'day_of_week' => 0,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RSD3(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT7074'],
                'lecturer_id' => $this->lookup($lecturers, '3825'),
                'venue_id' => $venues['B010'],
                'day_of_week' => 4,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'session_type' => 'P',
                'cohorts' => [$cohortIds['RSD3(S1)G1']->id],
            ],

            // ===== RSD3(S1)G2 =====
            [
                'module_id' => $moduleIds['BMIT7070'],
                'lecturer_id' => $this->lookup($lecturers, '3221'),
                'venue_id' => $venues['B109'],
                'day_of_week' => 0,
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RSD3(S1)G2']->id],
            ],

            // ===== Multi-cohort: RSD3(S1)G1 + RSD3(S1)G2 =====
            [
                'module_id' => $moduleIds['BMIT9012'],
                'lecturer_id' => $this->lookup($lecturers, '5425'),
                'venue_id' => $venues['B111'],
                'day_of_week' => 1,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RSD3(S1)G1']->id, $cohortIds['RSD3(S1)G2']->id],
            ],

            // ===== RSD3(S1)G3 =====
            [
                'module_id' => $moduleIds['BMIT7072'],
                'lecturer_id' => $this->lookup($lecturers, '2873'),
                'venue_id' => $venues['B108'],
                'day_of_week' => 2,
                'start_time' => '08:00:00',
                'end_time' => '11:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RSD3(S1)G3']->id],
            ],

            // ===== DFT1(S1)G1 =====
            [
                'module_id' => $moduleIds['BMIT2020'],
                'lecturer_id' => $this->lookup($lecturers, '5516'),
                'venue_id' => $venues['B100'],
                'day_of_week' => 0,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['DFT1(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT2020'],
                'lecturer_id' => $this->lookup($lecturers, '5516'),
                'venue_id' => $venues['B005'],
                'day_of_week' => 4,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'P',
                'cohorts' => [$cohortIds['DFT1(S1)G1']->id],
            ],

            // ===== DSF1(S1)G1 =====
            [
                'module_id' => $moduleIds['BMIT1234'],
                'lecturer_id' => $this->lookup($lecturers, '4288'),
                'venue_id' => $venues['B100'],
                'day_of_week' => 1,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'T',
                'cohorts' => [$cohortIds['DSF1(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['BMIT1010'],
                'lecturer_id' => $this->lookup($lecturers, '5514'),
                'venue_id' => $venues['B100'],
                'day_of_week' => 2,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['DSF1(S1)G1']->id],
            ],

            // ===== RSD1(S1)G1 =====
            [
                'module_id' => $moduleIds['BMIT2222'],
                'lecturer_id' => $this->lookup($lecturers, '5516'),
                'venue_id' => $venues['B101'],
                'day_of_week' => 0,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RSD1(S1)G1']->id],
            ],

            // ===== RAF2(S3)G2 — MPU-3133 =====
            [
                'module_id' => $moduleIds['MPU-3133'],
                'lecturer_id' => $this->lookup($lecturers, '4363'),
                'venue_id' => $venues['B110'],
                'day_of_week' => 2,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RAF2(S3)G2']->id],
            ],

            // ===== RAF2(S3)G4 — MPU-3133 =====
            [
                'module_id' => $moduleIds['MPU-3133'],
                'lecturer_id' => $this->lookup($lecturers, '4363'),
                'venue_id' => $venues['B110'],
                'day_of_week' => 2,
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RAF2(S3)G4']->id],
            ],

            // ===== Multi-cohort: RAF2(S3)G2 + RAF2(S3)G4 + RBU1(S1)G1 — MPU-3133 =====
            [
                'module_id' => $moduleIds['MPU-3133'],
                'lecturer_id' => $this->lookup($lecturers, '4363'),
                'venue_id' => $venues['B111'],
                'day_of_week' => 4,
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RAF2(S3)G2']->id, $cohortIds['RAF2(S3)G4']->id, $cohortIds['RBU1(S1)G1']->id],
            ],

            // ===== RBU1(S1)G1 — MPU-3232 =====
            [
                'module_id' => $moduleIds['MPU-3232'],
                'lecturer_id' => $this->lookup($lecturers, '5254'),
                'venue_id' => $venues['B110'],
                'day_of_week' => 0,
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'session_type' => 'L',
                'cohorts' => [$cohortIds['RBU1(S1)G1']->id],
            ],
            [
                'module_id' => $moduleIds['MPU-3232'],
                'lecturer_id' => $this->lookup($lecturers, '5254'),
                'venue_id' => $venues['B102'],
                'day_of_week' => 3,
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'session_type' => 'T',
                'cohorts' => [$cohortIds['RBU1(S1)G1']->id],
            ],
        ];
    }
}
