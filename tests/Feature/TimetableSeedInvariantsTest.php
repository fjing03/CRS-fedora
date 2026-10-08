<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * SDD change `import-real-schedule-records` — the seed invariants, re-pinned to
 * the REAL semester-202505 datasets (T-1..T-5 kept, T-6/T-7 added).
 *
 * Full-seed precondition via the suite-proven explicit `$this->seed()`
 * pattern, run inside RefreshDatabase's per-test transaction (the seed rolls back with
 * it, so tests share no mutable state). The `$seed = true` property path is deliberately
 * NOT used: RefreshDatabaseState::$migrated is process-global, so a RefreshDatabase
 * class that ran migrate:fresh earlier in the same process — without seeding — would
 * leave this class silently un-seeded.
 *
 * Frozen numbers under test: 101 class_sessions / 155 session_cohorts rows /
 * 3963 occupied time_slots (4116 − 153 holiday-affected: W8 Mon 62 + W14 Thu 54
 * + W14 Fri 37) / all 14 cohorts covered / venue+lecturer overlap-free /
 * exactly ONE documented cohort-overlap pair (user decision (c)).
 */
final class TimetableSeedInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_SESSIONS = 101;

    private const EXPECTED_SESSION_COHORTS = 155;

    private const EXPECTED_OCCUPIED_SLOTS = 3963;

    private const EXPECTED_COHORTS = 14;

    /** Holiday grid pairs [week, day] — mirrors RealScheduleSeeder (W8 Mon, W14 Thu, W14 Fri). */
    private const HOLIDAY_PAIRS = [[8, 0], [14, 3], [14, 4]];

    private const TYPE_LETTER_MAP = [
        'Lecture' => 'L',
        'Tutorial' => 'T',
        'Practical' => 'P',
    ];

    /**
     * T-4 venue-type guard: the EXACT 17 L/T-in-Practical-only-lab rows kept
     * as printed (DATASET-NOTES §3, signature module|room|day|type|start–end,
     * sorted). 9 non-Networking violations + 8 Networking/IoT (AMIT2034×2 in
     * B010 = room-level exception; the other 6 sit in B006, the spec's
     * Networking/IoT room). MUST stay in sorted order — T-4 compares exactly.
     */
    private const LAB_LT_ALLOWANCE = [
        'AMCS1013|B006|1|T|13:30–14:30',
        'AMCS1043|B011|2|T|10:00–11:00',
        'AMIS1003|B010|4|T|15:30–16:30',
        'AMIT2033|B006|2|L|14:00–16:00',
        'AMIT2033|B006|2|T|16:00–17:00',
        'AMIT2034|B010|1|L|13:30–15:30',
        'AMIT2034|B010|1|T|15:30–16:30',
        'AMSE1003|B009|2|T|09:00–10:00',
        'BMCS3033|B011|4|T|09:30–10:30',
        'BMIS2003|B006|1|L|11:00–13:00',
        'BMIT1173|B009|3|T|12:00–13:00',
        'BMIT1723|B011|2|L|13:30–14:30',
        'BMIT2013|B009|1|L|14:00–16:00',
        'BMIT2154|B006|0|L|09:00–11:00',
        'BMIT2154|B006|0|T|11:00–12:00',
        'BMIT3084|B006|0|L|13:00–15:00',
        'BMIT3084|B006|4|T|10:00–11:00',
    ];

    /**
     * Seed the full demo database. Runs before every test; RefreshDatabase rolls the
     * seed back afterwards, so tests share no mutable state.
     */
    private function seedFreshDemoDatabase(): void
    {
        // nextval() is not transactional in PostgreSQL: an earlier test in this process
        // can leave the sequence advanced after its rollback. Position ids are no longer
        // pinned (parity is content-based), but the reset stays as cheap hygiene.
        DB::statement("SELECT setval(pg_get_serial_sequence('class_sessions', 'id'), 1, false)");

        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Cohort display name, matching the seeder's own keyBy format.
     * current_year/semester/tutorial_group arrive as integers — passing them to %d
     * un-cast risks "RSD23(S1)G1"-style garbage instead of "RSD2(S1)G3".
     *
     * @param  object{programme_code: string, current_year: int|string, semester: int|string, tutorial_group: int|string}  $cohort
     */
    private function cohortName(object $cohort): string
    {
        return sprintf('%s%d(S%d)G%d', $cohort->programme_code, (int) $cohort->current_year, (int) $cohort->semester, (int) $cohort->tutorial_group);
    }

    /**
     * Boundary-exclusive overlap predicate (T-2): back-to-back sessions
     * (a.end == b.start) are LEGAL and must not qualify.
     */
    private function overlaps(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        return strcmp($aStart, $bEnd) < 0 && strcmp($bStart, $aEnd) < 0;
    }

    /**
     * Canonical signature for a cohort-overlap row (T-2): day, the two modules
     * and the two time windows each in sorted order — so the whitelist matches
     * regardless of which session of the pair got the lower id.
     *
     * @param  object{module_1: string, start_1: string, end_1: string, module_2: string, start_2: string, end_2: string, day_of_week: int|string}  $o
     */
    private function overlapSignature(object $o): string
    {
        $modules = [(string) $o->module_1, (string) $o->module_2];
        sort($modules);
        // DB times carry seconds ('11:00:00') — normalize to HH:MM so the
        // whitelist reads exactly like DATASET-NOTES §2.
        $windows = [
            substr((string) $o->start_1, 0, 5).'–'.substr((string) $o->end_1, 0, 5),
            substr((string) $o->start_2, 0, 5).'–'.substr((string) $o->end_2, 0, 5),
        ];
        sort($windows);

        return implode('|', [(int) $o->day_of_week, implode('+', $modules), implode('+', $windows)]);
    }

    /**
     * Parity key for the T-5 tuple (day_of_week, start HH:MM, venue, module).
     */
    private function parityKey(int|string $day, string $start, string $venue, string $module): string
    {
        return sprintf('%d|%s|%s|%s', (int) $day, $start, $venue, $module);
    }

    /**
     * Split the doc's "HH:MM–HH:MM" cell (en dash) into normalized HH:MM bounds.
     *
     * @return array{0: string, 1: string}
     */
    private function splitTimeRange(string $range): array
    {
        $parts = array_map('trim', explode('–', $range));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new RuntimeException(sprintf('timetable.md: malformed start–end cell "%s" (must be "HH:MM–HH:MM").', $range));
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Justified — session ids are unique in the table, so the loop-pairing idents
     * never alias.
     */
    private function assertNoViolations(array $violations, string $heading): void
    {
        $this->assertSame([], $violations, $heading."\n".implode("\n", $violations));
    }

    public function test_t1_no_orphans_exact_occupancy(): void
    {
        $this->seedFreshDemoDatabase();

        $sessions = DB::table('class_sessions')->select('id', 'day_of_week', 'start_time', 'end_time')->get();
        $this->assertNotEmpty($sessions, 'T-1: no class_sessions rows — the seeder did not run.');

        $holidayWeeksByDay = [];
        foreach (self::HOLIDAY_PAIRS as [$week, $day]) {
            $holidayWeeksByDay[$day][] = $week;
        }

        $totalOccupied = 0;

        foreach ($sessions as $session) {
            // Holiday rule (spec R6.4): a Mon/Thu/Fri session occupies 13 weeks
            // (W8 Mon, W14 Thu, W14 Fri stay available); others occupy 14.
            $durationMinutes = (int) round((strtotime($session->end_time) - strtotime($session->start_time)) / 60);
            $holidayWeeks = $holidayWeeksByDay[(int) $session->day_of_week] ?? [];
            $expected = (int) ceil($durationMinutes / 30) * (14 - count($holidayWeeks));

            $occupied = (int) DB::table('time_slots')
                ->where('semester_id', 1)
                ->where('class_session_id', $session->id)
                ->where('status', 'occupied')
                ->count();

            $this->assertSame(
                $expected,
                $occupied,
                sprintf(
                    'T-1: session %d (%d min, %d slots/week) must own exactly %d occupied slots, found %d.',
                    $session->id,
                    $durationMinutes,
                    (int) ceil($durationMinutes / 30),
                    $expected,
                    $occupied,
                ),
            );

            $totalOccupied += $occupied;
        }

        $this->assertSame(
            self::EXPECTED_OCCUPIED_SLOTS,
            $totalOccupied,
            sprintf('T-1: global occupied count (semester 1) must be %d, found %d.', self::EXPECTED_OCCUPIED_SLOTS, $totalOccupied),
        );

        $orphans = (int) DB::table('time_slots')
            ->where('semester_id', 1)
            ->where('status', 'occupied')
            ->whereNull('class_session_id')
            ->count();

        $this->assertSame(
            0,
            $orphans,
            sprintf('T-1: found %d occupied time_slots with a null class_session_id — every occupied slot must be session-owned.', $orphans),
        );
    }

    public function test_t2_no_venue_lecturer_cohort_overlaps(): void
    {
        $this->seedFreshDemoDatabase();

        $sessions = DB::table('class_sessions')
            ->join('modules', 'modules.id', '=', 'class_sessions.module_id')
            ->join('venues', 'venues.id', '=', 'class_sessions.venue_id')
            ->select(
                'class_sessions.id',
                'class_sessions.day_of_week',
                'class_sessions.start_time',
                'class_sessions.end_time',
                'class_sessions.venue_id',
                'class_sessions.lecturer_id',
                'modules.module_code',
                'venues.room_code',
            )
            ->get();

        $this->assertSame(
            self::EXPECTED_SESSIONS,
            $sessions->count(),
            sprintf('T-2: expected %d seeded sessions before overlap scanning.', self::EXPECTED_SESSIONS),
        );

        $all = $sessions->values()->all();
        $venueOverlaps = [];
        $lecturerOverlaps = [];

        foreach ($all as $i => $a) {
            foreach ($all as $j => $b) {
                if ($j <= $i) {
                    continue;
                }

                if ((int) $a->day_of_week !== (int) $b->day_of_week
                    || ! $this->overlaps($a->start_time, $a->end_time, $b->start_time, $b->end_time)) {
                    continue;
                }

                if ((int) $a->venue_id === (int) $b->venue_id) {
                    $venueOverlaps[] = sprintf(
                        'venue %s: session %d (%s %s–%s) overlaps session %d (%s %s–%s), day %d',
                        $a->room_code,
                        $a->id,
                        $a->module_code,
                        $a->start_time,
                        $a->end_time,
                        $b->id,
                        $b->module_code,
                        $b->start_time,
                        $b->end_time,
                        $a->day_of_week,
                    );
                }

                if ((int) $a->lecturer_id === (int) $b->lecturer_id) {
                    $lecturerOverlaps[] = sprintf(
                        'lecturer id %d: session %d (%s %s–%s) overlaps session %d (%s %s–%s), day %d',
                        $a->lecturer_id,
                        $a->id,
                        $a->module_code,
                        $a->start_time,
                        $a->end_time,
                        $b->id,
                        $b->module_code,
                        $b->start_time,
                        $b->end_time,
                        $a->day_of_week,
                    );
                }
            }
        }

        $cohortOverlaps = DB::table('session_cohorts as sc1')
            ->join('session_cohorts as sc2', 'sc2.cohort_id', '=', 'sc1.cohort_id')
            ->join('class_sessions as s1', 's1.id', '=', 'sc1.class_session_id')
            ->join('class_sessions as s2', 's2.id', '=', 'sc2.class_session_id')
            ->join('modules as m1', 'm1.id', '=', 's1.module_id')
            ->join('modules as m2', 'm2.id', '=', 's2.module_id')
            ->join('cohorts as c', 'c.id', '=', 'sc1.cohort_id')
            ->join('programmes as p', 'p.id', '=', 'c.programme_id')
            ->whereColumn('sc1.class_session_id', '<', 'sc2.class_session_id')
            ->whereColumn('s1.day_of_week', 's2.day_of_week')
            ->whereColumn('s1.start_time', '<', 's2.end_time')
            ->whereColumn('s2.start_time', '<', 's1.end_time')
            ->select(
                'sc1.cohort_id',
                'sc1.class_session_id',
                'sc2.class_session_id',
                's1.day_of_week',
                's1.start_time as start_1',
                's1.end_time as end_1',
                's2.start_time as start_2',
                's2.end_time as end_2',
                'm1.module_code as module_1',
                'm2.module_code as module_2',
                'p.programme_code',
                'c.current_year',
                'c.semester',
                'c.tutorial_group',
            )
            ->get();

        $cohortOverlapRows = $cohortOverlaps->map(fn ($overlap): array => [
            'signature' => $this->overlapSignature($overlap),
            'message' => sprintf(
                'cohort %s (id %d): session %d (%s %s–%s) overlaps session %d (%s %s–%s), day %d',
                $this->cohortName($overlap),
                $overlap->cohort_id,
                $overlap->class_session_id,
                $overlap->module_1,
                $overlap->start_1,
                $overlap->end_1,
                $overlap->sc2_class_session_id ?? $overlap->class_session_id,
                $overlap->module_2,
                $overlap->start_2,
                $overlap->end_2,
                $overlap->day_of_week,
            ),
        ])->all();

        $this->assertSame(
            [],
            $venueOverlaps,
            "T-2: sessions sharing a venue on overlapping times are forbidden (back-to-back is legal):\n".implode("\n", $venueOverlaps),
        );

        $this->assertSame(
            [],
            $lecturerOverlaps,
            "T-2: sessions sharing a lecturer on overlapping times are forbidden (back-to-back is legal):\n".implode("\n", $lecturerOverlaps),
        );

        // User decision (c): the ONE source cohort double-booking is KEPT as
        // printed — DFT2(S1)G1 Wednesday, AMIT2014 10:00–12:00 (B009) vs
        // AMIT2034 11:00–13:00 (B005), overlap 11:00–12:00 (DATASET-NOTES §2).
        // Exactly one cohort-overlap row is allowed and it must be THIS tuple;
        // the matched-count guard prevents silent whitelist rot (an empty
        // violation set with a dead whitelist must not pass).
        $allowedOverlapSignatures = [
            '2|AMIT2014+AMIT2034|10:00–12:00+11:00–13:00',
        ];

        $unexpectedCohortOverlaps = [];
        $matchedWhitelist = 0;
        foreach ($cohortOverlapRows as $row) {
            if (in_array($row['signature'], $allowedOverlapSignatures, true)) {
                $matchedWhitelist++;

                continue;
            }
            $unexpectedCohortOverlaps[] = $row['message'];
        }

        $this->assertSame(
            [],
            $unexpectedCohortOverlaps,
            "T-2: a cohort must not appear in two overlapping sessions (back-to-back is legal; the single\n".
            "documented DFT2 Wednesday clash is whitelisted — anything else is a regression):\n".implode("\n", $unexpectedCohortOverlaps),
        );

        $this->assertSame(
            count($allowedOverlapSignatures),
            $matchedWhitelist,
            sprintf(
                'T-2: whitelist rot guard — expected exactly %d whitelisted cohort-overlap row(s), found %d.',
                count($allowedOverlapSignatures),
                $matchedWhitelist,
            ),
        );
    }

    public function test_t3_all_14_cohorts_covered(): void
    {
        $this->seedFreshDemoDatabase();

        $this->assertSame(
            self::EXPECTED_COHORTS,
            (int) DB::table('cohorts')->count(),
            sprintf('T-3: the cohorts reference table must contain exactly %d rows.', self::EXPECTED_COHORTS),
        );

        $covered = DB::table('session_cohorts')->distinct()->pluck('cohort_id')->all();

        $missing = DB::table('cohorts')
            ->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
            ->whereNotIn('cohorts.id', $covered !== [] ? $covered : [0])
            ->get()
            ->map(fn ($cohort): string => sprintf('%s (id %d)', $this->cohortName($cohort), $cohort->id))
            ->all();

        $this->assertSame(
            [],
            $missing,
            sprintf("T-3: cohorts with zero session_cohorts rows:\n%s\n(all %d cohorts must have >= 1 session)", implode("\n", $missing), self::EXPECTED_COHORTS),
        );

        $this->assertCount(
            self::EXPECTED_COHORTS,
            $covered,
            sprintf('T-3: exactly %d distinct cohort_ids must appear in session_cohorts, found %d.', self::EXPECTED_COHORTS, count($covered)),
        );
    }

    public function test_t4_mpu_cohort_sets_match_dataset(): void
    {
        $this->seedFreshDemoDatabase();

        // Expected MPU-* cohort sets and session types, straight from the
        // dataset's own truth: the programme CSV (one row per cohort instance;
        // MPU-* are the dataset's general-studies codes).
        $csvPath = base_path('dataset/import/programmes-schedules-202505.csv');
        $this->assertFileExists($csvPath, 'T-4: the programme dataset CSV must exist.');
        $lines = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotFalse($lines, 'T-4: programme dataset CSV unreadable.');

        $expectedCohorts = [];
        $expectedTypes = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue; // header
            }
            $cells = array_map('trim', (array) str_getcsv($line));
            $course = $cells[5] ?? '';
            if (! str_starts_with($course, 'MPU-')) {
                continue;
            }
            $expectedCohorts[$course][$cells[0]] = true;
            $expectedTypes[$course][self::TYPE_LETTER_MAP[$cells[6]] ?? '?'] = true;
        }

        $this->assertGreaterThanOrEqual(
            5,
            count($expectedCohorts),
            sprintf('T-4: the dataset must print at least 5 MPU-* codes, found %d — CSV drift?', count($expectedCohorts)),
        );

        // DB side: cohort labels + session types per MPU code.
        $rows = DB::table('session_cohorts')
            ->join('class_sessions', 'class_sessions.id', '=', 'session_cohorts.class_session_id')
            ->join('cohorts', 'cohorts.id', '=', 'session_cohorts.cohort_id')
            ->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
            ->join('modules', 'modules.id', '=', 'class_sessions.module_id')
            ->where('modules.module_code', 'like', 'MPU-%')
            ->select(
                'modules.module_code',
                'class_sessions.session_type',
                'programmes.programme_code',
                'cohorts.current_year',
                'cohorts.semester',
                'cohorts.tutorial_group',
            )
            ->get();

        $dbCohorts = [];
        $dbTypes = [];
        foreach ($rows as $row) {
            $dbCohorts[$row->module_code][$this->cohortName($row)] = true;
            $dbTypes[$row->module_code][$row->session_type] = true;
        }

        // Set equality per code, CSV ↔ DB, both directions.
        $mismatches = [];
        foreach ($expectedCohorts as $code => $labels) {
            $dbLabels = array_keys($dbCohorts[$code] ?? []);
            $csvLabels = array_keys($labels);
            sort($dbLabels);
            sort($csvLabels);
            if ($dbLabels !== $csvLabels) {
                $mismatches[] = sprintf('%s cohorts: DB [%s] vs CSV [%s]', $code, implode(', ', $dbLabels), implode(', ', $csvLabels));
            }

            $dbTypeList = array_keys($dbTypes[$code] ?? []);
            $csvTypeList = array_keys($expectedTypes[$code]);
            sort($dbTypeList);
            sort($csvTypeList);
            if ($dbTypeList !== $csvTypeList) {
                $mismatches[] = sprintf('%s session types: DB [%s] vs CSV [%s]', $code, implode(', ', $dbTypeList), implode(', ', $csvTypeList));
            }
        }
        $extraCodes = array_values(array_diff(array_keys($dbCohorts), array_keys($expectedCohorts)));
        if ($extraCodes !== []) {
            $mismatches[] = sprintf('DB serves MPU codes absent from the CSV: [%s]', implode(', ', $extraCodes));
        }

        $this->assertSame(
            [],
            $mismatches,
            "T-4: MPU-* cohort sets / session types must equal the programme dataset exactly:\n".implode("\n", $mismatches),
        );

        // T-4 (venue-type guard, design §4): labs are Practical-only per
        // venues.allowed_session_types, but the printed source genuinely puts
        // these L/T blocks in labs — kept as printed (user decision) with the
        // 17 documented rows allowed (DATASET-NOTES §3: 9 non-Networking
        // violations + 8 Networking/IoT, of which AMIT2034×2 sit in B010 — a
        // room-level exception). The exact-list compare is anti-rot in BOTH
        // directions: a regression OR a silently vacated allowance fails.
        $typeViolations = DB::table('class_sessions')
            ->join('venues', 'venues.id', '=', 'class_sessions.venue_id')
            ->join('modules', 'modules.id', '=', 'class_sessions.module_id')
            ->select(
                'class_sessions.day_of_week',
                'class_sessions.start_time',
                'class_sessions.end_time',
                'class_sessions.session_type',
                'venues.room_code',
                'venues.allowed_session_types',
                'modules.module_code',
            )
            ->get()
            ->filter(fn ($row): bool => ! str_contains((string) $row->allowed_session_types, (string) $row->session_type))
            ->map(fn ($row): string => sprintf(
                '%s|%s|%d|%s|%s–%s',
                $row->module_code,
                $row->room_code,
                (int) $row->day_of_week,
                $row->session_type,
                substr((string) $row->start_time, 0, 5),
                substr((string) $row->end_time, 0, 5),
            ))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            self::LAB_LT_ALLOWANCE,
            $typeViolations,
            sprintf(
                "T-4: L/T sessions in Practical-only labs must be EXACTLY the 17 documented rows (DATASET-NOTES §3):\nDB: [%s]\nallowed: [%s]",
                implode('], [', $typeViolations),
                implode('], [', self::LAB_LT_ALLOWANCE),
            ),
        );
    }

    public function test_t5_doc_db_parity(): void
    {
        $this->seedFreshDemoDatabase();

        ['sessions' => $docRows, 'counts' => $docCounts] = $this->parseTimetableDoc();

        // (a) anti-truncation: the doc table itself must hold exactly
        // EXPECTED_SESSIONS session rows — the Counts self-report is NOT
        // trusted for this number ([Z3] rows excluded).
        $this->assertCount(
            self::EXPECTED_SESSIONS,
            $docRows,
            sprintf(
                'T-5: parsed %d session rows from dataset/timetable.md (header/separator lines excluded) — must be exactly %d.',
                count($docRows),
                self::EXPECTED_SESSIONS,
            ),
        );

        $dbSessions = (int) DB::table('class_sessions')->count();
        $dbSessionCohorts = (int) DB::table('session_cohorts')->count();
        $dbOccupied = (int) DB::table('time_slots')
            ->where('semester_id', 1)
            ->where('status', 'occupied')
            ->count();

        $this->assertSame(
            [self::EXPECTED_SESSIONS, self::EXPECTED_SESSION_COHORTS, self::EXPECTED_OCCUPIED_SLOTS],
            [$dbSessions, $dbSessionCohorts, $dbOccupied],
            sprintf(
                'T-5: seeded DB must be %d/%d/%d (sessions / session_cohorts / occupied), found %d/%d/%d.',
                self::EXPECTED_SESSIONS,
                self::EXPECTED_SESSION_COHORTS,
                self::EXPECTED_OCCUPIED_SLOTS,
                $dbSessions,
                $dbSessionCohorts,
                $dbOccupied,
            ),
        );

        // (b) the doc's ## Counts block must match the live DB numbers.
        $this->assertSame(
            $dbSessions,
            $docCounts['sessions'],
            sprintf('T-5: ## Counts "Session count" (%s) must match DB class_sessions count (%d).', var_export($docCounts['sessions'], true), $dbSessions),
        );

        $this->assertSame(
            $dbSessionCohorts,
            $docCounts['session_cohorts'],
            sprintf('T-5: ## Counts "Session-cohort rows" (%s) must match DB session_cohorts count (%d).', var_export($docCounts['session_cohorts'], true), $dbSessionCohorts),
        );

        $this->assertSame(
            $dbOccupied,
            $docCounts['occupied'],
            sprintf('T-5: ## Counts "Occupied time slots" (%s) must match DB occupied count (%d).', var_export($docCounts['occupied'], true), $dbOccupied),
        );

        // (c) FULL bidirectional set-equality of (day, start, venue, module, cohort-set)
        // tuples — zero sampling ([Z1]).
        $docMap = [];

        foreach ($docRows as $row) {
            $key = $this->parityKey($row['day'], $row['start'], $row['venue'], $row['module']);

            // Lazy guard: the previous version formatted BOTH sprintf args eagerly, so
            // $docMap[$key] was evaluated on the row's FIRST occurrence (undefined) —
            // the eager access threw "Undefined array key" before this assert could
            // ever run. Only format the collision message when a collision exists.
            if (array_key_exists($key, $docMap)) {
                $this->fail(sprintf(
                    'T-5: duplicate doc row %s (doc cohorts [%s] vs earlier [%s]) — same day/start/venue/module listed twice.',
                    $key,
                    implode(', ', $row['cohorts']),
                    implode(', ', $docMap[$key]),
                ));
            }

            $docMap[$key] = $row['cohorts'];
        }

        $dbMap = [];
        $cohortNamesBySession = $this->cohortNamesBySessionId();

        DB::table('class_sessions')
            ->join('modules', 'modules.id', '=', 'class_sessions.module_id')
            ->join('venues', 'venues.id', '=', 'class_sessions.venue_id')
            ->select(
                'class_sessions.id',
                'class_sessions.day_of_week',
                'class_sessions.start_time',
                'modules.module_code',
                'venues.room_code',
            )
            ->get()
            ->each(function ($session) use (&$dbMap, $cohortNamesBySession): void {
                $key = $this->parityKey(
                    (int) $session->day_of_week,
                    substr((string) $session->start_time, 0, 5),
                    $session->room_code,
                    $session->module_code,
                );
                $dbMap[$key] = $cohortNamesBySession[(int) $session->id] ?? [];
            });

        $docWithoutDb = [];
        $dbWithoutDoc = [];
        $cohortSetMismatches = [];

        foreach ($docMap as $key => $docCohorts) {
            if (! array_key_exists($key, $dbMap)) {
                $docWithoutDb[] = sprintf('%s (doc cohorts [%s])', $key, implode(', ', $docCohorts));

                continue;
            }

            if ($dbMap[$key] !== $docCohorts) {
                $cohortSetMismatches[] = sprintf('%s: doc cohorts [%s] vs DB cohorts [%s]', $key, implode(', ', $docCohorts), implode(', ', $dbMap[$key]));
            }
        }

        foreach ($dbMap as $key => $dbCohorts) {
            if (! array_key_exists($key, $docMap)) {
                $dbWithoutDoc[] = sprintf('%s (DB cohorts [%s])', $key, implode(', ', $dbCohorts));
            }
        }

        $this->assertSame(
            [],
            $docWithoutDb,
            "T-5: parsed doc rows with NO live seeded session:\n".implode("\n", $docWithoutDb),
        );

        $this->assertSame(
            [],
            $cohortSetMismatches,
            "T-5: doc rows whose cohort set differs from the live session (order-insensitive sets):\n".implode("\n", $cohortSetMismatches),
        );

        $this->assertSame(
            [],
            $dbWithoutDoc,
            "T-5: live seeded sessions missing from the doc:\n".implode("\n", $dbWithoutDoc),
        );
    }

    public function test_t6_semester_holidays_and_accounts(): void
    {
        $this->seedFreshDemoDatabase();

        // Semester dates canonical (design §3.4: 2026-09-21 → 2026-12-27).
        $semester = DB::table('semesters')->where('semester_code', '202605')->first();
        $this->assertNotNull($semester, 'T-6: semester 202605 must exist.');
        $this->assertSame('2026-09-21', $semester->start_date, 'T-6: semester start_date must be the canonical W1 Monday.');
        $this->assertSame('2026-12-27', $semester->end_date, 'T-6: semester end_date must be the canonical W14 Sunday.');

        // Holidays: exactly the 3 canonical grid rows (Deepavali Sunday 8 Nov
        // is outside the Mon–Sat grid — docs-only, not a DB row).
        $holidays = DB::table('holidays')->where('semester_id', $semester->id)->get();
        $this->assertCount(3, $holidays, 'T-6: exactly 3 canonical holiday rows.');
        $expectedLabels = ['Deepavali Holiday (In Lieu)', 'Christmas Eve', 'Christmas Day'];
        foreach (self::HOLIDAY_PAIRS as $index => [$week, $day]) {
            $row = $holidays->first(fn ($h): bool => (int) $h->week_number === $week && (int) $h->day_of_week === $day);
            $this->assertNotNull($row, "T-6: holiday row W{$week} D{$day} missing.");
            $this->assertSame($expectedLabels[$index], $row->label, "T-6: holiday W{$week} D{$day} label mismatch.");

            $busy = DB::table('time_slots')
                ->where('semester_id', $semester->id)
                ->where('week_number', $week)
                ->where('day_of_week', $day)
                ->where('status', '!=', 'available')
                ->count();
            $this->assertSame(0, $busy, "T-6: {$busy} non-available slots on holiday W{$week} D{$day}.");
        }

        // Login accounts intact (prompt requirement; NavIdentityTest depends
        // on 5425's identity): 5425 PL, 5770 plain, 25RSD0001 student.
        $pl = DB::table('users')
            ->join('lecturers', 'lecturers.user_id', '=', 'users.id')
            ->where('lecturers.staff_id', '5425')
            ->first(['users.name', 'users.honorific', 'lecturers.is_pl']);
        $this->assertNotNull($pl, 'T-6: staff account 5425 must exist.');
        $this->assertSame('Surayaini Binti Basri', $pl->name, 'T-6: 5425 name mismatch.');
        $this->assertSame('Pn.', $pl->honorific, 'T-6: 5425 honorific mismatch.');
        $this->assertTrue((bool) $pl->is_pl, 'T-6: 5425 must keep is_pl = true.');

        $plain = DB::table('users')
            ->join('lecturers', 'lecturers.user_id', '=', 'users.id')
            ->where('lecturers.staff_id', '5770')
            ->first(['lecturers.is_pl']);
        $this->assertNotNull($plain, 'T-6: staff account 5770 must exist.');
        $this->assertFalse((bool) $plain->is_pl, 'T-6: 5770 must stay a plain lecturer (is_pl = false).');

        $student = DB::table('students')->where('student_id', '25RSD0001')->first();
        $this->assertNotNull($student, 'T-6: student account 25RSD0001 must exist.');
    }

    public function test_t7_import_verify_command_passes_on_fresh_seed(): void
    {
        $this->seedFreshDemoDatabase();

        // R3.3/R3.4: on the imported state the idempotent re-run IS
        // verify-only — preflight fingerprint + Phase 4 read-only checks,
        // exit 0, zero writes. This exercises the command's verify branch in
        // CI, not only on the demo DB.
        $this->artisan('crs:import-real-schedule', ['--verify' => true])
            ->assertExitCode(0);
    }

    /**
     * Parse dataset/timetable.md per the §4.1 format contract.
     *
     * Non-session rows ([Z3]): the title line, blank lines, `## Day` headings, the
     * `| start–end |…|` header lines and the `|---|` separator lines are all excluded
     * from the parsed-session count. The `## Counts` block is parsed separately with
     * its three literal patterns.
     *
     * @return array{sessions: list<array{day: int, start: string, end: string, venue: string, module: string, lecturer: string, type: string, cohorts: list<string>}>, counts: array{sessions: int|null, session_cohorts: int|null, occupied: int|null}}
     */
    private function parseTimetableDoc(): array
    {
        $path = base_path('dataset/timetable.md');
        $this->assertFileExists($path, 'T-5: dataset/timetable.md must exist for the doc↔DB parity check.');

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $this->assertNotFalse($lines, sprintf('T-5: dataset/timetable.md (%s) is unreadable.', $path));

        $sessions = [];
        $counts = ['sessions' => null, 'session_cohorts' => null, 'occupied' => null];
        $day = null;

        foreach ($lines as $line) {
            // `## <Day> (day_of_week = N)` — carries the day for the rows beneath it.
            if (preg_match('/^## .+\(day_of_week = (-?\d+)\)$/', $line, $matches) === 1) {
                $day = (int) $matches[1];

                continue;
            }

            if (preg_match('/^- Session count: (\d+)$/', $line, $matches) === 1) {
                $counts['sessions'] = (int) $matches[1];

                continue;
            }

            if (preg_match('/^- Session-cohort rows: (\d+)$/', $line, $matches) === 1) {
                $counts['session_cohorts'] = (int) $matches[1];

                continue;
            }

            if (preg_match('/^- Occupied time slots: (\d+)$/', $line, $matches) === 1) {
                $counts['occupied'] = (int) $matches[1];

                continue;
            }

            if (! str_starts_with($line, '|')) {
                // Title, `## Counts` heading, blank lines, and everything else.
                continue;
            }

            // [Z3]: the table header and separator lines are non-session rows.
            if (str_contains($line, 'start–end') || preg_match('/^\|\s*-{3}/', $line) === 1) {
                continue;
            }

            if ($day === null) {
                throw new RuntimeException(sprintf('timetable.md: table row before any day heading: "%s".', $line));
            }

            $cells = array_values(array_filter(array_map('trim', explode('|', $line)), fn (string $cell): bool => $cell !== ''));

            if (count($cells) !== 6) {
                throw new RuntimeException(sprintf('timetable.md: malformed table row: "%s" (expected 6 cells, found %d).', $line, count($cells)));
            }

            [$start, $end] = $this->splitTimeRange($cells[0]);
            $cohorts = array_values(array_filter(array_map('trim', explode(',', $cells[5])), fn (string $name): bool => $name !== ''));

            $sessions[] = [
                'day' => $day,
                'start' => $start,
                'end' => $end,
                'venue' => $cells[1],
                'module' => $cells[2],
                'lecturer' => $cells[3],
                'type' => $cells[4],
                'cohorts' => $cohorts,
            ];
        }

        return ['sessions' => $sessions, 'counts' => $counts];
    }

    /**
     * Cohort ids -> names per class_session, for the T-5 parity comparison.
     *
     * @return array<string, list<string>> keyed by class_session_id
     */
    private function cohortNamesBySessionId(): array
    {
        $rows = DB::table('session_cohorts')
            ->join('cohorts', 'cohorts.id', '=', 'session_cohorts.cohort_id')
            ->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
            ->select(
                'session_cohorts.class_session_id',
                'programmes.programme_code',
                'cohorts.current_year',
                'cohorts.semester',
                'cohorts.tutorial_group',
            )
            ->get();

        $names = [];

        foreach ($rows as $row) {
            $names[(string) $row->class_session_id][] = $this->cohortName($row);
        }

        foreach ($names as &$list) {
            sort($list);
        }

        return $names;
    }
}
