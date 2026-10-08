<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * SDD change `repair-timetable-seed-data` — the five frozen invariants (design §5, T-1..T-5).
 *
 * Full-seed precondition via OCCValidatorTest's suite-proven explicit `$this->seed()`
 * pattern, run inside RefreshDatabase's per-test transaction (the seed rolls back with
 * it, so tests share no mutable state). The `$seed = true` property path is deliberately
 * NOT used: RefreshDatabaseState::$migrated is process-global, so a RefreshDatabase
 * class that ran migrate:fresh earlier in the same process — without seeding — would
 * leave this class silently un-seeded.
 *
 * Frozen §1 numbers under test: 35 class_sessions / 44 session_cohorts rows /
 * 1988 occupied time_slots / all 14 cohorts covered / 0 overlap pairs.
 */
final class TimetableSeedInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_SESSIONS = 35;

    private const EXPECTED_SESSION_COHORTS = 44;

    private const EXPECTED_OCCUPIED_SLOTS = 1988;

    private const EXPECTED_COHORTS = 14;

    /**
     * Seed the full demo database. Runs before every test; RefreshDatabase rolls the
     * seed back afterwards, so tests share no mutable state.
     */
    private function seedFreshDemoDatabase(): void
    {
        // nextval() is not transactional in PostgreSQL: an earlier test in this process
        // can leave the sequence advanced after its rollback, which would shift the
        // position-identified class ids (templates 1..35) the fixture seeders pin
        // (ReplacementRequestsSeeder's class_session_id 9/12/22 — design §6.2).
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

        $sessions = DB::table('class_sessions')->select('id', 'start_time', 'end_time')->get();
        $this->assertNotEmpty($sessions, 'T-1: no class_sessions rows — the seeder did not run.');

        $totalOccupied = 0;

        foreach ($sessions as $session) {
            // 2-h session -> 4 cells x 14 weeks = 56; the one 3-h session (tpl BMIT7072) -> 84.
            $durationMinutes = (int) round((strtotime($session->end_time) - strtotime($session->start_time)) / 60);
            $expected = (int) ceil($durationMinutes / 30) * 14;

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

        $cohortOverlapMessages = $cohortOverlaps->map(fn ($overlap): string => sprintf(
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
        ))->all();

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

        $this->assertSame(
            [],
            $cohortOverlapMessages,
            "T-2: a cohort must not appear in two overlapping sessions (back-to-back is legal):\n".implode("\n", $cohortOverlapMessages),
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

    public function test_t4_mpu_cohort_sets_per_spec(): void
    {
        $this->seedFreshDemoDatabase();

        $moduleCohorts = DB::table('session_cohorts')
            ->join('class_sessions', 'class_sessions.id', '=', 'session_cohorts.class_session_id')
            ->join('cohorts', 'cohorts.id', '=', 'session_cohorts.cohort_id')
            ->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
            ->join('modules', 'modules.id', '=', 'class_sessions.module_id')
            ->where('modules.module_code', 'MPU-3133')
            ->select(
                'cohorts.id',
                'programmes.programme_code',
                'cohorts.current_year',
                'cohorts.semester',
                'cohorts.tutorial_group',
            )
            ->get()
            ->unique('id');

        $mpu3133Names = $moduleCohorts->map(fn ($cohort): string => $this->cohortName($cohort))->values()->all();

        foreach (['RAF2(S3)G2', 'RAF2(S3)G4', 'RBU1(S1)G1'] as $required) {
            $this->assertContains(
                $required,
                $mpu3133Names,
                sprintf('T-4: MPU-3133 cohort set must include %s; present: [%s].', $required, implode(', ', $mpu3133Names)),
            );
        }

        $hasRsdCohort = $moduleCohorts->contains(fn ($cohort): bool => $cohort->programme_code === 'RSD');
        $this->assertTrue(
            $hasRsdCohort,
            sprintf('T-4: MPU-3133 cohort set must include >= 1 RSD-programme cohort; present: [%s].', implode(', ', $mpu3133Names)),
        );

        $foreign = $moduleCohorts
            ->reject(fn ($cohort): bool => in_array($cohort->programme_code, ['RAF', 'RBU', 'RSD'], true))
            ->map(fn ($cohort): string => sprintf('%s (programme %s)', $this->cohortName($cohort), $cohort->programme_code))
            ->all();

        $this->assertSame(
            [],
            $foreign,
            sprintf("T-4: MPU-3133 must serve no cohort outside the RAF/RBU/RSD programmes (programme_code NOT IN ('RAF','RBU','RSD') join filter):\n%s", implode("\n", $foreign)),
        );

        $mpu3232Names = DB::table('session_cohorts')
            ->join('class_sessions', 'class_sessions.id', '=', 'session_cohorts.class_session_id')
            ->join('cohorts', 'cohorts.id', '=', 'session_cohorts.cohort_id')
            ->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
            ->join('modules', 'modules.id', '=', 'class_sessions.module_id')
            ->where('modules.module_code', 'MPU-3232')
            ->select(
                'cohorts.id',
                'programmes.programme_code',
                'cohorts.current_year',
                'cohorts.semester',
                'cohorts.tutorial_group',
            )
            ->get()
            ->unique('id')
            ->map(fn ($cohort): string => $this->cohortName($cohort))
            ->values()
            ->all();

        // canonicalizing = order-insensitive set compare (the doc order is irrelevant
        // for a set; PHPUnit has no assertSameCanonicalizing — this is the real name).
        $this->assertEqualsCanonicalizing(
            ['RSD2(S1)G2', 'RSD2(S1)G3', 'RSD3(S1)G3'],
            $mpu3232Names,
            sprintf('T-4: MPU-3232 cohort set must be exactly {RSD2(S1)G2, RSD2(S1)G3, RSD3(S1)G3}; found: [%s].', implode(', ', $mpu3232Names)),
        );

        $mpu3232Types = DB::table('class_sessions')
            ->join('modules', 'modules.id', '=', 'class_sessions.module_id')
            ->where('modules.module_code', 'MPU-3232')
            ->pluck('session_type');

        $this->assertTrue(
            $mpu3232Types->contains('L'),
            sprintf('T-4: MPU-3232 must have >= 1 L session; found session types: [%s].', implode(', ', $mpu3232Types->all())),
        );

        $this->assertTrue(
            $mpu3232Types->contains('T'),
            sprintf('T-4: MPU-3232 must have >= 1 T session; found session types: [%s].', implode(', ', $mpu3232Types->all())),
        );

        $typeViolations = DB::table('class_sessions')
            ->join('venues', 'venues.id', '=', 'class_sessions.venue_id')
            ->select(
                'class_sessions.id',
                'class_sessions.session_type',
                'venues.room_code',
                'venues.allowed_session_types',
            )
            ->get()
            ->filter(fn ($row): bool => ! str_contains((string) $row->allowed_session_types, (string) $row->session_type))
            ->map(fn ($row): string => sprintf(
                'session %d: type %s not in venue %s allowed_session_types [%s]',
                $row->id,
                $row->session_type,
                $row->room_code,
                $row->allowed_session_types,
            ))
            ->all();

        $this->assertSame(
            [],
            $typeViolations,
            sprintf("T-4: every session's session_type must be contained in its venue's allowed_session_types:\n%s", implode("\n", $typeViolations)),
        );
    }

    public function test_t5_doc_db_parity(): void
    {
        $this->seedFreshDemoDatabase();

        ['sessions' => $docRows, 'counts' => $docCounts] = $this->parseTimetableDoc();

        // (a) anti-truncation: the doc table itself holds exactly 35 session rows —
        // the Counts self-report is NOT trusted for this number ([Z3] rows excluded).
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
