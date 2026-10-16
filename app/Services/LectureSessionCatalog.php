<?php

namespace App\Services;

use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Models\Semester;
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
 *   the CHECK constraint's own vocabulary), plus the B005 no-Diploma pattern
 *   and venue code.
 * - day_of_week is the DB import encoding 0–5 = Mon–Sat (Sundays carry no
 *   teaching data; index 6 exists in the abbr table only for the label path).
 */
final class LectureSessionCatalog
{
    /** Weekday abbreviations, indexed by day_of_week (0–5 = Mon–Sat). */
    public const DAY_ABBRS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * The single-source eligibility rule (raw DB encodings; JSON-able — the
     * pages bake it into page-data and the client only READS it).
     *
     * B005: a session with any cohort whose programme_code matches the
     * pattern below may not be offered for B005 (doc rule, client+modal
     * filtering only until venue-restrictions enforcement lands).
     *
     * @return array{P: list<string>, T: list<string>, L: list<string>, b005_no_diploma_pattern: string, b005_venue_code: string}
     */
    public static function ruleMap(): array
    {
        return [
            'P' => ['lab', 'cisco_lab'],
            'T' => ['tutorial'],
            'L' => ['lecture_hall'],
            'b005_no_diploma_pattern' => '^\\s*D',
            'b005_venue_code' => 'B005',
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
           hit, or the B005 venue-restriction conflict — for the BOOKED week.
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
     * Is this session admissible for a venue of $rawRoomType? Pure rule read
     * over data + the B005 cohort pattern (type ∈ map values + B005 rule).
     * Duration/day-end fitness is arithmetic the CLIENT does against the
     * shared hours constant — intentionally not here.
     */
    public static function sessionAllowedForVenue(string $sessionType, string $rawRoomType, ClassSession $session, string $venueCode): bool
    {
        $rule = self::ruleMap();

        $allowedRooms = $rule[$sessionType] ?? null;
        if (! is_array($allowedRooms) || ! in_array($rawRoomType, $allowedRooms, true)) {
            return false;
        }

        if ($venueCode === $rule['b005_venue_code']) {
            $pattern = $rule['b005_no_diploma_pattern'];
            foreach ($session->cohorts as $cohort) {
                $programme = $cohort->programme !== null ? $cohort->programme->programme_code : '';
                if (preg_match('/'.$pattern.'/', $programme) === 1) {
                    return false;
                }
            }
        }

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
            // compact codes added for the B005-style pattern checks.
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
     * parity): exception-cancelled, public-holiday-hit, or B005-restricted.
     * Active requests exclude ("already being handled", D1 rule).
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, string> session id => human reason
     */
    private function conflictFacts(Collection $sessions, int $week1): array
    {
        $ids = $sessions->pluck('id')->all();
        if (count($ids) === 0) {
            return [];
        }

        $semester = Semester::active();
        $weekContext = $this->weekContext($week1);
        $handled = ReplacementRequest::query()
            ->whereIn('class_session_id', $ids)
            ->whereIn('status', ['pending', 'approved'])
            ->where('week_number', $week1)
            ->pluck('class_session_id')
            ->flip();

        $exceptions = ClassException::query()
            ->whereIn('class_session_id', $ids)
            ->where('week_number', $week1)
            ->get()
            ->keyBy(fn ($e) => (int) $e->class_session_id);

        $holidays = [];
        if ($semester !== null) {
            foreach ($semester->holidays()->where('week_number', $week1)->get(['week_number', 'day_of_week', 'label']) as $h) {
                $holidays[(int) $h->day_of_week] = (string) $h->label;
            }
        }

        $out = [];
        foreach ($sessions as $s) {
            if ($handled->has($s->id)) {
                continue;
            }
            if ($exceptions->has($s->id)) {
                $reason = $exceptions->get($s->id)->reason;
                $out[(int) $s->id] = 'Cancelled — '.str_replace('_', ' ', ucwords((string) $reason, '_'));

                continue;
            }
            if (isset($holidays[(int) $s->day_of_week])) {
                $out[(int) $s->id] = 'Public Holiday ('.$holidays[(int) $s->day_of_week].')';

                continue;
            }
            if ($s->venue !== null
                && $s->venue->room_code === 'B005'
                && $s->cohorts->contains(fn ($c) => str_starts_with((string) $c->programme->programme_code, 'D'))) {
                $out[(int) $s->id] = 'Venue Restriction (B005 no-Diploma)';
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

        $semester = Semester::active();
        $weeks = $semester !== null ? $semester->week_count : 14;

        $handled = ReplacementRequest::query()
            ->whereIn('class_session_id', $ids)
            ->whereIn('status', ['pending', 'approved'])
            ->get()
            ->map(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number)
            ->flip();

        $exceptions = ClassException::query()
            ->whereIn('class_session_id', $ids)
            ->get()
            ->groupBy('week_number');

        $holidays = [];
        if ($semester !== null) {
            foreach ($semester->holidays()->get(['week_number', 'day_of_week', 'label']) as $h) {
                $holidays[(int) $h->week_number][(int) $h->day_of_week] = (string) $h->label;
            }
        }

        $out = [];
        foreach ($sessions as $s) {
            $venueRestricted = $s->venue !== null
                && $s->venue->room_code === 'B005'
                && $s->cohorts->contains(fn ($c) => str_starts_with((string) $c->programme->programme_code, 'D'));

            for ($week = 1; $week <= $weeks; $week++) {
                if ($handled->has($s->id.':'.$week)) {
                    continue;
                }
                if ($exceptions->has($week) && $exceptions->get($week)->firstWhere('class_session_id', $s->id) !== null) {
                    $reason = (string) $exceptions->get($week)->firstWhere('class_session_id', $s->id)->reason;
                    $out[$week][(int) $s->id] = 'Cancelled — '.str_replace('_', ' ', ucwords($reason, '_'));
                } elseif (isset($holidays[$week][(int) $s->day_of_week])) {
                    $out[$week][(int) $s->id] = 'Public Holiday ('.$holidays[$week][(int) $s->day_of_week].')';
                } elseif ($venueRestricted) {
                    $out[$week][(int) $s->id] = 'Venue Restriction (B005 no-Diploma)';
                }
            }
        }

        return $out;
    }

    /**
     * Semester-wide conflicted candidates (user directive 2026-10-16, apply
     * round 4): the venue modal's pool is WEEK-AGNOSTIC — a W14-conflicted
     * class may be arranged at any week past the lead time (e.g. W6), so the
     * pool comes from the whole semester, never just the viewed week.
     * Flattens the conflict map: session id => human reason + sorted weeks.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array{reason: string, weeks: list<int>}>
     */
    public function conflictedFor(Collection $sessions): array
    {
        $out = [];
        foreach ($this->conflictMapFor($sessions) as $week => $reasons) {
            foreach ($reasons as $sessionId => $reason) {
                if (! isset($out[$sessionId])) {
                    $out[$sessionId] = ['reason' => $reason, 'weeks' => []];
                }
                $out[$sessionId]['weeks'][] = (int) $week;
            }
        }
        foreach ($out as &$row) {
            $row['weeks'] = array_values(array_unique($row['weeks']));
            sort($row['weeks']);
        }
        unset($row);

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
        $weeks = $semester !== null ? $semester->week_count : 14;
        if ($week1 > $weeks) {
            return null;
        }

        $start = Carbon::parse($semester !== null ? $semester->start_date : now()->toDateString());
        $monday = $start->copy()->addWeeks($week1 - 1);

        return ['monday' => $monday, 'week1' => $week1];
    }

    /**
     * Spaced-class cohort convention identical to the legacy payloads
     * (module 'cohorts' arrays and the client's B005 pattern input).
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
