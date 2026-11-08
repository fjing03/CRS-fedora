<?php

namespace App\Services;

use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Support\LocalTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared lecturer-session catalog + the venue-type eligibility rule map.
 *
 * SDD arrange-intent-session-choice (design D0/D1): the eligibility rule
 * lives EXACTLY once, PHP-side, and is shipped to the pages as DATA. The
 * venue page's "Select a Session" modal (intentSessionOptions) and the
 * arrangement page's fallback prefill both consume this catalog — no JS
 * hosts a venue-eligibility rule.
 *
 * Encodings (contract, mirrored in the design):
 * - ruleMap keys/values are the raw DB values: `class_sessions.session_type`
 *   (L/T/P) against `venues.room_type` (tutorial/lecture_hall/lab/cisco_lab —
 *   the CHECK constraint's own vocabulary).
 *   (The B005 no-Diploma pattern + venue code were REPEALED 2026-10-27 —
 *   SDD b005-diploma-allowed: the keys are GONE, the law is data-free.)
 * - day_of_week is the DB import encoding 0–5 = Mon–Sat (Sundays carry no
 *   teaching data; index 6 exists in the abbr table only for the label path).
 */
final class LectureSessionCatalog
{
    /** Weekday abbreviations, indexed by day_of_week (0–5 = Mon–Sat). */
    public const DAY_ABBRS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * The type-aware lab roster (user ruling 2026-11-07, SDD
     * `lab-access-type-aware`): `module_code => session types whose OWN seeded
     * session already sits in a `lab` / `cisco_lab`. This is the whole roster —
     * there is no second module list; `b006_allowed_module_codes` is derived
     * from these keys, and BOTH additive arms (any lab, and B006 by room code)
     * require the session type to be present here.
     *
     * Derived from the seed in BOTH directions by
     * `TimetableSeedInvariantsTest::test_t8_…`: every pair below has a seeded
     * lab L/T of that exact type, and every seeded lab L/T is listed. A future
     * import that moves a type into a lab, or out of one, fails that test until
     * this map is edited on purpose.
     *
     * L in a lab (7): AMIT2033, BMIT2154, BMIT3084, AMIT2034 (B006/B010),
     * BMIS2003 (B006), BMIT1723 (B011), BMIT2013 (B009).
     * T in a lab (10): AMIT2033, BMIT2154, BMIT3084, AMIT2034 (B006/B010),
     * AMCS1043 (B011), AMIS1003 (B010), AMSE1003 (B009), BMCS3033 (B011),
     * BMIT1173 (B009), AMCS1013 (B006 — its L is in B111, hence `T` only).
     *
     * @var array<string, list<string>>
     */
    public const LAB_ACCESS = [
        'AMIT2033' => ['L', 'T'],
        'BMIT2154' => ['L', 'T'],
        'BMIT3084' => ['L', 'T'],
        'AMIT2034' => ['L', 'T'],
        'BMIT2013' => ['L'],   // its L is in B009; it has NO seeded T at all
        'BMIS2003' => ['L'],   // B006 lecture
        'BMIT1723' => ['L'],   // B011 lecture
        'AMCS1013' => ['T'],   // T in B006; its L sits in Lecture Hall B111
        'AMCS1043' => ['T'],
        'AMIS1003' => ['T'],
        'AMSE1003' => ['T'],
        'BMCS3033' => ['T'],
        'BMIT1173' => ['T'],
    ];

    /**
     * The single-source eligibility rule (raw DB encodings; JSON-able — the
     * pages bake it into page-data and the client only READS it).
     *
     * SDD venue-restrictions-enforcement (2026-10-24): the campus rule book is
     * ENFORCED here as data — the module-conditioned ALLOW/DISALLOW matrix
     * (networking subjects gain additive lab access for L/T; B006 admits
     * those modules' L/T; its population is FOCS-only per R6, DFT/DSF/RSD
     * allowed, RAF/RBU never) composites OVER these base rows for EVERY
     * session type (design D2's one boolean formula — never an early-return
     * chain). R4/IoT was DROPPED by the user (no IoT keys exist on purpose).
     * 2026-11-03 (user ruling): BMIT2013 joins the additive lab roster — its
     * lecture already runs in B009 in the seeded real timetable (a pinned seed
     * invariant), so the booking rule now matches the timetable instead of
     * contradicting it.
     * Capacity is NOT part of this gate — the engine's separate check.
     *
     * @return array{P: list<string>, T: list<string>, L: list<string>, lab_access: array<string, list<string>>, b006_allowed_module_codes: list<string>, b006_focs_programmes: list<string>, b006_banned_programmes: list<string>, b006_venue_code: string}
     */
    public static function ruleMap(): array
    {
        // SDD lt-venue-capacity-rule (2026-10-19) + lh-l-only-revert
        // (2026-10-20): the 2026-10-19 dual admission NARROWED — Lecture
        // Halls admit L ONLY; tutorials keep L/T ("the Lecture Hall only
        // allow for L only", the user's ruling). Capacity-gated at every
        // consumer (engine-side; NEVER this gate).
        return [
            'P' => ['lab', 'cisco_lab'],
            'T' => ['tutorial'],   // lh-l-only-revert (2026-10-20): the lecture hall narrows back to L-only
            'L' => ['tutorial', 'lecture_hall'],
            /* venue-restrictions-enforcement (the user's 2026-10-24
               ALLOW/DISALLOW law): networking subjects' L/T may use any lab
               (additive — their R1 rooms stay); B006's L/T roster is DERIVED
               from the same map (one authority, no second copy); the
               population gate keys (R6). NOTE this
               module-conditioned data is the ONLY rule authority now — the
               allowed_session_types columns are untouched reference
               inventory (Q6: no migration).
               2026-11-03 (user ruling, docs/venue-restrictions.md R3): the
               roster now ALSO carries every non-networking subject whose
               seeded L/T already sits in a lab — BMIT2013 plus the other
               eight "kept as printed" modules (DATASET-NOTES §3). The user's
               words: "allow this class : BMIT2013 (L) to execute in lab" and
               then "are there any other subjects' L/T execute in lab but
               disallowed ?? add them to the array." The booking rule may not
               contradict the timetable it ships with; the seed invariant test
               now pins that alignment (TimetableSeedInvariantsTest).

               2026-11-07 (user ruling, SDD lab-access-type-aware) — SUPERSEDES
               the per-subject reading above: the roster is per **module AND
               session type**. A listed module's L/T may use a lab only when
               that type's OWN seeded session already sits in a lab. The
               2026-11-03 widening was right about which modules and wrong
               about which types: six modules whose L lives in a hall/tutorial
               room could book all five labs, and AMCS1013's L could book the
               very B006 that its T legitimately uses. The two flat arrays are
               therefore ONE type-aware map (`LAB_ACCESS`). */
            'lab_access' => self::LAB_ACCESS,
            // DERIVED from `lab_access` (~never hand-maintained): the B006 roster
            // IS the map's module set, so the two can never diverge. The shipped
            // page payload and the docs still read this key.
            'b006_allowed_module_codes' => array_keys(self::LAB_ACCESS),
            'b006_focs_programmes' => ['DFT', 'DSF', 'RSD'],
            'b006_banned_programmes' => ['RAF', 'RBU'],
            'b006_venue_code' => 'B006',
            // B005's R2 no-Diploma keys were REPEALED 2026-10-27
            // (SDD b005-diploma-allowed) — the keys are GONE by design; a
            // comeback would be a NEW user ruling, not a stub.
        ];
    }

    /**
     * Convenience entry for callers that don't already hold the collection
     * (`VenueTimetable::intentSessionOptions`). Owner-pinned by the caller:
     * pass auth()->id() — never another lecturer's id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function payloadFor(int $lecturerUserId, ?int $week1 = null, ?int $semesterId = null): array
    {
        $query = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme'])
            ->where('lecturer_id', $lecturerUserId);

        if ($semesterId !== null) {
            $query->where('semester_id', $semesterId);
        }

        $sessions = $query->orderBy('day_of_week')->orderBy('start_time')->orderBy('id')->get();

        return $this->build($sessions, $week1);
    }

    /**
     * The catalog builder. `sessions` must be ordered by (day_of_week,
     * start_time, id) — the module entries then follow first-occurrence
     * timetable order (what the UI "dropdown display order" means).
     *
     * Shape contract (design D1): each module carries `sessions` as an
     * ID-KEYED map (`sessions[(string) id]` — the load-bearing lookups at
     * courseCtx()/wanted-bind stay intact) plus a parallel `order` array of
     * session ids carrying the canonical order. `ReplacementArrangement::coursesPayload()`
     * delegates here; its public shape stays identical (the `order` array is
     * additive and simply ignored by consumers that predate this change).
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $sessions, ?int $week1 = null): array
    {
        $weekContext = $week1 !== null ? $this->weekContext($week1) : null;

        /* USER AMENDMENT (2026-10-15, apply round 3): conflicted-only policy —
           the modal/fallback offer only occurrences that NEED the replacement
           (ReplacementHome parity): class_exception cancelled, public-holiday
           hit (the B005 venue-restriction arm was REPEALED 2026-10-27,
           SDD b005-diploma-allowed) — for the BOOKED week.
           Active pending/approved requests exclude the occurrence (D1: already
           being handled). Without week context (facts-only mode) no filtering. */
        $conflicts = $weekContext !== null
            ? $this->conflictFacts($sessions, $weekContext['week1'])
            : null;

        return $sessions
            ->groupBy('module_id')
            ->map(function (Collection $group) use ($weekContext, $conflicts): array {
                $first = $group->first();

                // $group preserves the query order (day, start, id) — the id
                // map keyed the same way today's coursesPayload did; the
                // order array carries that canonical sequence explicitly.
                $orderedIds = [];
                $sessionsMap = [];
                foreach ($group as $s) {
                    $entry = $this->sessionEntry($s, $weekContext);
                    if ($conflicts !== null) {
                        $reason = $conflicts[$s->id] ?? null;
                        if ($reason === null) {
                            continue; // healthy occurrence → not offered (conflicted-only)
                        }
                        $entry['conflicted'] = true;
                        $entry['conflictReason'] = $reason;
                    }
                    $orderedIds[] = (int) $s->id;
                    $sessionsMap[(string) $s->id] = $entry;
                }

                $uniqueCohorts = $group->flatMap(fn ($s) => $s->cohorts)
                    ->unique('id')
                    ->values();

                return [
                    'code' => (string) $first->module->module_code,
                    'name' => (string) $first->module->module_name,
                    // Module-grain default kept for shape parity (occurrence-
                    // grain context rules replacements: the session's facts,
                    // never the module default — explore A2/A3).
                    'type' => (string) $first->session_type,
                    'sessions' => $sessionsMap,
                    'order' => $orderedIds,
                    'cohorts' => $uniqueCohorts
                        ->map(fn ($c): string => $this->spacedCohortLabel($c))
                        ->all(),
                    'cohortCounts' => $uniqueCohorts
                        ->map(fn ($c): int => (int) $c->student_count)
                        ->all(),
                    'studentCount' => (int) $uniqueCohorts->sum('student_count'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Is this session admissible for a venue of $rawRoomType? THE one matrix
     * read (SDD venue-restrictions-enforcement design D2): ONE boolean
     * formula over the rule data — (base row OR labsAdditive OR b006Widen)
     * AND NOT b006PopulationDeny (the b005DiplomaDeny arm was REPEALED
     * 2026-10-27) — NOT an
     * early-return step chain (the deny gates must compose over the widened
     * sets for EVERY session type). Fail-closed: an unloaded module
     * relation is in NO list (base rows + gates only — never widen, never
     * throw); an unloaded cohort relation DENIES a B006 population check
     * (it cannot prove FOCS). Capacity is NOT part of this gate (the
     * engine's separate check). Duration/day-end fitness is arithmetic the
     * CLIENT does against the shared hours constant — intentionally not
     * here.
     */
    public static function sessionAllowedForVenue(string $sessionType, string $rawRoomType, ClassSession $session, string $venueCode): bool
    {
        $rule = self::ruleMap();

        $allowedRooms = $rule[$sessionType] ?? null;
        if (! is_array($allowedRooms)) {
            return false;   // unknown session type — fail closed
        }

        // fail-closed module identity (the round-1 🔴: the gate reads the
        // relation INSIDE; no eager-loaded module = in NO widen list).
        $moduleCode = $session->relationLoaded('module') && $session->module !== null
            ? (string) $session->module->module_code
            : null;

        // the ALLOW half — one OR set (design D2):
        $inWidenRows = (bool) $moduleCode;
        $baseRow = in_array($rawRoomType, $allowedRooms, true);
        // 2026-11-07 (SDD lab-access-type-aware): the additive arms test the
        // SESSION TYPE against the module's own entry — an L of a module whose
        // only seeded lab session is a T gains nothing. An unknown module, or an
        // unloaded relation, yields [] → no widening (the old fail-closed guard).
        $moduleLabTypes = $inWidenRows ? ($rule['lab_access'][$moduleCode] ?? []) : [];
        $labsAdditive = in_array($sessionType, $moduleLabTypes, true)
            && in_array($rawRoomType, ['lab', 'cisco_lab'], true);
        $b006Widen = $venueCode === $rule['b006_venue_code']
            && in_array($sessionType, $moduleLabTypes, true);
        if (! ($baseRow || $labsAdditive || $b006Widen)) {
            return false;
        }

        // R6 population deny (B006): EVERY cohort's programme must hit the
        // FOCS list; ANY hit on the banned list denies. Fail-closed on an
        // unloaded cohort relation (cannot prove FOCS). A cohort-less
        // session passes vacuously (recorded: no such class exists in the
        // DB today).
        if ($venueCode === $rule['b006_venue_code']) {
            if (! $session->relationLoaded('cohorts')) {
                return false;
            }
            foreach ($session->cohorts as $cohort) {
                $programme = $cohort->programme !== null ? (string) $cohort->programme->programme_code : '';
                if (in_array($programme, $rule['b006_banned_programmes'], true)) {
                    return false;
                }
                if (! in_array($programme, $rule['b006_focs_programmes'], true)) {
                    return false;
                }
            }
        }

        // R2 no-Diploma (B005) was REPEALED 2026-10-27 (SDD
        // b005-diploma-allowed): the deny block is GONE by design — a
        // comeback would be a NEW user ruling, not a stub. The remaining
        // gates: the type rows, the B006 composite, and the engine's
        // capacity check.

        return true;
    }

    /**
     * @param  array{monday: Carbon, week1: int}|null  $weekContext
     * @return array<string, mixed>
     */
    private function sessionEntry(ClassSession $s, ?array $weekContext): array
    {
        $start = substr((string) $s->start_time, 0, 5);
        $end = substr((string) $s->end_time, 0, 5);
        $duration = round(Carbon::parse((string) $s->start_time)->diffInMinutes(Carbon::parse((string) $s->end_time)) / 60, 2);

        $entry = [
            'type' => (string) $s->session_type,
            'venue' => $s->venue !== null ? (string) $s->venue->room_code : '',
            'duration' => $duration,
            'durationSlots' => (int) max(1, (int) ceil($duration * 2)), // half-hour slots
            'dayOfWeek' => (int) $s->day_of_week,
            'startTime' => $start,
            'endTime' => $end,
            'studentCount' => (int) $s->cohorts->sum('student_count'),
            // display labels first (shape parity with the legacy payload);
            // compact codes (the B005 pattern inputs were REPEALED
            // 2026-10-27 — kept: the client's cohort coding stays).
            'cohorts' => $s->cohorts
                ->map(fn ($c): string => $this->spacedCohortLabel($c))
                ->all(),
            'cohortCodes' => $s->cohorts
                ->map(fn ($c): string => str_replace(' ', '', $this->spacedCohortLabel($c)))
                ->all(),
            'cohortCounts' => $s->cohorts
                ->map(fn ($c): int => (int) $c->student_count)
                ->all(),
        ];

        // dayAbbr is occurrence-independent (the session's own recurring day) —
        // the week-agnostic pool label needs it even in facts-only mode.
        $entry['dayAbbr'] = self::DAY_ABBRS[(int) $s->day_of_week];
        if ($weekContext !== null) {
            $occurrenceDate = $weekContext['monday']->copy()->addDays((int) $s->day_of_week);
            $entry['date'] = $occurrenceDate->format('d M Y'); // {DD Mon YYYY} — the frozen label grain
            $entry['weekNumber'] = (int) $weekContext['week1'];
        }

        return $entry;
    }

    /**
     * Which of the lecturer's sessions are CONFLICTED at $week1 (ReplacementHome
     * parity): exception-cancelled or public-holiday-hit (the B005
     * venue-restriction arm REPEALED 2026-10-27).
     * Active requests exclude ("already being handled", D1 rule).
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, string> session id => human reason
     */
    private function conflictFacts(Collection $sessions, int $week1): array
    {
        // SDD occurrence-week-pin-truthfulness D-P0: ONE implementation —
        // the batched context + the pure occurrence verdict (byte-parity
        // shapes and reason strings; the hand-rolled branch chain retired).
        $factContext = $this->factContext($sessions);

        $out = [];
        foreach ($sessions as $s) {
            $fact = self::occurrenceFact($s, $week1, $factContext);
            if ($fact !== null) {
                $out[(int) $s->id] = $fact['reason'];
            }
        }

        return $out;
    }

    /**
     * All weeks where the lecturer has at least one CONFLICTED occurrence
     * (ReplacementHome parity), grouped per week: week1 => [sessionId => reason].
     * Consumed by the producer modal's zero-fit notice (jump-to-week hints).
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array<int, string>>
     */
    public function conflictMapFor(Collection $sessions): array
    {
        $ids = $sessions->pluck('id')->all();
        if (count($ids) === 0) {
            return [];
        }

        // SDD occurrence-week-pin-truthfulness D-P0: ONE impl over the
        // batched primitive (3 batched queries per call in total, verdict
        // = the pure occurrenceFact; the per-week branch chain retired).
        $semester = Semester::active();
        $factContext = $this->factContext($sessions);

        $out = [];
        foreach ($sessions as $s) {
            for ($week = 1; $week <= self::weekBound($semester); $week++) {
                $fact = self::occurrenceFact($s, $week, $factContext);
                if ($fact !== null) {
                    $out[$week][(int) $s->id] = $fact['reason'];
                }
            }
        }

        return $out;
    }

    /**
     * Semester-wide conflicted candidates

    /**
     * Semester-wide conflicted candidates (user directive 2026-10-16, apply
     * round 4): the venue modal's pool is WEEK-AGNOSTIC — a W14-conflicted
     * class may be arranged at any week past the lead time (e.g. W6), so the
     * pool comes from the whole semester, never just the viewed week.
     * Flattens the conflict map: session id => human reason + sorted weeks.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array{reason: string, weeks: list<int>, occurrences: list<array{week: int, dayAbbr: string, date: string, label: string, reason: string}>}>
     */
    public function conflictedFor(Collection $sessions): array
    {
        $map = $this->conflictMapFor($sessions);
        $out = [];
        if ($map === []) {
            return $out;
        }

        // weekContext re-parses the semester per call — cache the Mondays once.
        $mondays = [];
        foreach (array_keys($map) as $week) {
            $context = $this->weekContext((int) $week);
            if ($context !== null) {
                $mondays[(int) $week] = $context['monday'];
            }
        }

        foreach ($sessions as $s) {
            $rows = [];
            foreach ($map as $week => $reasons) {
                $reason = $reasons[$s->id] ?? null;
                if ($reason === null) {
                    continue;
                }
                $monday = $mondays[(int) $week] ?? null;
                $occDate = $monday !== null ? $monday->copy()->addDays((int) $s->day_of_week) : null;
                $rows[] = [
                    'week' => (int) $week,
                    'dayAbbr' => self::DAY_ABBRS[(int) $s->day_of_week],
                    'date' => $occDate !== null ? $occDate->format('d M Y') : '',
                    'label' => $occDate !== null ? $occDate->format('j M Y') : '',
                    'reason' => $reason,
                ];
            }
            if ($rows === []) {
                continue;
            }
            usort($rows, fn (array $a, array $b): int => $a['week'] <=> $b['week']);
            $out[(int) $s->id] = [
                'reason' => $rows[0]['reason'],
                'weeks' => array_map(fn (array $r): int => $r['week'], $rows),
                'occurrences' => $rows,
            ];
        }

        return $out;
    }

    // ═══ occurrence-week-pin-truthfulness D-P0: the ONE fact-lookup primitive ═══

    /**
     * The week bound — week_count ?? 14, the catalog's single read (the
     * action's FR 2.9 range check reads it too; the literal 14's last
     * outside-the-catalog home is gone).
     */
    public static function weekBound(?Semester $semester = null): int
    {
        $semester ??= Semester::active();

        return $semester->week_count ?? 14;
    }

    /**
     * Batched fact context — EVERYTHING the verdict needs, loaded ONCE per
     * consumer call: the active semester, its week bound, handled keys
     * (pending/approved requests), class_exceptions and holidays are ALL
     * batch-loaded; the verdict itself is pure in-memory.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array{semester: ?Semester, weeks: int, handled: array<string, true>, exceptions: array<string, ClassException>, holidays: array<int, array<int, string>>}
     */
    public function factContext(Collection $sessions): array
    {
        $ids = $sessions->pluck('id')->all();
        if (count($ids) === 0) {
            return ['semester' => null, 'weeks' => self::weekBound(), 'handled' => [], 'exceptions' => [], 'holidays' => []];
        }

        $semester = Semester::active();

        $handled = ReplacementRequest::query()
            ->whereIn('class_session_id', $ids)
            ->whereIn('status', ['pending', 'approved'])
            ->get()
            ->mapWithKeys(fn (ReplacementRequest $r): array => [$r->class_session_id.':'.$r->week_number => true])
            ->all();

        $exceptions = ClassException::query()
            ->whereIn('class_session_id', $ids)
            ->get()
            ->keyBy(fn (ClassException $e): string => (string) $e->class_session_id.':'.$e->week_number)
            ->all();

        $holidays = [];
        if ($semester !== null) {
            foreach ($semester->holidays()->get(['week_number', 'day_of_week', 'label']) as $h) {
                $holidays[(int) $h->week_number][(int) $h->day_of_week] = (string) $h->label;
            }
        }

        return ['semester' => $semester, 'weeks' => self::weekBound($semester), 'handled' => $handled, 'exceptions' => $exceptions, 'holidays' => $holidays];
    }

    /**
     * The verdict primitive: the live conflict FACT for one <session, week>
     * pair, or null when the pair carries NO fact (a normal bookable
     * occurrence) — the home list's derivation as ONE implementation.
     * $context optional: omit it for a ONE-shot pair (a one-session context
     * builds internally — F1's SubmitReplacementRequest call); week-loop
     * consumers pass the shared factContext() instead.
     *
     * @param  array{semester?: ?Semester, weeks?: int, handled: array<string, true>, exceptions: array<string, ClassException>, holidays: array<int, array<int, string>>}|null  $context
     * @return array{kind: 'exception'|'holiday'|'venue', reason: string}|null
     */
    public function occurrenceFact(ClassSession $session, int $weekNumber, ?array $context = null): ?array
    {
        $ctx = $context ?? $this->factContext(Collection::wrap($session));
        $weeks = isset($ctx['weeks']) ? (int) $ctx['weeks'] : self::weekBound($ctx['semester'] ?? null);

        if ($weekNumber < 1 || $weekNumber > $weeks) {
            return null;
        }

        // ① handled: a live pending/approved request already carries this
        // pair — a deliberate exclusion (NOT a "no conflict").
        if (isset($ctx['handled'][$session->id.':'.$weekNumber])) {
            return null;
        }

        // ② exception at the pair.
        $exception = $ctx['exceptions'][$session->id.':'.$weekNumber] ?? null;
        if ($exception !== null) {
            $reason = str_replace('_', ' ', ucwords((string) $exception->reason, '_'));

            return ['kind' => 'exception', 'reason' => 'Cancelled — '.$reason];
        }

        // ③ holiday on the session's own day this week.
        if (isset($ctx['holidays'][$weekNumber][(int) $session->day_of_week])) {
            return ['kind' => 'holiday', 'reason' => 'Public Holiday ('.$ctx['holidays'][$weekNumber][(int) $session->day_of_week].')'];
        }

        // ④ (was) the venue restriction — REPEALED 2026-10-27 (SDD
        // b005-diploma-allowed): no conflict kind remains after the
        // exception (②) and holiday (③) arms.

        return null;
    }

    /**
     * Render-ready index; consumers attach in a later change — do NOT
     * purge as dead code (frozen D-P0 API member).
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<string, array{kind: string, reason: string}> "session:week" => fact
     */
    public function factsIndex(Collection $sessions): array
    {
        $ctx = $this->factContext($sessions);

        $out = [];
        foreach ($sessions as $s) {
            for ($week = 1; $week <= $ctx['weeks']; $week++) {
                $fact = self::occurrenceFact($s, $week, $ctx);
                if ($fact !== null) {
                    $out[$s->id.':'.$week] = ['kind' => $fact['kind'], 'reason' => $fact['reason']];
                }
            }
        }

        return $out;
    }

    /**
     * Week scaffold for occurrence facts — mirrors the timeline concern's
     * Monday math (semester start + (week-1) weeks), so the modal labels and
     * the grid agree on the booked week's dates by construction.
     *
     * @return array{monday: Carbon, week1: int}|null
     */
    private function weekContext(int $week1): ?array
    {
        if ($week1 < 1) {
            return null;
        }

        $semester = Semester::active();
        $weeks = self::weekBound($semester);
        if ($week1 > $weeks) {
            return null;
        }

        $start = Carbon::parse($semester !== null ? $semester->start_date : LocalTime::today());
        $monday = $start->copy()->addWeeks($week1 - 1);

        return ['monday' => $monday, 'week1' => $week1];
    }

    /**
     * Spaced-class cohort convention identical to the legacy payloads
     * (module 'cohorts' arrays; the client's B005 pattern input was
     * repealed 2026-10-27).
     */
    private function spacedCohortLabel(?Cohort $cohort): string
    {
        if ($cohort === null || $cohort->programme === null) {
            return '—';
        }

        return $cohort->programme->programme_code
            .$cohort->current_year.' (S'.$cohort->semester.')'
            .' G'.$cohort->tutorial_group;
    }
}
