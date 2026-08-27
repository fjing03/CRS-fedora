<?php

namespace Database\Seeders;

use App\Models\ClassSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use SplFileObject;

class LecturerScheduleSeeder extends Seeder
{
    private const DAY_MAP = [
        'Monday' => 0,
        'Tuesday' => 1,
        'Wednesday' => 2,
        'Thursday' => 3,
        'Friday' => 4,
        'Saturday' => 5,
    ];

    public function run(): void
    {
        $csvPath = base_path('../CRS/past sem pdf/lecturer_schedule_5770.csv');

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

        $this->command->info('Parsed '.count($rows).' rows from CSV.');

        $modules = $this->ensureModules();
        $cohorts = $this->ensureCohorts();
        $lecturerUserId = DB::table('lecturers')
            ->join('users', 'users.id', '=', 'lecturers.user_id')
            ->where('lecturers.staff_id', '5770')
            ->value('lecturers.user_id');

        if (! $lecturerUserId) {
            $this->command->error('Lecturer 5770 not found.');

            return;
        }

        $inserted = 0;

        foreach ($rows as $row) {
            $dayStr = trim($row[2]);
            $timeStart = $this->parseTime(trim($row[3]));
            $timeEnd = $this->parseTime(trim($row[4]));
            $modulePart = trim($row[5]);
            $venueRaw = trim($row[6]);
            $cohortRaw = trim($row[7]);

            preg_match('/^(\S+)\s*\((\w)\)$/', $modulePart, $m);
            $moduleCode = $m[1] ?? $modulePart;
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

            $csId = ClassSession::insertGetId([
                'semester_id' => $semesterId,
                'module_id' => $moduleId,
                'lecturer_id' => $lecturerUserId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $timeStart,
                'end_time' => $timeEnd,
                'venue_id' => $venueId,
                'session_type' => $sessionType,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cohortNames = array_map('trim', explode('/', $cohortRaw));
            $linkedCohorts = [];

            foreach ($cohortNames as $cn) {
                $cn = $this->cleanCohortName($cn);
                $cohortId = $cohorts[$cn] ?? null;

                if (! $cohortId && preg_match('/^(.+)\(S(\d+)\)$/', $cn, $m2)) {
                    $prog = $m2[1];
                    $sem = (int) $m2[2];
                    foreach ($cohorts as $k => $v) {
                        if (str_starts_with($k, "{$prog}") && str_contains($k, "(S{$sem})")) {
                            $cohortId = $v;
                            break;
                        }
                    }
                }

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
                    'cohort_id' => $cohortId,
                ]);
            }

            $this->occupyTimeSlots($semesterId, $csId, $venueId, $dayOfWeek, $timeStart, $timeEnd);

            $inserted++;
        }

        $this->command->info("Inserted {$inserted} class sessions for lecturer 5770.");
    }

    private function occupyTimeSlots(int $semesterId, int $csId, int $venueId, int $dayOfWeek, string $start, string $end): void
    {
        $startMin = $this->toMinutes($start);
        $endMin = $this->toMinutes($end);

        DB::table('time_slots')
            ->where('semester_id', $semesterId)
            ->where('venue_id', $venueId)
            ->where('day_of_week', $dayOfWeek)
            ->where('status', 'available')
            ->where(function ($q) use ($startMin, $endMin) {
                $q->where(function ($q2) use ($startMin) {
                    $q2->whereRaw('EXTRACT(HOUR FROM start_time) * 60 + EXTRACT(MINUTE FROM start_time) >= ?', [$startMin]);
                })->where(function ($q2) use ($endMin) {
                    $q2->whereRaw('EXTRACT(HOUR FROM start_time) * 60 + EXTRACT(MINUTE FROM start_time) < ?', [$endMin]);
                });
            })
            ->update([
                'status' => 'occupied',
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
        $raw = strtolower(trim($raw));
        $isPm = str_contains($raw, 'pm');
        $raw = str_replace(['am', 'pm'], '', $raw);

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
        $raw = trim($raw);

        return $raw;
    }

    private function ensureModules(): array
    {
        $map = [];
        $existing = DB::table('modules')->pluck('id', 'module_code')->toArray();

        $needed = ['BMIT3013', 'AMCS2093', 'BMIT2013', 'AMCS1034'];

        foreach ($needed as $code) {
            if (isset($existing[$code])) {
                $map[$code] = $existing[$code];
            } else {
                $id = DB::table('modules')->insertGetId([
                    'module_code' => $code,
                    'module_name' => $code,
                    'allowed_session_types' => 'L,T,P',
                    'created_at' => now(),
                    'updated_at' => now(),
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
            $key = $this->cohortKey($c->programme_code, $c->current_year, $c->semester, $c->tutorial_group);
            $map[$key] = $c->id;

            $shortKey = "{$c->programme_code}{$c->current_year}(S{$c->semester})";
            if (! isset($map[$shortKey])) {
                $map[$shortKey] = $c->id;
            }
        }

        $needed = [
            ['programme_code' => 'RBU', 'year' => 2, 'sem' => 3, 'group' => 1],
            ['programme_code' => 'RBU', 'year' => 2, 'sem' => 3, 'group' => 2],
        ];

        foreach ($needed as $n) {
            $key = $this->cohortKey($n['programme_code'], $n['year'], $n['sem'], $n['group']);
            if (! isset($map[$key])) {
                $programmeId = DB::table('programmes')
                    ->where('programme_code', $n['programme_code'])
                    ->value('id');

                if (! $programmeId) {
                    $this->command->warn("Programme not found: {$n['programme_code']}, skipping cohort.");

                    continue;
                }

                $id = DB::table('cohorts')->insertGetId([
                    'programme_id' => $programmeId,
                    'current_year' => $n['year'],
                    'semester' => $n['sem'],
                    'tutorial_group' => $n['group'],
                    'academic_year' => '2025/26',
                    'intake' => 'June 2024',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $map[$key] = $id;
                $this->command->info("Created cohort: {$n['programme_code']}{$n['year']}(S{$n['sem']})G{$n['group']}");
            }
        }

        return $map;
    }

    private function cohortKey(string $prog, int $year, int $sem, int $group): string
    {
        return "{$prog}{$year}(S{$sem})G{$group}";
    }
}
