<?php

namespace App\Livewire;

use App\Actions\SubmitReplacementRequest;
use App\Concerns\ResolvesTimetableTimeline;
use App\Http\Requests\StoreReplacementRequest;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Services\MatrixIntersectionEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * Replacement Arrangement — 100% the frozen UI design (the mock template's
 * subject/slot pickers, venue dropdown, week navigator, grid, selection
 * summary, confirm modal, keyboard help and lead-time rule all run
 * untouched). This component is the DATA BRIDGE plus the real submit:
 *
 * - `MockData.venueSlots` ← real per-week venue slot states (1 occupied,
 *   3 pending-mine, 4 reserved-by-others — the mock's exact triple shape,
 *   week-keyed because real slot states are week-specific; the mock's
 *   single-semester map becomes a [week][venue] map with a one-line
 *   cellRender lookup change, documented in the view).
 * - `MockData.cohortTimetable.events` ← real conflict/cancelled occurrences
 *   (Public Holiday / Venue Restriction / FR 2.16 exceptions), so the mock's
 *   extractSlotsForSubject slot picker works unchanged.
 * - `MockData.courses` / `venues` / `semester` / `holidays` ← real rows.
 * - `busyByWeek` / `blockedVenues` ← the engine's busy vectors per subject
 *   (lecturer + cohort classes, minus that week's class_exceptions) and the
 *   venues ineligible for the session's type/headcount (engine Vector 4) —
 *   the frozen SDD's D4 rule 1 ("excluded by engine busy vectors renders
 *   occupied") made visible so the grid never offers a cell the FR 4.3–4.7
 *   pre-check would refuse (the mock never needed it: its data had no real
 *   conflicts).
 * - submitBlocks() ← the mock's proceed() confirm callback now posts the
 *   selected blocks through StoreReplacementRequest rules (D8) +
 *   SubmitReplacementRequest (engine pre-check → OCC reserve → D5); results
 *   return per block so the mock's own toast/modal vocabulary reports
 *   success and FR 4.10 conflicts.
 *
 * Legacy mock template untouched for the D10 fallback.
 */
class ReplacementArrangement extends Component
{
    use ResolvesTimetableTimeline;

    private ?MatrixIntersectionEngine $engineInstance = null;

    /**
     * The grid's D4-rule-1 rendering reuses the engine's exact busy vectors —
     * no duplicated conflict semantics. (Lazy resolution: Livewire's factory
     * news components directly, bypassing constructor injection.)
     */
    private function engine(): MatrixIntersectionEngine
    {
        return $this->engineInstance ??= app(MatrixIntersectionEngine::class);
    }

    public function render(): View
    {
        return view('livewire.replacement-arrangement', [
            'payload' => $this->pagePayload(),
        ])
            ->extends('layouts.ui-template', [
                'homeUrl' => '/my-timetable-ui',
                'activeNav' => 'replacement-arrangement',
                'hideNav' => true,
                'pageKey' => 'replacementArrangement',
            ])
            ->section('content');
    }

    /**
     * The mock's data shapes, derived from the real DB.
     *
     * @return array<string, mixed>
     */
    private function pagePayload(): array
    {
        $semester = $this->timelineSemester();
        $semesterId = $semester !== null ? $semester->id : 1;
        $weeks = $this->timelineWeeks();

        $sessions = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme', 'lecturer'])
            ->where('lecturer_id', auth()->id())
            ->where('semester_id', $semesterId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return [
            'semester' => $this->semesterJs(),
            'holidays' => $this->holidaysForJs(),
            'currentUser' => ['name' => (string) auth()->user()->name],
            'venues' => $this->venuesPayload(),
            'courses' => $this->coursesPayload($sessions),
            'conflictEvents' => $this->conflictEventsPayload($sessions),
            'venueSlotsByWeek' => $this->venueSlotsByWeekPayload($semesterId, $weeks),
            'busyByWeek' => $this->busyByWeekPayload($sessions, $semesterId, $weeks),
            'blockedVenues' => $this->blockedVenuesPayload($sessions),
        ];
    }

    /**
     * All venues in the mock's VenueDropdown shape. Room types map to the
     * dropdown's vocabulary; 'Lab' covers lab/cisco_lab (the view's one-line
     * venue-filter patch admits them).
     *
     * @return array<int, array<string, mixed>>
     */
    private function venuesPayload(): array
    {
        return Venue::query()
            ->orderBy('room_code')
            ->get()
            ->map(fn (Venue $v): array => [
                'code' => (string) $v->room_code,
                'name' => (string) $v->room_name,
                'type' => match ((string) $v->room_type) {
                    'lecture_hall' => 'LectureHall',
                    'lab', 'cisco_lab' => 'Lab',
                    default => 'Tutorial',
                },
                'capacity' => (int) $v->capacity,
            ])
            ->all();
    }

    /**
     * The lecturer's subjects in the mock's course shape (the client's
     * extractSlotsForSubject filter decides which appear in the dropdown).
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array<string, mixed>>
     */
    private function coursesPayload(Collection $sessions): array
    {
        return $sessions
            ->groupBy('module_id')
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'code' => (string) $first->module->module_code,
                    'name' => (string) $first->module->module_name,
                    'type' => (string) $first->session_type,
                    'cohorts' => $group->flatMap(fn (ClassSession $s) => $s->cohorts)
                        ->unique('id')
                        ->values()
                        ->map(fn ($c): string => $this->spacedCohortLabel($c))
                        ->all(),
                    'cohortCounts' => $group->flatMap(fn (ClassSession $s) => $s->cohorts)
                        ->unique('id')
                        ->values()
                        ->map(fn ($c): int => (int) $c->student_count)
                        ->all(),
                    'studentCount' => (int) $group->flatMap(fn (ClassSession $s) => $s->cohorts)
                        ->unique('id')
                        ->sum('student_count'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Conflict/cancelled occurrences in the mock's cohortTimetable.events
     * shape ({week, event}) — the slot picker's exact data source.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array<string, mixed>>
     */
    private function conflictEventsPayload(Collection $sessions): array
    {
        $semester = $this->timelineSemester();
        $startDate = Carbon::parse($semester !== null ? $semester->start_date : now()->toDateString());
        $weeks = $this->timelineWeeks();

        $exceptions = ClassException::query()
            ->whereIn('class_session_id', $sessions->pluck('id'))
            ->get()
            ->keyBy(fn (ClassException $e): string => $e->class_session_id.':'.$e->week_number);

        $holidays = collect($this->holidaysForJs())
            ->keyBy(fn (array $h): string => $h['week'].':'.$h['dayIndex']);

        $events = [];
        foreach ($sessions as $session) {
            $venueRestricted = $this->venueRestrictionConflict($session);

            for ($week = 1; $week <= $weeks; $week++) {
                $exception = $exceptions->get($session->id.':'.$week);
                $holiday = $holidays->get($week.':'.$session->day_of_week);

                if ($exception !== null) {
                    $status = 'cancelled';
                } elseif ($holiday !== null || $venueRestricted) {
                    $status = 'conflict';
                } else {
                    continue;
                }

                $events[] = [
                    'week' => $week - 1, // the mock's weekData index (0-based)
                    'event' => [
                        'di' => (int) $session->day_of_week,
                        'start' => $this->slotIndex((string) $session->start_time),
                        'end' => $this->slotIndex((string) $session->end_time) - 1, // inclusive last half-hour cell
                        'venue' => $session->venue !== null ? (string) $session->venue->room_code : '',
                        'status' => $status,
                        'name' => (string) $session->module->module_name,
                        'type' => (string) $session->session_type,
                        'code' => (string) $session->module->module_code,
                    ],
                ];
            }
        }

        return $events;
    }

    /**
     * Real venue slot states, week-keyed: [weekIdx][venueCode] = [[day, hourIdx,
     * status], …] — 1 occupied, 3 pending (mine), 4 reserved (others').
     *
     * @return array<int|string, array<int|string, list<array{int, int, int}>>> The
     *                                                                          mock's map gains the week dimension; the view's cellRender lookup is the
     *                                                                          one documented line that reads it. Availability stays client-side (the
     *                                                                          mock's model: green = venue-free); the server re-validates lecturer +
     *                                                                          cohort freedom at submit (engine pre-check → FR 4.10 conflict alert).
     */
    private function venueSlotsByWeekPayload(int $semesterId, int $weeks): array
    {
        $slots = TimeSlot::query()
            ->where('semester_id', $semesterId)
            ->whereIn('week_number', range(1, $weeks))
            ->whereIn('status', ['occupied', 'pending'])
            ->get();

        $proposers = ReplacementRequest::query()
            ->where('status', 'pending')
            ->whereIn('replacement_time_slot_id', $slots->modelKeys())
            ->pluck('proposer_id', 'replacement_time_slot_id');

        $myId = (int) auth()->id();

        $out = [];
        foreach ($slots as $slot) {
            $status = $slot->status === 'occupied'
                ? 1
                : ((int) ($proposers->get($slot->id)) === $myId ? 3 : 4);

            $out[(int) $slot->week_number - 1][(string) $slot->venue->room_code][] = [
                (int) $slot->day_of_week,
                $this->slotIndex((string) $slot->start_time),
                $status,
            ];
        }

        return $out;
    }

    /**
     * One representative session per subject — the one whose engine vectors
     * the grid must honour. The URL handoff (code/day/start/venue) identifies
     * the exact session with the same resolution submitBlocks uses; other
     * subjects fall back to their first session of the week.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<string, ClassSession>
     */
    private function sessionsByCode(Collection $sessions): array
    {
        $urlCode = trim((string) request()->query('code', ''));
        $orig = json_encode([
            'day' => request()->query('day'),
            'start' => request()->query('start'),
            'venue' => request()->query('originalVenue', request()->query('venue')),
        ]) ?: '{}';

        $out = [];
        foreach ($sessions->groupBy(fn (ClassSession $s): string => (string) $s->module->module_code) as $code => $group) {
            $code = (string) $code;

            $out[$code] = $code === $urlCode && request()->query('day') !== null
                ? ($this->resolveSession($code, $orig) ?? $group->first())
                : $group->first();
        }

        return $out;
    }

    /**
     * D4 rule 1 — the engine's busy vectors as half-hour ranges, per subject:
     * [code][weekIdx] = [[day, startIdx, endIdxExclusive], …]. The '' key is
     * the lecturer's own freedom (course-independent — any submission needs
     * the lecturer free); each subject key adds its cohorts' classes.
     * class_exceptions for the evaluated week are excluded by the engine, so
     * a cancelled class frees its cells (the replacement may reuse them).
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<string, array<int, list<array{int, int, int}>>>
     */
    private function busyByWeekPayload(Collection $sessions, int $semesterId, int $weeks): array
    {
        $lecturerId = (int) auth()->id();

        $toRanges = function (array $busy): array {
            $out = [];
            foreach ($busy as $range) {
                $out[] = [
                    (int) $range['day'],
                    $this->slotIndex((string) $range['start']),
                    $this->slotIndex((string) $range['end']),
                ];
            }

            return $out;
        };

        $lecturerBusy = [];
        for ($w = 1; $w <= $weeks; $w++) {
            $lecturerBusy[$w - 1] = $toRanges($this->engine()->lecturerBusyRanges($semesterId, $lecturerId, $w));
        }

        $cohortBusyCache = [];
        $out = ['' => $lecturerBusy];

        foreach ($this->sessionsByCode($sessions) as $code => $session) {
            $cohortIds = $session->cohorts->map(fn (Cohort $c): int => (int) $c->id)->sort()->values()->all();
            $hash = md5((string) json_encode($cohortIds));

            if (! isset($cohortBusyCache[$hash])) {
                $cohortBusyCache[$hash] = [];
                for ($w = 1; $w <= $weeks; $w++) {
                    $cohortBusyCache[$hash][$w - 1] = $toRanges($this->engine()->cohortBusyRanges($semesterId, $cohortIds, $w));
                }
            }

            $merged = [];
            for ($w = 1; $w <= $weeks; $w++) {
                $merged[$w - 1] = array_values(array_unique(
                    array_merge($lecturerBusy[$w - 1], $cohortBusyCache[$hash][$w - 1]),
                    SORT_REGULAR,
                ));
            }

            $out[$code] = $merged;
        }

        return $out;
    }

    /**
     * D4 rule 1 (venue eligibility — engine Vector 4): venues that cannot host
     * the subject's resolved session (session type not allowed, or capacity
     * below the cohorts' headcount) — the grid renders their cells occupied.
     * The '' key blocks nothing: before a subject is picked there is no type
     * or headcount to test against.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<string, array<int, string>>
     */
    private function blockedVenuesPayload(Collection $sessions): array
    {
        $roomCodes = Venue::query()->orderBy('room_code')->pluck('room_code')
            ->map(fn ($code): string => (string) $code);
        $all = $roomCodes->all();

        $out = ['' => []];
        foreach ($this->sessionsByCode($sessions) as $code => $session) {
            $cohortIds = $session->cohorts->map(fn (Cohort $c): int => (int) $c->id)->all();
            $eligible = $this->engine()->eligibleVenues(
                $this->engine()->requiredHeadcount($cohortIds),
                (string) $session->session_type,
            );

            $out[$code] = $roomCodes
                ->reject(fn (string $roomCode): bool => in_array($roomCode, array_values($eligible), true))
                ->values()
                ->all();
        }

        return $out;
    }

    /**
     * The mock's proceed() confirm now posts each selected block through the
     * real pipeline. Blocks arrive as {venue, week (0-based JS), day,
     * startHour, endHour}; each becomes one pending request for that week's
     * occurrence (FR 2.9) with the D8 rules, engine pre-check, OCC reserve
     * and D5 compensation inside SubmitReplacementRequest. Returns per-block
     * results in input order so the page's own toast/modal vocabulary can
     * report successes and FR 4.10 conflicts.
     *
     * @return array<int, array{ok: bool, reason: string|null}>
     */
    public function submitBlocks(string $blocksJson, string $code = '', string $origJson = '{}'): array
    {
        $session = $this->resolveSession($code, $origJson);
        if ($session === null) {
            return [['ok' => false, 'reason' => 'Class not found among your classes.']];
        }

        $blocks = json_decode($blocksJson, true);
        if (! is_array($blocks) || $blocks === []) {
            return [['ok' => false, 'reason' => 'No selection to submit.']];
        }

        $semester = $this->timelineSemester();
        $semesterId = $semester !== null ? $semester->id : 1;

        $action = app(SubmitReplacementRequest::class);
        $results = [];

        foreach ($blocks as $block) {
            $week = (int) ($block['week'] ?? -1) + 1; // JS weekData index → DB week number
            $day = (int) ($block['day'] ?? -1);
            $startHour = (int) ($block['startHour'] ?? -1);
            $venue = Venue::query()->where('room_code', (string) ($block['venue'] ?? ''))->first();

            if ($week < 1 || $week > $this->timelineWeeks() || $day < 0 || $startHour < 0 || $venue === null) {
                $results[] = ['ok' => false, 'reason' => 'Invalid selection payload.'];

                continue;
            }

            $startTime = Carbon::parse('08:00:00')->addMinutes($startHour * 30)->format('H:i:s');
            $slot = TimeSlot::query()
                ->where('semester_id', $semesterId)
                ->where('week_number', $week)
                ->where('venue_id', $venue->id)
                ->where('day_of_week', $day)
                ->where('start_time', $startTime)
                ->first();

            if ($slot === null) {
                $results[] = ['ok' => false, 'reason' => 'That slot does not exist in the booking grid.'];

                continue;
            }

            $validator = Validator::make([
                'class_session_id' => $session->id,
                'week_number' => $week,
                'replacement_time_slot_id' => (int) $slot->id,
                'remarks' => null,
            ], StoreReplacementRequest::rules());

            if ($validator->fails()) {
                $results[] = ['ok' => false, 'reason' => (string) $validator->errors()->first()];

                continue;
            }

            $result = $action(auth()->user(), $session, $week, (int) $slot->id, null);

            $results[] = [
                'ok' => (bool) $result['ok'],
                'reason' => $result['ok'] ? null : $this->conflictText((string) $result['conflict_reason']),
            ];
        }

        return $results;
    }

    /**
     * Ownership-scoped session resolution (FR 2.11): module code + the
     * original-slot pick (day/start/venue) identify the exact class; only the
     * owner's sessions can ever match.
     */
    private function resolveSession(string $code, string $origJson): ?ClassSession
    {
        $semester = $this->timelineSemester();

        $query = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->where('semester_id', $semester !== null ? $semester->id : 1);

        if (trim($code) !== '') {
            $query->whereHas('module', fn ($m) => $m->where('module_code', trim($code)));
        }

        $orig = json_decode($origJson, true);
        if (is_array($orig)) {
            if (isset($orig['day']) && $orig['day'] !== '') {
                $query->where('day_of_week', (int) $orig['day']);
            }
            if (isset($orig['start']) && $orig['start'] !== '') {
                $candidates = $query->orderBy('day_of_week')->orderBy('start_time')->get();
                $startIdx = (int) $orig['start'];

                return $candidates->first(
                    fn (ClassSession $s): bool => $this->slotIndex((string) $s->start_time) === $startIdx
                        && (! isset($orig['venue']) || $orig['venue'] === ''
                            || ($s->venue !== null && $s->venue->room_code === (string) $orig['venue'])),
                );
            }
        }

        return $query->orderBy('day_of_week')->orderBy('start_time')->first();
    }

    private function conflictText(string $reason): string
    {
        return match ($reason) {
            'slot_not_available', 'slot_not_found' => 'That slot was just taken.',
            'engine_precheck_failed' => 'You or the cohort is no longer free at that time.',
            'reserve_interrupted' => 'The booking could not be completed — try again.',
            default => 'Booking conflict — refresh and try again.',
        };
    }

    /**
     * The mock's spaced cohort convention ('DFT2 (S1) G1').
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
