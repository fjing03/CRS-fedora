<?php

namespace Database\Seeders;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function abs;
use function array_diff;
use function array_map;
use function array_sum;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function sort;
use function sprintf;
use function trim;
use function var_export;

/**
 * RealScheduleSeeder — loads the real semester-202505 schedule datasets from
 * dataset/import/ as the system's sole class-session source of truth.
 *
 * Frozen design: .sdd/changes/import-real-schedule-records/design.md (§3,
 * D1–D9); spec: specs/real-schedule-importer/spec.md (R1–R8).
 *
 * D1 — the importer CORE lives here; ImportRealScheduleCommand adds only the
 * mode gate (R3) and snapshot wrapper. DatabaseSeeder chains this seeder
 * directly (additive path on fresh test DBs). Phases:
 *
 *   0  preflight()        read-only, every mode, before any write (R2)
 *   1  deleteExisting()   replace mode only, own transaction (R4)
 *   2+3 importAll()       references + sessions + occupancy, ONE transaction
 *   4  verify()           read-only, after commit (R7)
 *
 * Dataset facts (dataset/DATASET-NOTES.md): 101/101/155 rows, 147 room-hours,
 * 42 course codes, 14 cohort labels; occupied slots 4116 − 153
 * holiday-affected = 3963.
 */
class RealScheduleSeeder extends Seeder
{
    private const SEMESTER_CODE = '202605';

    private const SEMESTER_START = '2026-09-21';

    private const SEMESTER_END = '2026-12-27';

    private const EXPECTED_VENUE_ROWS = 101;

    private const EXPECTED_LECTURER_ROWS = 101;

    private const EXPECTED_PROGRAMME_ROWS = 155;

    private const EXPECTED_VENUE_HOURS = 147.0;

    private const EXPECTED_COURSE_CODES = 42;

    private const EXPECTED_COHORT_LABELS = 14;

    private const EXPECTED_SESSIONS = 101;

    private const EXPECTED_COHORT_LINKS = 155;

    private const EXPECTED_OCCUPIED_SLOTS = 3963;

    private const EXPECTED_MODULES = 42;

    private const EXPECTED_HOLIDAYS = 3;

    private const EXPECTED_LAB_WARN_ROWS = 11;

    /** Holiday grid pairs: [week_number, day_of_week]. */
    private const HOLIDAY_PAIRS = [[8, 0], [14, 3], [14, 4]];

    private const HOLIDAY_ROWS = [
        [8, 0, 'Deepavali Holiday (In Lieu)'],
        [14, 3, 'Christmas Eve'],
        [14, 4, 'Christmas Day'],
    ];

    private const DAY_MAP = [
        'Monday' => 0,
        'Tuesday' => 1,
        'Wednesday' => 2,
        'Thursday' => 3,
        'Friday' => 4,
        'Saturday' => 5,
    ];

    private const TYPE_MAP = [
        'Lecture' => 'L',
        'Tutorial' => 'T',
        'Practical' => 'P',
    ];

    private const SESSION_TYPES = ['Lecture', 'Tutorial', 'Practical'];

    /** Practical-only labs (venues.allowed_session_types = 'P'). */
    private const LAB_ROOMS = ['B005', 'B006', 'B009', 'B010', 'B011'];

    private const VENUE_HEADER = [
        'venue', 'room_label', 'day', 'start', 'end', 'duration_hours',
        'course', 'session_type', 'lecturer', 'cohorts', 'source',
    ];

    private const LECTURER_HEADER = [
        'staff_id', 'lecturer', 'day', 'start', 'end', 'duration_hours',
        'course', 'session_type', 'venue', 'cohorts', 'source',
    ];

    private const PROGRAMME_HEADER = [
        'cohort', 'day', 'start', 'end', 'duration_hours', 'course',
        'session_type', 'lecturer', 'venue', 'source',
    ];

    /** @var list<string> */
    private array $log = [];

    // ─────────────────────────────────────────────────────────────────────
    // Entry points
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Additive path (DatabaseSeeder chain, fresh test DBs — design D1):
     * preflight → import → verify. No delete phase here.
     */
    public function run(): void
    {
        $plan = $this->preflight();
        $this->importAll($plan);
        $this->verify();

        foreach ($this->log as $line) {
            $this->command->line($line);
        }
    }

    /** @return list<string> */
    public function logLines(): array
    {
        return $this->log;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 0 — preflight (every mode, before any write; spec R2)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Parse + validate the datasets and resolve every reference. Throws
     * (zero writes performed) on ANY failure; warnings never abort (R2.6).
     *
     * @return array{
     *     semester_id: int,
     *     module_unions: array<string, string>,
     *     sessions: list<array{
     *         module_code: string, lecturer_user_id: int, day_of_week: int,
     *         start_time: string, end_time: string, venue_id: int,
     *         session_type: string, cohort_ids: list<int>,
     *     }>,
     * }
     */
    public function preflight(): array
    {
        $venues = $this->readCsv('venues-schedules-202505.csv', self::VENUE_HEADER);
        $lecturers = $this->readCsv('lecturers-schedules-202505.csv', self::LECTURER_HEADER);
        $programmes = $this->readCsv('programmes-schedules-202505.csv', self::PROGRAMME_HEADER);
        $failures = [];
        $warnings = [];

        // R2.1 — counts, hours, distinct codes, per-row shape.
        if (count($venues) !== self::EXPECTED_VENUE_ROWS) {
            $failures[] = sprintf('venue CSV rows %d, expected %d', count($venues), self::EXPECTED_VENUE_ROWS);
        }
        if (count($lecturers) !== self::EXPECTED_LECTURER_ROWS) {
            $failures[] = sprintf('lecturer CSV rows %d, expected %d', count($lecturers), self::EXPECTED_LECTURER_ROWS);
        }
        if (count($programmes) !== self::EXPECTED_PROGRAMME_ROWS) {
            $failures[] = sprintf('programme CSV rows %d, expected %d', count($programmes), self::EXPECTED_PROGRAMME_ROWS);
        }

        $venueHours = array_sum(array_map(
            fn (array $r): float => (float) $r['duration_hours'],
            $venues,
        ));
        if (abs($venueHours - self::EXPECTED_VENUE_HOURS) > 0.001) {
            $failures[] = sprintf('venue hours total %.1f, expected %.1f', $venueHours, self::EXPECTED_VENUE_HOURS);
        }

        foreach (['venue' => $venues, 'lecturer' => $lecturers, 'programme' => $programmes] as $label => $rows) {
            foreach ($rows as $i => $row) {
                $this->assertRowShape($label, $i + 2, $row, $failures);
            }
        }

        $courseCodes = array_values(array_unique(array_map(
            fn (array $r): string => $r['course'],
            $venues,
        )));
        if (count($courseCodes) !== self::EXPECTED_COURSE_CODES) {
            $failures[] = sprintf('distinct course codes %d, expected %d', count($courseCodes), self::EXPECTED_COURSE_CODES);
        }

        // R2.2 — venue codes resolve to venues.room_code.
        $venueIds = DB::table('venues')->pluck('id', 'room_code')->all();
        $venueCodes = array_values(array_unique(array_map(
            fn (array $r): string => $this->venueCode($r['venue']),
            $venues,
        )));
        $unresolvedVenues = array_values(array_diff($venueCodes, array_keys($venueIds)));
        if ($unresolvedVenues !== []) {
            $failures[] = 'venue codes not in DB: '.implode(', ', $unresolvedVenues);
        }

        // R2.3 — lecturer cross-match: every venue row's (day,start,end,course)
        // matches exactly one lecturer row, same venue, staff_id resolvable.
        $staffUserIds = DB::table('lecturers')
            ->join('users', 'users.id', '=', 'lecturers.user_id')
            ->pluck('users.id', 'lecturers.staff_id')
            ->all();
        $lecturerByKey = [];
        foreach ($lecturers as $row) {
            $lecturerByKey[$this->classKey($row)][] = $row;
        }
        $lecturerUserIds = [];
        foreach ($venues as $i => $row) {
            $key = $this->classKey($row);
            $matches = $lecturerByKey[$key] ?? [];
            if (count($matches) !== 1) {
                $failures[] = sprintf(
                    'venue row %d (%s %s–%s %s): %d lecturer-CSV matches, expected exactly 1',
                    $i + 2, $row['day'], $row['start'], $row['end'], $row['course'], count($matches),
                );

                continue;
            }
            $match = $matches[0];
            if ($this->venueCode($match['venue']) !== $this->venueCode($row['venue'])) {
                $failures[] = sprintf(
                    'venue row %d (%s %s–%s %s): lecturer-CSV venue %s != venue CSV %s',
                    $i + 2, $row['day'], $row['start'], $row['end'], $row['course'],
                    $this->venueCode($match['venue']), $this->venueCode($row['venue']),
                );

                continue;
            }
            $staffId = trim($match['staff_id']);
            if (! isset($staffUserIds[$staffId])) {
                $failures[] = sprintf('lecturer staff_id %s not in DB (venue row %d)', $staffId, $i + 2);

                continue;
            }
            $lecturerUserIds[$i] = (int) $staffUserIds[$staffId];
        }

        // R2.4 — cohort label resolution (DatabaseSeeder::cohortCode()
        // derivation, character-for-character).
        $cohortIds = $this->cohortIdMap();
        $cohortIdsByRow = [];
        $usedLabels = [];
        foreach ($venues as $i => $row) {
            $ids = [];
            foreach (explode('/', $row['cohorts']) as $label) {
                $label = trim($label);
                $usedLabels[$label] = true;
                if (! isset($cohortIds[$label])) {
                    $failures[] = sprintf('cohort label not resolvable: %s (venue row %d)', $label, $i + 2);

                    continue;
                }
                $ids[] = (int) $cohortIds[$label];
            }
            $cohortIdsByRow[$i] = array_values(array_unique($ids));
        }
        if (count($usedLabels) !== self::EXPECTED_COHORT_LABELS) {
            $failures[] = sprintf(
                'distinct cohort labels %d, expected %d', count($usedLabels), self::EXPECTED_COHORT_LABELS,
            );
        }

        // R2.5 — 3-way consistency: venue-CSV cohort lists == programme rows,
        // element for element; cohort-instance totals 155 from every view.
        $fromVenues = [];
        foreach ($venues as $row) {
            foreach (explode('/', $row['cohorts']) as $cohort) {
                $fromVenues[] = implode('|', [
                    trim($cohort), $row['day'], $row['start'], $row['end'],
                    $row['course'], $this->venueCode($row['venue']),
                ]);
            }
        }
        $fromProgrammes = [];
        foreach ($programmes as $row) {
            $fromProgrammes[] = implode('|', [
                $row['cohort'], $row['day'], $row['start'], $row['end'],
                $row['course'], $this->venueCode($row['venue']),
            ]);
        }
        if (count($fromVenues) !== self::EXPECTED_COHORT_LINKS) {
            $failures[] = sprintf(
                'cohort instances from venue CSV %d, expected %d',
                count($fromVenues), self::EXPECTED_COHORT_LINKS,
            );
        }
        if (count($fromProgrammes) !== self::EXPECTED_COHORT_LINKS) {
            $failures[] = sprintf(
                'cohort instances from programme CSV %d, expected %d',
                count($fromProgrammes), self::EXPECTED_COHORT_LINKS,
            );
        }
        sort($fromVenues);
        sort($fromProgrammes);
        if ($fromVenues !== $fromProgrammes) {
            $onlyVenues = array_values(array_diff($fromVenues, $fromProgrammes));
            $onlyProgrammes = array_values(array_diff($fromProgrammes, $fromVenues));
            $failures[] = sprintf(
                '3-way dataset mismatch: %d tuples only in venue CSV, %d only in programme CSV (first: %s / %s)',
                count($onlyVenues), count($onlyProgrammes),
                $onlyVenues[0] ?? '—', $onlyProgrammes[0] ?? '—',
            );
        }

        // R2.6 — warnings (never abort): lab L/T + missing titles.
        // Warned set = 11 rows (DATASET-NOTES §3): the 9 non-Networking L/T
        // blocks in labs + AMIT2034×2 in B010 (room-level exception). The 6
        // Networking/IoT L/T rows in B006 are fully allowed — not warned.
        $networkingIoT = ['AMIT2033', 'AMIT2034', 'BMIT2154', 'BMIT3084', 'BMIT2123'];
        $labLtRows = [];
        foreach ($venues as $row) {
            $code = $this->venueCode($row['venue']);
            $isLt = in_array($row['session_type'], ['Lecture', 'Tutorial'], true);
            $subjectAllowedInB006 = $code === 'B006' && in_array($row['course'], $networkingIoT, true);
            if (in_array($code, self::LAB_ROOMS, true) && $isLt && ! $subjectAllowedInB006) {
                $labLtRows[] = sprintf(
                    '%s %s %s %s %s–%s %s',
                    $row['course'], $row['session_type'], $code,
                    $row['day'], $row['start'], $row['end'], $row['lecturer'],
                );
            }
        }
        if (count($labLtRows) !== self::EXPECTED_LAB_WARN_ROWS) {
            $warnings[] = sprintf(
                'lab L/T kept-as-printed count %d differs from the documented %d (DATASET-NOTES §3) — dataset drift?',
                count($labLtRows), self::EXPECTED_LAB_WARN_ROWS,
            );
        }
        foreach ($labLtRows as $line) {
            $warnings[] = 'L/T in Practical-only lab (kept as printed): '.$line;
        }

        $titles = require dirname(__DIR__, 2).'/dataset/import/course-titles.php';
        $missingTitles = array_values(array_diff($courseCodes, array_keys($titles)));
        foreach ($missingTitles as $code) {
            $warnings[] = sprintf('no real title for %s — module_name falls back to the code', $code);
        }

        if ($failures !== []) {
            throw new RuntimeException(
                "Dataset preflight FAILED — no writes performed.\n  - ".implode("\n  - ", $failures),
            );
        }

        // Semester must exist (abort before any write otherwise).
        $semesterId = DB::table('semesters')->where('semester_code', self::SEMESTER_CODE)->value('id');
        if ($semesterId === null) {
            throw new RuntimeException(sprintf('semester %s not found in DB', self::SEMESTER_CODE));
        }

        // Build the import plan.
        $moduleUnions = [];
        foreach ($venues as $row) {
            $letter = self::TYPE_MAP[$row['session_type']];
            $moduleUnions[$row['course']][$letter] = true;
        }
        $moduleUnions = array_map(
            fn (array $letters): string => implode(',', array_filter(
                ['L', 'T', 'P'],
                fn (string $t): bool => isset($letters[$t]),
            )),
            $moduleUnions,
        );

        $sessions = [];
        foreach ($venues as $i => $row) {
            $sessions[] = [
                'module_code' => $row['course'],
                'lecturer_user_id' => $lecturerUserIds[$i],
                'day_of_week' => self::DAY_MAP[$row['day']],
                'start_time' => $row['start'].':00',
                'end_time' => $row['end'].':00',
                'venue_id' => (int) $venueIds[$this->venueCode($row['venue'])],
                'session_type' => self::TYPE_MAP[$row['session_type']],
                'cohort_ids' => $cohortIdsByRow[$i],
            ];
        }

        $this->log[] = sprintf(
            'Phase 0 (preflight): datasets OK — %d/%d/%d rows, %.1f h, %d codes, %d cohort-instances.',
            count($venues), count($lecturers), count($programmes), $venueHours,
            count($courseCodes), count($fromVenues),
        );
        foreach ($warnings as $line) {
            $this->log[] = 'WARNING: '.$line;
        }

        return [
            'semester_id' => (int) $semesterId,
            'module_unions' => $moduleUnions,
            'sessions' => $sessions,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 1 — delete (replace mode only; command-driven; spec R4)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Replace path, phase 1: clear occupancy links, drop the demo overlays
     * and the hand-made sessions, in FK-safe order inside one transaction.
     * Assertions are computed from LIVE counts — never hardcoded baselines —
     * so this is safe on any pre-import state (spec R4.2).
     */
    public function deleteExisting(): void
    {
        $semesterId = $this->semesterId();

        DB::transaction(function () use ($semesterId): void {
            $sessionIds = DB::table('class_sessions')
                ->where('semester_id', $semesterId)
                ->pluck('id')
                ->all();

            // 1. Occupancy links first — time_slots.class_session_id has NO cascade.
            $linkedSlots = DB::table('time_slots')
                ->where('semester_id', $semesterId)
                ->whereIn('class_session_id', $sessionIds)
                ->count();
            $cleared = DB::table('time_slots')
                ->where('semester_id', $semesterId)
                ->whereIn('class_session_id', $sessionIds)
                ->update([
                    'class_session_id' => null,
                    'status' => 'available',
                    'version' => 1,
                    'updated_at' => now(),
                ]);
            $this->assertSame($cleared, $linkedSlots, 'time_slots occupancy cleared');

            // 2. Demo overlays (explicit deletes, not cascade reliance).
            $this->deleteCounted('class_exceptions', function ($q) use ($sessionIds): void {
                $q->whereIn('class_session_id', $sessionIds);
            }, 'class_exceptions');
            $this->deleteCounted('replacement_requests', function ($q) use ($sessionIds): void {
                $q->whereIn('class_session_id', $sessionIds);
            }, 'replacement_requests');

            // 3. The sessions themselves (session_cohorts rows cascade).
            $this->deleteCounted('class_sessions', function ($q) use ($semesterId): void {
                $q->where('semester_id', $semesterId);
            }, 'class_sessions');

            $orphanLinks = DB::table('session_cohorts')
                ->whereIn('class_session_id', $sessionIds)
                ->count();
            $this->assertSame($orphanLinks, 0, 'session_cohorts orphans after session delete');
            $danglingSlots = DB::table('time_slots')
                ->where('semester_id', $semesterId)
                ->whereNotNull('class_session_id')
                ->whereIn('class_session_id', $sessionIds)
                ->count();
            $this->assertSame($danglingSlots, 0, 'time_slots dangling links after clear');
        });

        $this->log[] = 'Phase 1 (delete): hand-made sessions, demo overlays and occupancy links cleared.';
    }

    /**
     * Delete with a live-count assertion (R4.2): affected MUST equal the
     * number of rows matching the same filter just before the delete.
     *
     * @param  Closure(Builder): void  $scope
     */
    private function deleteCounted(string $table, Closure $scope, string $label): void
    {
        $probe = DB::table($table);
        $scope($probe);
        $before = $probe->count();

        $query = DB::table($table);
        $scope($query);
        $deleted = $query->delete();

        $this->assertSame($deleted, $before, "{$label} deleted (live count)");
        $this->log[] = sprintf('%s: deleted %d rows.', $label, $deleted);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phases 2 + 3 — additive import (ONE transaction; spec R5 + R6)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  array{
     *     semester_id: int,
     *     module_unions: array<string, string>,
     *     sessions: list<array{
     *         module_code: string, lecturer_user_id: int, day_of_week: int,
     *         start_time: string, end_time: string, venue_id: int,
     *         session_type: string, cohort_ids: list<int>,
     *     }>,
     * }  $plan
     */
    public function importAll(array $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $this->importReferences($plan);
            $this->importSessions($plan);
        });
    }

    /**
     * Phase 2 — reference updates (spec R5). Caller wraps in a transaction.
     *
     * @param  array{semester_id: int, module_unions: array<string, string>}  $plan
     */
    private function importReferences(array $plan): void
    {
        $semesterId = $plan['semester_id'];

        // R5.1 — semester dates (code/label/week_count untouched — user (b)).
        DB::table('semesters')
            ->where('id', $semesterId)
            ->update([
                'start_date' => self::SEMESTER_START,
                'end_date' => self::SEMESTER_END,
                'updated_at' => now(),
            ]);
        $this->log[] = sprintf('Phase 2: semester dates → %s … %s.', self::SEMESTER_START, self::SEMESTER_END);

        // R5.2 — holidays: replace ALL semester rows with the canonical 3.
        $existingHolidays = DB::table('holidays')->where('semester_id', $semesterId)->count();
        DB::table('holidays')->where('semester_id', $semesterId)->delete();
        foreach (self::HOLIDAY_ROWS as [$week, $day, $label]) {
            DB::table('holidays')->insert([
                'semester_id' => $semesterId,
                'week_number' => $week,
                'day_of_week' => $day,
                'label' => $label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->log[] = sprintf(
            'Phase 2: holidays %d → %d rows (canonical).',
            $existingHolidays, count(self::HOLIDAY_ROWS),
        );

        // R5.3 — modules: upsert all 42 codes; allowed_session_types =
        // observed-union (D4); missing titles fall back to the code (D9).
        $existingCodes = DB::table('modules')->pluck('id', 'module_code')->all();
        foreach ($plan['module_unions'] as $code => $allowed) {
            $row = [
                'module_name' => $this->moduleTitle($code),
                'allowed_session_types' => $allowed,
                'updated_at' => now(),
            ];
            if (isset($existingCodes[$code])) {
                DB::table('modules')->where('id', $existingCodes[$code])->update($row);
            } else {
                $row['module_code'] = $code;
                $row['created_at'] = now();
                DB::table('modules')->insert($row);
            }
        }
        $this->log[] = sprintf('Phase 2: modules upserted (%d codes).', count($plan['module_unions']));
    }

    /**
     * Phase 3 — sessions, cohort links, prune, occupancy (spec R6).
     * Caller wraps in a transaction.
     *
     * @param  array{
     *     semester_id: int,
     *     sessions: list<array{
     *         module_code: string, lecturer_user_id: int, day_of_week: int,
     *         start_time: string, end_time: string, venue_id: int,
     *         session_type: string, cohort_ids: list<int>,
     *     }>,
     * }  $plan
     */
    private function importSessions(array $plan): void
    {
        $semesterId = $plan['semester_id'];

        $moduleIds = DB::table('modules')->pluck('id', 'module_code')->all();
        $inserted = 0;
        $links = 0;
        foreach ($plan['sessions'] as $session) {
            if (! isset($moduleIds[$session['module_code']])) {
                throw new RuntimeException(sprintf('module %s missing at import time', $session['module_code']));
            }

            $sessionId = DB::table('class_sessions')->insertGetId([
                'semester_id' => $semesterId,
                'module_id' => $moduleIds[$session['module_code']],
                'lecturer_id' => $session['lecturer_user_id'],
                'day_of_week' => $session['day_of_week'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
                'venue_id' => $session['venue_id'],
                'session_type' => $session['session_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inserted++;

            foreach ($session['cohort_ids'] as $cohortId) {
                DB::table('session_cohorts')->insert([
                    'class_session_id' => $sessionId,
                    'cohort_id' => $cohortId,
                ]);
                $links++;
            }

            $this->occupyTimeSlots($semesterId, $sessionId, $session);
        }

        $this->assertSame(
            $inserted, self::EXPECTED_SESSIONS,
            sprintf('class_sessions inserted %d, expected %d', $inserted, self::EXPECTED_SESSIONS),
        );
        $this->assertSame(
            $links, self::EXPECTED_COHORT_LINKS,
            sprintf('session_cohorts inserted %d, expected %d', $links, self::EXPECTED_COHORT_LINKS),
        );

        // R6.3 — prune modules with zero session references (live count).
        $unreferenced = DB::table('modules')
            ->whereNotIn('id', function ($query): void {
                $query->select('module_id')->from('class_sessions');
            })
            ->count();
        $pruned = DB::table('modules')
            ->whereNotIn('id', function ($query): void {
                $query->select('module_id')->from('class_sessions');
            })
            ->delete();
        $this->assertSame($pruned, $unreferenced, 'modules pruned (live count)');

        $moduleTotal = DB::table('modules')->count();
        $this->assertSame($moduleTotal, self::EXPECTED_MODULES, 'modules after prune');

        $this->log[] = sprintf(
            'Phase 3: %d sessions, %d cohort links; pruned %d unreferenced modules → %d remain.',
            $inserted, $links, $pruned, $moduleTotal,
        );
    }

    /**
     * Occupy the session's weekly slots with an exact-count assertion
     * (house pattern of the replaced ClassSessionsSeeder, spec R6.4).
     *
     * @param  array{module_code: string, day_of_week: int, start_time: string, end_time: string, venue_id: int}  $session
     */
    private function occupyTimeSlots(int $semesterId, int $sessionId, array $session): void
    {
        $slotCount = $this->slotCount($session['start_time'], $session['end_time']);
        $holidayWeeks = $this->holidayWeeksForDay($session['day_of_week']);
        $expected = $slotCount * (14 - count($holidayWeeks));

        $query = DB::table('time_slots')
            ->where('semester_id', $semesterId)
            ->whereBetween('week_number', [1, 14])
            ->where('day_of_week', $session['day_of_week'])
            ->where('start_time', '>=', $session['start_time'])
            ->where('start_time', '<', $session['end_time'])
            ->where('venue_id', $session['venue_id'])
            ->where('status', 'available');
        if ($holidayWeeks !== []) {
            $query->whereNotIn('week_number', $holidayWeeks);
        }
        $affected = $query->update([
            'class_session_id' => $sessionId,
            'status' => 'occupied',
            'updated_at' => now(),
        ]);

        if ($affected !== $expected) {
            throw new RuntimeException(sprintf(
                'Occupancy mismatch for %s @ day %d %s–%s venue %d — affected %d of expected %d. '
                .'No silent fixes; inspect time_slots state.',
                $session['module_code'], $session['day_of_week'],
                $session['start_time'], $session['end_time'], $session['venue_id'],
                $affected, $expected,
            ));
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 4 — verification pass (read-only, every mode; spec R7)
    // ─────────────────────────────────────────────────────────────────────

    public function verify(): void
    {
        $semesterId = $this->semesterId();
        $failures = [];

        // R7.1 — occupied slots.
        $occupied = DB::table('time_slots')->where('semester_id', $semesterId)
            ->where('status', 'occupied')->count();
        if ($occupied !== self::EXPECTED_OCCUPIED_SLOTS) {
            $failures[] = sprintf('occupied time_slots %d, expected %d', $occupied, self::EXPECTED_OCCUPIED_SLOTS);
        }

        // Orphans: occupied/pending slots pointing at missing sessions.
        $orphans = DB::table('time_slots')
            ->leftJoin('class_sessions', 'class_sessions.id', '=', 'time_slots.class_session_id')
            ->where('time_slots.semester_id', $semesterId)
            ->whereNotNull('time_slots.class_session_id')
            ->whereNull('class_sessions.id')
            ->count();
        if ($orphans !== 0) {
            $failures[] = sprintf('time_slots orphan class_session_id: %d', $orphans);
        }

        // Non-available slots without a session.
        $ghostBusy = DB::table('time_slots')
            ->where('semester_id', $semesterId)
            ->whereNull('class_session_id')
            ->where('status', '!=', 'available')
            ->count();
        if ($ghostBusy !== 0) {
            $failures[] = sprintf('non-available slots without a session: %d', $ghostBusy);
        }

        // Double-booked venue slots (the partial unique index backstops; the
        // explicit check gives the error message).
        $doubleBooked = DB::table('time_slots')
            ->select('venue_id', 'day_of_week', 'start_time', 'week_number', DB::raw('COUNT(*) as n'))
            ->where('semester_id', $semesterId)
            ->whereIn('status', ['pending', 'occupied'])
            ->groupBy('venue_id', 'day_of_week', 'start_time', 'week_number')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($doubleBooked !== 0) {
            $failures[] = sprintf('double-booked venue slots: %d', $doubleBooked);
        }

        $links = DB::table('session_cohorts')->count();
        if ($links !== self::EXPECTED_COHORT_LINKS) {
            $failures[] = sprintf('session_cohorts rows %d, expected %d', $links, self::EXPECTED_COHORT_LINKS);
        }

        $cohortCoverage = DB::table('session_cohorts')->distinct()->count('cohort_id');
        if ($cohortCoverage !== self::EXPECTED_COHORT_LABELS) {
            $failures[] = sprintf('distinct cohort coverage %d, expected %d', $cohortCoverage, self::EXPECTED_COHORT_LABELS);
        }

        $modules = DB::table('modules')->count();
        if ($modules !== self::EXPECTED_MODULES) {
            $failures[] = sprintf('modules %d, expected %d', $modules, self::EXPECTED_MODULES);
        }

        $holidays = DB::table('holidays')->where('semester_id', $semesterId)->get();
        if (count($holidays) !== self::EXPECTED_HOLIDAYS) {
            $failures[] = sprintf('holidays %d, expected %d', count($holidays), self::EXPECTED_HOLIDAYS);
        }
        foreach (self::HOLIDAY_ROWS as [$week, $day, $label]) {
            $row = $holidays->first(fn ($h): bool => (int) $h->week_number === $week && (int) $h->day_of_week === $day);
            if ($row === null || $row->label !== $label) {
                $failures[] = sprintf('missing/mismatched holiday W%d D%d (%s)', $week, $day, $label);

                continue;
            }
            $busy = DB::table('time_slots')
                ->where('semester_id', $semesterId)
                ->where('week_number', $week)
                ->where('day_of_week', $day)
                ->where('status', '!=', 'available')
                ->count();
            if ($busy !== 0) {
                $failures[] = sprintf('%d non-available slots on holiday W%d D%d', $busy, $week, $day);
            }
        }

        $semester = DB::table('semesters')->where('id', $semesterId)->first();
        if ($semester === null
            || $semester->start_date !== self::SEMESTER_START
            || $semester->end_date !== self::SEMESTER_END) {
            $failures[] = 'semester dates not canonical';
        }

        if ($failures !== []) {
            throw new RuntimeException(
                "Post-import verification FAILED.\n  - ".implode("\n  - ", $failures),
            );
        }

        $this->log[] = sprintf(
            'Phase 4 (verify): %d occupied slots, 0 orphans, 0 double-bookings, %d links, %d cohorts, %d modules, %d holidays.',
            $occupied, $links, $cohortCoverage, $modules, count($holidays),
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function semesterId(): int
    {
        $id = DB::table('semesters')->where('semester_code', self::SEMESTER_CODE)->value('id');
        if ($id === null) {
            throw new RuntimeException(sprintf('semester %s not found in DB', self::SEMESTER_CODE));
        }

        return (int) $id;
    }

    private function slotCount(string $startTime, string $endTime): int
    {
        $start = $this->toMinutes(substr($startTime, 0, 5));
        $end = $this->toMinutes(substr($endTime, 0, 5));
        if ($start === null || $end === null || ($end - $start) % 30 !== 0) {
            throw new RuntimeException("Non-aligned session span {$startTime}–{$endTime}");
        }

        return (int) (($end - $start) / 30);
    }

    private function toMinutes(string $time): ?int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m) !== 1) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }

        return $h * 60 + $min;
    }

    /**
     * @return list<int>
     */
    private function holidayWeeksForDay(int $day): array
    {
        $weeks = [];
        foreach (self::HOLIDAY_PAIRS as [$week, $pairDay]) {
            if ($pairDay === $day) {
                $weeks[] = $week;
            }
        }

        return $weeks;
    }

    private function venueCode(string $raw): string
    {
        return trim(explode(' ', trim($raw))[0]);
    }

    private function moduleTitle(string $code): string
    {
        static $titles = null;
        if ($titles === null) {
            $titles = require dirname(__DIR__, 2).'/dataset/import/course-titles.php';
        }

        return $titles[$code] ?? $code; // D9: never invent a title.
    }

    /**
     * Cohort label → id, derived exactly like DatabaseSeeder::cohortCode():
     * programme_code + current_year + '(S' + semester + ')G' + tutorial_group.
     *
     * @return array<string, int>
     */
    private function cohortIdMap(): array
    {
        $map = [];
        $rows = DB::table('cohorts')
            ->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
            ->get([
                'programmes.programme_code', 'cohorts.id',
                'cohorts.current_year', 'cohorts.semester', 'cohorts.tutorial_group',
            ]);
        foreach ($rows as $c) {
            $map[sprintf('%s%d(S%d)G%d', $c->programme_code, $c->current_year, $c->semester, $c->tutorial_group)] = (int) $c->id;
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function classKey(array $row): string
    {
        return implode('|', [$row['day'], $row['start'], $row['end'], $row['course']]);
    }

    /**
     * @param  list<string>  $expectedHeader
     * @return list<array<string, string>>
     */
    private function readCsv(string $filename, array $expectedHeader): array
    {
        $path = dirname(__DIR__, 2).'/dataset/import/'.$filename;
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Dataset file unreadable: {$path}");
        }

        try {
            $header = fgetcsv($handle, escape: '\\');
            if ($header === false || (count($header) === 1 && trim((string) $header[0]) === '')) {
                throw new RuntimeException("{$filename}: empty file");
            }
            $header = array_map(
                fn ($cell): string => trim((string) $cell, " \t\n\r\0\x0B\xEF\xBB\xBF"),
                $header,
            );
            if ($header !== $expectedHeader) {
                throw new RuntimeException(sprintf(
                    '%s: header drift — got [%s], expected [%s]',
                    $filename, implode(',', $header), implode(',', $expectedHeader),
                ));
            }

            $rows = [];
            while (($row = fgetcsv($handle, escape: '\\')) !== false) {
                if (count($row) === 1 && trim((string) $row[0]) === '') {
                    continue; // blank line
                }
                if (count($row) !== count($expectedHeader)) {
                    throw new RuntimeException(sprintf(
                        '%s line %d: %d cells, header has %d',
                        $filename, count($rows) + 2, count($row), count($expectedHeader),
                    ));
                }
                $rows[] = array_combine($expectedHeader, array_map(
                    fn ($cell): string => trim((string) $cell),
                    $row,
                ));
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Row-level field validity (R2.1): day, session type, 30-min alignment,
     * grid bounds, duration consistency.
     *
     * @param  array<string, string>  $row
     * @param  list<string>  $failures
     */
    private function assertRowShape(string $dataset, int $lineNo, array $row, array &$failures): void
    {
        $where = sprintf('%s CSV line %d', $dataset, $lineNo);

        if (! isset(self::DAY_MAP[$row['day']])) {
            $failures[] = "{$where}: unknown day {$row['day']}";

            return;
        }
        if (! in_array($row['session_type'], self::SESSION_TYPES, true)) {
            $failures[] = "{$where}: session_type '{$row['session_type']}' not in {Lecture, Tutorial, Practical}";
        }

        $startMin = $this->toMinutes($row['start']);
        $endMin = $this->toMinutes($row['end']);
        if ($startMin === null || $endMin === null) {
            $failures[] = "{$where}: bad time format {$row['start']}–{$row['end']}";

            return;
        }
        if ($startMin % 30 !== 0 || $endMin % 30 !== 0) {
            $failures[] = "{$where}: not 30-min aligned {$row['start']}–{$row['end']}";
        }
        if ($startMin < 8 * 60 || $endMin > 18 * 60 || $endMin <= $startMin) {
            $failures[] = "{$where}: outside grid bounds 08:00–18:00 ({$row['start']}–{$row['end']})";
        }

        $duration = (float) $row['duration_hours'];
        if (abs($duration - ($endMin - $startMin) / 60) > 0.001) {
            $failures[] = "{$where}: duration_hours {$duration} != span";
        }
    }

    private function assertSame(mixed $actual, mixed $expected, string $label): void
    {
        if ($actual !== $expected) {
            throw new RuntimeException(sprintf(
                '%s: got %s, expected %s',
                $label, var_export($actual, true), var_export($expected, true),
            ));
        }
    }
}
