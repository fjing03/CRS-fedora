<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use SplFileObject;

class CsvTimetableSeeder extends Seeder
{
    private const DAY_MAP = [
        'Monday'    => 0,
        'Tuesday'   => 1,
        'Wednesday' => 2,
        'Thursday'  => 3,
        'Friday'    => 4,
        'Saturday'  => 5,
    ];

    public function run(): void
    {
        $csvPath = base_path('../CRS/past sem pdf/lecturer_timetable_pls.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV not found: {$csvPath}");

            return;
        }

        $file = new SplFileObject($csvPath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $semesterId = DB::table('semesters')->where('semester_code', '202605')->value('id');
        if (! $semesterId) {
            $this->command->error('Semester 202605 not found.');

            return;
        }

        $header = $file->fgetcsv();
        $rows = [];
        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if ($row && count($row) >= 8 && ! empty($row[0])) {
                $rows[] = $row;
            }
        }

        $this->command->info("Parsed " . count($rows) . " rows from CSV.");

        $modules = $this->ensureModules();
        $cohorts = $this->ensureCohorts();

        $lecturerCache = [];
        $inserted = 0;

        foreach ($rows as $row) {
            $staffId    = trim($row[0]);
            $dayStr     = trim($row[2]);
            $timeStart  = $this->parseTime(trim($row[3]));
            $timeEnd    = $this->parseTime(trim($row[4]));
            $modulePart = trim($row[5]);
            $venueRaw   = trim($row[6]);
            $cohortRaw  = trim($row[7]);

            if (! isset($lecturerCache[$staffId])) {
                $lecturerCache[$staffId] = DB::table('lecturers')
                    ->where('staff_id', $staffId)
                    ->value('user_id');
            }
            $lecturerUserId = $lecturerCache[$staffId];
            if (! $lecturerUserId) {
                $this->command->warn("Lecturer not found: {$staffId}, skipping.");

                continue;
            }

            preg_match('/^(\S+)\s*\((\w)\)$/', $modulePart, $m);
            $moduleCode  = $m[1] ?? $modulePart;
            $sessionType = $m[2] ?? 'L';

            $venueCode = preg_replace('/\s*[-–].*$/', '', $venueRaw);

            $moduleId = $modules[$moduleCode] ?? null;
            if (! $moduleId) {
                $this->command->warn("Module not found: {$moduleCode}, skipping.");

                continue;
            }

            $venueId = DB::table('venues')->where('room_code', $venueCode)->value('id');
            if (! $venueId) {
                $this->command->warn("Venue not found: {$venueCode}, skipping.");

                continue;
            }

            $dayOfWeek = self::DAY_MAP[$dayStr] ?? null;
            if ($dayOfWeek === null) {
                $this->command->warn("Unknown day: {$dayStr}, skipping.");

                continue;
            }

            $csId = DB::table('class_sessions')->insertGetId([
                'semester_id'   => $semesterId,
                'module_id'     => $moduleId,
                'lecturer_id'   => $lecturerUserId,
                'day_of_week'   => $dayOfWeek,
                'start_time'    => $timeStart,
                'end_time'      => $timeEnd,
                'venue_id'      => $venueId,
                'session_type'  => $sessionType,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $this->occupyTimeSlots($semesterId, $csId, $venueId, $dayOfWeek, $timeStart, $timeEnd);

            $cohortNames = array_map('trim', explode('/', $cohortRaw));
            $linkedCohorts = [];

            foreach ($cohortNames as $cn) {
                $cn = $this->cleanCohortName($cn);
                $cohortId = $this->resolveCohort($cn, $cohorts);

                if (! $cohortId) {
                    $this->command->warn("Cohort not found: {$cn}, skipping link.");

                    continue;
                }

                if (in_array($cohortId, $linkedCohorts, true)) {
                    continue;
                }
                $linkedCohorts[] = $cohortId;

                DB::table('session_cohorts')->insert([
                    'class_session_id' => $csId,
                    'cohort_id'        => $cohortId,
                ]);
            }

            $inserted++;
        }

        $this->command->info("Inserted {$inserted} class sessions.");
    }

    private function resolveCohort(string $raw, array $cohorts): ?int
    {
        if (isset($cohorts[$raw])) {
            return $cohorts[$raw];
        }

        if (preg_match('/^(.+?)\s*\(S(\d+)\)\s*G(\d+)$/', $raw, $m)) {
            $key = "{$m[1]}(S{$m[2]})G{$m[3]}";
            if (isset($cohorts[$key])) {
                return $cohorts[$key];
            }
        }

        if (preg_match('/^(.+?)\s*\(S(\d+)\)$/', $raw, $m)) {
            $prefix = "{$m[1]}(S{$m[2]})";
            foreach ($cohorts as $k => $v) {
                if (str_starts_with($k, $prefix)) {
                    return $v;
                }
            }
        }

        return null;
    }

    private function occupyTimeSlots(int $semesterId, int $csId, int $venueId, int $dayOfWeek, string $start, string $end): void
    {
        $startMin = $this->toMinutes($start);
        $endMin   = $this->toMinutes($end);

        DB::table('time_slots')
            ->where('semester_id', $semesterId)
            ->where('venue_id', $venueId)
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'available')
            ->where(function ($q) use ($startMin, $endMin) {
                $q->whereRaw("EXTRACT(HOUR FROM start_time) * 60 + EXTRACT(MINUTE FROM start_time) >= ?", [$startMin])
                  ->whereRaw("EXTRACT(HOUR FROM start_time) * 60 + EXTRACT(MINUTE FROM start_time) < ?", [$endMin]);
            })
            ->update([
                'status'           => 'occupied',
                'class_session_id' => $csId,
            ]);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = explode(':', substr($time, 0, 5));

        return (int) $h * 60 + (int) $m;
    }

    private function parseTime(string $raw): string
    {
        $raw   = strtolower(trim($raw));
        $isPm  = str_contains($raw, 'pm');
        $raw   = str_replace(['am', 'pm'], '', $raw);
        [$h, $m] = explode(':', $raw);
        $h = (int) $h;
        $m = (int) $m;

        if ($isPm && $h !== 12) {
            $h += 12;
        }
        if (! $isPm && $h === 12) {
            $h = 0;
        }

        return sprintf('%02d:%02d:00', $h, $m);
    }

    private function cleanCohortName(string $raw): string
    {
        $raw = preg_replace('/\s+Jefferson\s+Ng\s+\(\d+\)/i', '', $raw);
        $raw = preg_replace('/\s+Neoh Siew Bok\s+\(\d+\)/i', '', $raw);
        $raw = preg_replace('/\s+Chew Xin Ying\s+\(\d+\)/i', '', $raw);
        $raw = preg_replace('/\s+Tan Yee Qing\s+\(\d+\)/i', '', $raw);
        $raw = preg_replace('/\s+Liew Shen\s+\(\d+\)/i', '', $raw);
        $raw = preg_replace('/\s+Yong Zhe\s+\(\d+\)/i', '', $raw);

        return trim($raw);
    }

    private function ensureModules(): array
    {
        $map      = [];
        $existing = DB::table('modules')->pluck('id', 'module_code')->toArray();

        $needed = ['AMSE1003', 'BMSE3153', 'BMSE2163', 'AMSE2003', 'BMIT2043', 'AMIS1003', 'BMCS1013', 'BMCS1113'];

        foreach ($needed as $code) {
            if (isset($existing[$code])) {
                $map[$code] = $existing[$code];
            } else {
                $id = DB::table('modules')->insertGetId([
                    'module_code'           => $code,
                    'module_name'           => $code,
                    'allowed_session_types' => 'L,T,P',
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
                $map[$code] = $id;
                $this->command->info("Created module: {$code}");
            }
        }

        foreach ($existing as $code => $id) {
            $map[$code] = $id;
        }

        return $map;
    }

    private function ensureCohorts(): array
    {
        $map = [];
        $existing = DB::table('cohorts')
            ->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
            ->select('cohorts.id', 'programmes.programme_code', 'cohorts.current_year', 'cohorts.semester', 'cohorts.tutorial_group')
            ->get();

        foreach ($existing as $c) {
            $key = "{$c->programme_code}{$c->current_year}(S{$c->semester})G{$c->tutorial_group}";
            $map[$key] = $c->id;

            $shortKey = "{$c->programme_code}{$c->current_year}(S{$c->semester})";
            if (! isset($map[$shortKey])) {
                $map[$shortKey] = $c->id;
            }
        }

        $needed = [
            ['programme_code' => 'RSD', 'year' => 3, 'sem' => 1, 'group' => 4],
            ['programme_code' => 'RSD', 'year' => 3, 'sem' => 1, 'group' => 5],
        ];

        foreach ($needed as $n) {
            $key = "{$n['programme_code']}{$n['year']}(S{$n['sem']})G{$n['group']}";
            if (! isset($map[$key])) {
                $programmeId = DB::table('programmes')
                    ->where('programme_code', $n['programme_code'])
                    ->value('id');

                if (! $programmeId) {
                    $this->command->warn("Programme not found: {$n['programme_code']}, skipping cohort.");

                    continue;
                }

                $id = DB::table('cohorts')->insertGetId([
                    'programme_id'   => $programmeId,
                    'current_year'   => $n['year'],
                    'semester'       => $n['sem'],
                    'tutorial_group' => $n['group'],
                    'academic_year'  => '2025/26',
                    'intake'         => 'June 2023',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);

                $map[$key] = $id;
                $this->command->info("Created cohort: {$key}");
            }
        }

        return $map;
    }
}
