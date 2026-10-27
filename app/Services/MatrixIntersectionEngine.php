<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\Venue;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MatrixIntersectionEngine
{
    private const VALID_SESSION_TYPES = ['L', 'T', 'P'];

    private const MIN_WEEK = 1;

    private const MAX_WEEK = 14;

    private const MIN_DURATION = 30;

    private const DURATION_STEP = 30;

    /**
     * SDD venue-restrictions-enforcement: the headcount+type PREVIEW flavor —
     * its API readers treat this as an audit surface; the AUTHORITATIVE
     * session-scoped admission is `sessionVenueEligible()` (module-
     * conditioned, via `validateSlot(..., $session)`).
     *
     * @param  array<int, int>  $cohortIds
     * @return array<int, array{
     *   day: int,
     *   start_time: string,
     *   end_time: string,
     *   venue_id: int,
     *   venue_code: string,
     *   time_slot_ids: array<int, int>,
     *   run_start: string,
     *   run_end: string,
     * }>
     */
    public function findAvailableSlots(
        int $lecturerId,
        array $cohortIds,
        int $semesterId,
        int $weekNumber,
        string $sessionType = 'L',
        int $duration = 60,
        ?int $venueId = null,
    ): array {
        $this->validateSessionType($sessionType);
        $this->validateWeekNumber($weekNumber);
        $this->validateDuration($duration);
        $this->validateCohortIds($cohortIds);

        $lecturerBusy = $this->lecturerBusyRanges($semesterId, $lecturerId, $weekNumber);
        $cohortBusy = $this->cohortBusyRanges($semesterId, $cohortIds, $weekNumber);

        $headcount = $this->requiredHeadcount($cohortIds);
        $venues = $this->eligibleVenues($headcount, $sessionType, $venueId);

        $holidayDays = $this->holidayDays($semesterId, $weekNumber);
        $base = $this->baseCells($semesterId, $weekNumber, array_keys($venues), $venueId, $holidayDays);

        $busyByDay = [];

        foreach (array_merge($lecturerBusy, $cohortBusy) as $range) {
            $busyByDay[$range['day']][] = $range;
        }

        $cellsByVenue = [];

        foreach ($base as $cell) {
            foreach ($busyByDay[$cell['day']] ?? [] as $busy) {
                if ($busy['start'] < $cell['end'] && $busy['end'] > $cell['start']) {
                    continue 2;
                }
            }

            $cellsByVenue[$cell['day'].'|'.$cell['venue_id']][] = $cell;
        }

        $results = [];

        foreach ($cellsByVenue as $cells) {
            $runCells = [];

            foreach ($cells as $cell) {
                $previous = end($runCells);

                if ($previous !== false && $cell['start'] !== $this->addMinutes($previous['start'], 30)) {
                    $this->collectRun($results, $runCells, $duration);

                    $runCells = [];
                }

                $runCells[] = $cell;
            }

            $this->collectRun($results, $runCells, $duration);
        }

        usort(
            $results,
            fn (array $left, array $right): int => [$left['day'], $left['start_time'], $left['venue_code']]
                <=> [$right['day'], $right['start_time'], $right['venue_code']],
        );

        return $results;
    }

    /**
     * Busy ranges (lecturer + cohorts) for one week — read-only view for UI
     * cell-state derivation (D4 rule 1: "excluded by engine busy vectors" renders
     * occupied). Same semantics as findAvailableSlots; no behaviour change.
     *
     * @param  array<int, int>  $cohortIds
     * @return array<int, array{day: int, start: string, end: string}>
     */
    public function busyRanges(int $lecturerId, array $cohortIds, int $semesterId, int $weekNumber): array
    {
        return array_merge(
            $this->lecturerBusyRanges($semesterId, $lecturerId, $weekNumber),
            $this->cohortBusyRanges($semesterId, $cohortIds, $weekNumber),
        );
    }

    /**
     * Append a maximal contiguous run of cells to $results if it satisfies $duration.
     *
     * @param  array<int, array{id: int, day: int, start: string, end: string, venue_id: int, venue_code: string}>  $cells
     * @param  array<int, array{day: int, start_time: string, end_time: string, venue_id: int, venue_code: string, time_slot_ids: array<int, int>, run_start: string, run_end: string}>  $results
     */
    private function collectRun(array &$results, array $cells, int $duration): void
    {
        if ($cells === [] || count($cells) * 30 < $duration) {
            return;
        }

        $first = $cells[0];
        $last = $cells[count($cells) - 1];

        $results[] = [
            'day' => $first['day'],
            'start_time' => $first['start'],
            'end_time' => $this->addMinutes($first['start'], $duration),
            'venue_id' => $first['venue_id'],
            'venue_code' => $first['venue_code'],
            'time_slot_ids' => array_column($cells, 'id'),
            'run_start' => $first['start'],
            'run_end' => $last['end'],
        ];
    }

    /**
     * Add $minutes to an H:i:s time string, returning a new H:i:s string.
     */
    private function addMinutes(string $time, int $minutes): string
    {
        [$hours, $mins] = array_map('intval', explode(':', $time));

        $total = $hours * 60 + $mins + $minutes;

        return sprintf('%02d:%02d:00', intdiv($total, 60), $total % 60);
    }

    /**
     * Validate a single time-slot cell against the same vectors as
     * findAvailableSlots(). The slot's own week/semester is the evaluation
     * context; $weekNumber must match the slot's week. Throws
     * ModelNotFoundException when the slot does not exist.
     *
     * Full-block extension (SDD full-block-reservation, design D4): $span
     * evaluates the SAME vectors for EVERY slot in the plan
     * (anchor..anchor+span−1, 30-min steps, same day — the resolve step
     * guarantees the run fits the grid, so no separate day-end check here).
     * The default (span = 1) keeps every existing caller byte-identical.
     *
     * @param  array<int, int>  $cohortIds
     */
    public function validateSlot(
        int $timeSlotId,
        int $lecturerId,
        array $cohortIds,
        string $sessionType,
        int $weekNumber,
        int $span = 1,
        ?ClassSession $session = null,
    ): bool {
        $this->validateSessionType($sessionType);
        $this->validateWeekNumber($weekNumber);
        $this->validateCohortIds($cohortIds);

        $anchor = TimeSlot::findOrFail($timeSlotId);

        if ($anchor->week_number !== $weekNumber) {
            return false;
        }

        if ($anchor->status !== 'available') {
            return false;
        }

        if (in_array($anchor->day_of_week, $this->holidayDays($anchor->semester_id, $anchor->week_number), true)) {
            return false;
        }

        $lecturerBusy = $this->lecturerBusyRanges($anchor->semester_id, $lecturerId, $anchor->week_number);
        $cohortBusy = $this->cohortBusyRanges($anchor->semester_id, $cohortIds, $anchor->week_number);
        $headcount = $this->requiredHeadcount($cohortIds);
        /* SDD venue-restrictions-enforcement: with the submitting session in
           hand, the venue admission is the ONE matrix read (module-conditional
           labs / the B006 widening / R6 population / R2 — design D3); WITHOUT
           it the historic headcount+type read stands byte-identical. */
        $venueOk = $session !== null
            ? $this->sessionVenueEligible($session, Venue::query()->findOrFail($anchor->venue_id))
            : $this->eligibleVenues($headcount, $sessionType, $anchor->venue_id) !== [];

        for ($k = 0; $k < $span; $k++) {
            // Anchor on k = 0 keeps the original semantics byte-identical; the
            // k > 0 cells are virtual (same resolve shape the OCC locks: 30-min
            // steps from the anchor's own grid row).
            $slot = $k === 0
                ? $anchor
                : new TimeSlot([
                    'semester_id' => $anchor->semester_id,
                    'week_number' => $anchor->week_number,
                    'day_of_week' => $anchor->day_of_week,
                    'start_time' => $this->addMinutes((string) $anchor->start_time, $k * 30),
                    'end_time' => $this->addMinutes((string) $anchor->end_time, $k * 30),
                    'status' => 'available',
                    'venue_id' => $anchor->venue_id,
                ]);

            foreach ($lecturerBusy as $busy) {
                if ($this->overlaps($busy, $slot)) {
                    return false;
                }
            }

            foreach ($cohortBusy as $busy) {
                if ($this->overlaps($busy, $slot)) {
                    return false;
                }
            }
        }

        return $venueOk;
    }

    /**
     * @param  array{day: int, start: string, end: string}  $busy
     */
    private function overlaps(array $busy, TimeSlot $slot): bool
    {
        return $busy['day'] === $slot->day_of_week
            && $busy['start'] < $slot->end_time
            && $busy['end'] > $slot->start_time;
    }

    private function validateSessionType(string $sessionType): void
    {
        if (! in_array($sessionType, self::VALID_SESSION_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid session type [{$sessionType}]. Expected one of: L, T, P."
            );
        }
    }

    private function validateWeekNumber(int $weekNumber): void
    {
        if ($weekNumber < self::MIN_WEEK || $weekNumber > self::MAX_WEEK) {
            throw new InvalidArgumentException(
                "Invalid week number [{$weekNumber}]. Expected a value between 1 and 14."
            );
        }
    }

    private function validateDuration(int $duration): void
    {
        if ($duration < self::MIN_DURATION || $duration % self::DURATION_STEP !== 0) {
            throw new InvalidArgumentException(
                "Invalid duration [{$duration}]. Expected a multiple of 30, at least 30 minutes."
            );
        }
    }

    /**
     * @param  array<int, int>  $cohortIds
     */
    private function validateCohortIds(array $cohortIds): void
    {
        if ($cohortIds === []) {
            throw new InvalidArgumentException('The cohortIds array must not be empty.');
        }
    }

    /**
     * Busy ranges for a lecturer in a given week, minus that week's class exceptions.
     * Public: also feeds the arrangement grid's D4-rule-1 occupied rendering.
     *
     * @return array<int, array{day: int, start: string, end: string}>
     */
    public function lecturerBusyRanges(int $semesterId, int $lecturerId, int $weekNumber): array
    {
        return ClassSession::query()
            ->where('semester_id', $semesterId)
            ->where('lecturer_id', $lecturerId)
            ->whereNotIn('id', $this->exceptionIdsInWeek($weekNumber))
            ->get(['day_of_week', 'start_time', 'end_time'])
            ->map(fn (ClassSession $session): array => [
                'day' => $session->day_of_week,
                'start' => $session->start_time,
                'end' => $session->end_time,
            ])
            ->all();
    }

    /**
     * Union of busy ranges across target cohorts (overlapping ranges among cohorts count once).
     * Public: also feeds the arrangement grid's D4-rule-1 occupied rendering.
     *
     * @param  array<int, int>  $cohortIds
     * @return array<int, array{day: int, start: string, end: string}>
     */
    public function cohortBusyRanges(int $semesterId, array $cohortIds, int $weekNumber): array
    {
        return ClassSession::query()
            ->join('session_cohorts as sc', 'sc.class_session_id', '=', 'class_sessions.id')
            ->where('class_sessions.semester_id', $semesterId)
            ->whereIn('sc.cohort_id', $cohortIds)
            ->whereNotIn('class_sessions.id', $this->exceptionIdsInWeek($weekNumber))
            ->groupBy('class_sessions.id')
            ->get(['class_sessions.id', 'class_sessions.day_of_week', 'class_sessions.start_time', 'class_sessions.end_time'])
            ->map(fn (ClassSession $session): array => [
                'day' => $session->day_of_week,
                'start' => $session->start_time,
                'end' => $session->end_time,
            ])
            ->all();
    }

    /**
     * Sum of students across the target cohorts (multi-cohort sessions share one room).
     * Reads the denormalized cohorts.student_count when populated; falls back to a live
     * Student count for cohorts predating the column.
     *
     * @param  array<int, int>  $cohortIds
     */
    public function requiredHeadcount(array $cohortIds): int
    {
        if ($cohortIds === []) {
            return 0;
        }

        $counts = Cohort::query()
            ->whereIn('id', $cohortIds)
            ->pluck('student_count', 'id');

        if ($counts->contains(fn ($count): bool => $count === null)) {
            return (int) Student::query()->whereIn('cohort_id', $cohortIds)->count();
        }

        return (int) $counts->sum();
    }

    /**
     * SDD venue-restrictions-enforcement (design D3): the SESSION-scoped
     * venue admission — capacity vs the session's own headcount AND the ONE
     * matrix read (`LectureSessionCatalog::sessionAllowedForVenue`). The
     * authoritative OCC-time flavor (`validateSlot` + the blocked-venues
     * payload consume it); `findAvailableSlots` keeps the headcount+type
     * preview contract (its API readers treat it as an audit surface).
     */
    public function sessionVenueEligible(ClassSession $session, Venue $venue): bool
    {
        $cohortIds = $session->cohorts->map(fn (Cohort $c): int => (int) $c->id)->all();

        return $venue->capacity >= $this->requiredHeadcount($cohortIds)
            && app(LectureSessionCatalog::class)->sessionAllowedForVenue(
                (string) $session->session_type,
                (string) $venue->room_type,
                $session,
                (string) $venue->room_code,
            );
    }

    /**
     * Venues that satisfy Vector 4 (SDD lt-venue-capacity-rule D2): the
     * CANONICAL room_type rule map admits the session type AND capacity
     * >= headcount. The venues.allowed_session_types CSV column no longer
     * gates booking — it stays reference inventory (ApiReadController's
     * direct audit read is untouched). A provided $venueId is FILTERED TO,
     * not trusted — it must still pass both checks.
     *
     * @return array<int, string> venue id => room_code
     */
    public function eligibleVenues(int $headcount, string $sessionType, ?int $venueId = null): array
    {
        $query = Venue::query();

        if ($venueId !== null) {
            $query->whereKey($venueId);
        }

        $mapRow = app(LectureSessionCatalog::class)->ruleMap()[$sessionType] ?? [];
        $allowedRoomTypes = is_array($mapRow) ? $mapRow : [];   // the rule-map values are type rows (the repealed b005_* string keys are gone)

        return $query
            ->get(['id', 'room_code', 'capacity', 'room_type'])
            ->filter(fn (Venue $venue): bool => $venue->capacity >= $headcount
                && in_array($venue->room_type, $allowedRoomTypes, true))
            ->mapWithKeys(fn (Venue $venue): array => [$venue->id => $venue->room_code])
            ->all();
    }

    /**
     * @return array<int, int> list of day_of_week values (0..5) that are global holidays for the week
     */
    private function holidayDays(int $semesterId, int $weekNumber): array
    {
        return array_map(
            'intval',
            DB::table('holidays')
                ->where('semester_id', $semesterId)
                ->where('week_number', $weekNumber)
                ->pluck('day_of_week')
                ->all(),
        );
    }

    /**
     * The materialized grid base set: available cells in eligible venues, minus holiday days.
     *
     * @param  array<int, int>  $venueIds
     * @param  array<int, int>  $holidayDays
     * @return array<int, array{id: int, day: int, start: string, end: string, venue_id: int, venue_code: string}>
     */
    private function baseCells(int $semesterId, int $weekNumber, array $venueIds, ?int $venueId, array $holidayDays): array
    {
        $query = TimeSlot::query()
            ->join('venues', 'venues.id', '=', 'time_slots.venue_id')
            ->where('time_slots.semester_id', $semesterId)
            ->where('time_slots.week_number', $weekNumber)
            ->where('time_slots.status', 'available')
            ->whereIn('time_slots.venue_id', $venueIds)
            ->orderBy('time_slots.day_of_week')
            ->orderBy('time_slots.start_time')
            ->orderBy('venues.room_code');

        if ($holidayDays !== []) {
            $query->whereNotIn('time_slots.day_of_week', $holidayDays);
        }

        if ($venueId !== null) {
            $query->where('time_slots.venue_id', $venueId);
        }

        return $query
            ->get([
                'time_slots.id',
                'time_slots.day_of_week',
                'time_slots.start_time',
                'time_slots.end_time',
                'time_slots.venue_id',
                'venues.room_code',
            ])
            ->map(fn (TimeSlot $slot): array => [
                'id' => $slot->id,
                'day' => $slot->day_of_week,
                'start' => $slot->start_time,
                'end' => $slot->end_time,
                'venue_id' => $slot->venue_id,
                'venue_code' => (string) $slot->getAttribute('room_code'),
            ])
            ->all();
    }

    /**
     * Subquery of class session ids cancelled by a class exception in the given week.
     */
    private function exceptionIdsInWeek(int $weekNumber): \Closure
    {
        return function (Builder $query) use ($weekNumber): void {
            $query->select('class_session_id')
                ->from('class_exceptions')
                ->where('week_number', $weekNumber);
        };
    }
}
