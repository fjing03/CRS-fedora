<?php

namespace App\Livewire;

use App\Actions\SubmitReplacementRequest;
use App\Concerns\ResolvesTimetableTimeline;
use App\Http\Requests\StoreReplacementRequest;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Holiday;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Services\LectureSessionCatalog;
use App\Services\MatrixIntersectionEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Renderless;
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
 *   (Public Holiday / FR 2.16 exceptions; the Venue Restriction arm was
 *   REPEALED 2026-10-27), so the mock's
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
            'ruleMap' => LectureSessionCatalog::ruleMap(),
            'courses' => $this->coursesPayload($sessions),
            'conflictEvents' => $this->conflictEventsPayload($sessions),
            'venueSlotsByWeek' => $this->venueSlotsByWeekPayload($semesterId, $weeks),
            'busyByWeek' => $this->busyByWeekPayload($sessions, $semesterId, $weeks),
            'blockedVenues' => $this->blockedVenuesPayload($sessions),
            'pinnedSession' => $this->pinnedSessionPayload($weeks),
            /* arrange-intent-session-choice (user amendment, conflicted-only):
               for a hand-typed intent (no ?session=), the fallback must pick a
               class the system flagged for that week — same definition as the
               replacement-home list (exception / holiday / venue restriction),
               minus occurrences already under an active request (D1). */
            'intentConflicts' => $this->intentConflictsPayload(),
        ];
    }

    /**
     * SEMESTER-WIDE conflicted candidates of the logged-in lecturer
     * (session id => reason), derived only when a date param exists —
     * user directive 2026-10-16 (apply round 4): a conflicted class may be
     * arranged at ANY week past the lead time, so the fallback's candidates
     * come from the whole semester (service-defined conflict parity —
     * exception / holiday / venue restriction / active requests), NOT just
     * the intent week. Kept in lockstep with the producer modal's
     * week-agnostic pool via LectureSessionCatalog::conflictedFor().
     *
     * @return array<int, string>
     */
    private function intentConflictsPayload(): array
    {
        $dateQuery = request()->query('date');
        if (! $dateQuery) {
            return [];
        }

        try {
            Carbon::parse((string) $dateQuery);
        } catch (\Throwable) {
            return [];
        }

        $semester = $this->timelineSemester();
        if ($semester === null) {
            return [];
        }

        $sessions = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme'])
            ->where('lecturer_id', auth()->id())
            ->where('semester_id', $semester->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        $out = [];
        foreach (app(LectureSessionCatalog::class)->conflictedFor($sessions) as $sessionId => $facts) {
            $out[$sessionId] = $facts['reason'];
        }

        return $out;
    }

    /**
     * The handoff pins the EXACT occurrence being replaced — the backend's own
     * identity (class_session_id + week_number, the D1 pair). The server
     * resolves + ownership-checks it (FR 2.11: a foreign or fabricated id
     * resolves to null, never to someone else's class) and emits its facts so
     * the client hydrates the same urlParams the legacy handoffs used.
     *
     * @return array{id: int, code: string, day: int, start: int, venue: string, duration: float, week: int}|null
     */
    private function pinnedSessionPayload(int $weeks): ?array
    {
        $sessionId = (int) request()->query('session', '');
        $week = (int) request()->query('week', '');
        if ($sessionId < 1 || $week < 1 || $week > $weeks) {
            return null;
        }

        $session = ClassSession::query()
            ->with(['module', 'venue'])
            ->where('lecturer_id', auth()->id())
            ->find($sessionId);
        if ($session === null) {
            return null;
        }

        return [
            'id' => $session->id,
            'code' => (string) $session->module->module_code,
            'day' => (int) $session->day_of_week,
            'start' => $this->slotIndex((string) $session->start_time),
            'venue' => $session->venue !== null ? (string) $session->venue->room_code : '',
            'duration' => round(Carbon::parse($session->start_time)->diffInMinutes(Carbon::parse($session->end_time)) / 60, 2),
            'week' => $week,
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
                // mock-statics-die (G1(b)): the server-stamped SHORT picker
                // label (the label/tooltip law splits — the client no longer
                // consults the dead venueRealNames mirror).
                'short_name' => self::shortVenueName($v->room_name, (string) $v->room_code),
                // Raw DB encoding rides along (D6 honest inventory): the
                // client dd filter + the fallback eligibility checks read
                // ruleMap[] over room_type (SDD lt-venue-capacity-rule; the
                // CSV column no longer gates).
                'rawRoomType' => (string) $v->room_type,
                // SDD venue-description-doc-b006-rule: the client payload
                // gains the plain description (both display apps).
                'description' => $v->description !== null ? (string) $v->description : '',
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
     * SDD arrange-intent-session-choice (D1): this DELEGATES to the shared
     * `LectureSessionCatalog` — the public shape stays identical (id-keyed
     * `sessions` map preserved; the additive `order` array rides along),
     * enriched with per-session facts (dayOfWeek/startTime/endTime/durationSlots/cohortCodes)
     * the fallback prefill and the producer modal share.
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int, array<string, mixed>>
     */
    private function coursesPayload(Collection $sessions): array
    {
        return app(LectureSessionCatalog::class)->build($sessions, null);
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

        // FULL-BLOCK RESERVATION (design D1, user amendment 2026-10-17): an
        // occurrence already under an ACTIVE request is "already being
        // handled" (ReplacementHome parity) — it must NOT appear in the
        // slot-picker dropdown (its make-up block is on the proposal's own
        // schedule; re-proposing would create a double booking).
        $activeRequests = ReplacementRequest::query()
            ->whereIn('class_session_id', $sessions->pluck('id'))
            ->whereIn('status', ['pending', 'approved'])
            ->get()
            ->keyBy(fn (ReplacementRequest $r): string => $r->class_session_id.':'.$r->week_number);

        $holidays = collect($this->holidaysForJs())
            ->keyBy(fn (array $h): string => $h['week'].':'.$h['dayIndex']);

        $events = [];
        foreach ($sessions as $session) {
            for ($week = 1; $week <= $weeks; $week++) {
                $exception = $exceptions->get($session->id.':'.$week);
                $holiday = $holidays->get($week.':'.$session->day_of_week);

                // D1 parity — the handled occurrence skips the conflicted list.
                if ($activeRequests->has($session->id.':'.$week)) {
                    continue;
                }

                if ($exception !== null) {
                    $status = 'cancelled';
                } elseif ($holiday !== null) {
                    $status = 'conflict';
                } else {
                    continue;
                }

                $events[] = [
                    'week' => $week - 1, // the mock's weekData index (0-based)
                    'event' => [
                        'sessionId' => (int) $session->id, // the D1 pair's other half — the pin follows the picked slot
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

        // Full-block ownership read (design D7 item 2, round-4 🔴1): the
        // anchor-keyed pluck can't see a span's tail slots — derive the
        // owner per slot from the JUNCTION (pending-geometric ONLY; approved
        // and cancelled keep the legacy column semantics elsewhere).
        $proposers = ReplacementRequestSlot::query()
            ->join('replacement_requests', 'replacement_request_slots.replacement_request_id', '=', 'replacement_requests.id')
            ->where('replacement_requests.status', 'pending')
            ->whereIn('replacement_request_slots.time_slot_id', $slots->modelKeys())
            ->pluck('replacement_requests.proposer_id', 'replacement_request_slots.time_slot_id');

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
     * D4 rule 1 — the engine's busy vectors as half-hour ranges, per subject:
     * [code][weekIdx] = [[day, startIdx, endIdxExclusive], …]. The '' key is
     * the lecturer's own freedom (course-independent — any submission needs
     * the lecturer free); each subject key adds its cohorts' classes.
     * class_exceptions for the evaluated week are excluded by the engine, so
     * a cancelled class frees its cells (the replacement may reuse them).
     *
     * @param  Collection<int, ClassSession>  $sessions
     * @return array<int|string, array<int, list<array{int, int, int}>>>
     */
    private function busyByWeekPayload(Collection $sessions, int $semesterId, int $weeks): array
    {
        $lecturerId = (int) auth()->id();

        /* TEMP-HOLDER-LABEL (user, 2026-10-27): the occupied cells carried NO
           holder identity (aria-label="" — the void a misdiagnosis was built
           on: AMIT2034, not the approved-away class, owned the cell). Each
           busy range now rides its holder text as the 4th tuple key; the
           blade attaches it to the cell's data-tip/aria. The 4th key JOINS
           on day:slotStart:slotEnd, so array_unique's dedup survives
           (identical tuples stay identical). REMOVABLE AFTER THE FINAL
           VIVA — delete this block + the blade's tip attach; the first 3
           keys alone are the pre-existing engine contract. */
        $holderLabels = $this->busyCellHolderLabels($semesterId, $weeks);

        $toRanges = function (array $busy, int $weekIdx) use ($holderLabels): array {
            $out = [];
            foreach ($busy as $range) {
                $day = (int) $range['day'];
                $startIdx = $this->slotIndex((string) $range['start']);
                $endIdx = $this->slotIndex((string) $range['end']);
                $out[] = [
                    $day,
                    $startIdx,
                    $endIdx,
                    implode(' | ', array_unique($holderLabels[$weekIdx][$day.':'.$startIdx.':'.$endIdx] ?? [])),
                ];
            }

            return $out;
        };

        $lecturerBusy = [];
        for ($w = 1; $w <= $weeks; $w++) {
            $lecturerBusy[$w - 1] = $toRanges($this->engine()->lecturerBusyRanges($semesterId, $lecturerId, $w), $w - 1);
        }

        $cohortBusyCache = [];
        $out = ['' => $lecturerBusy];

        foreach ($sessions as $session) {
            $cohortIds = $session->cohorts->map(fn (Cohort $c): int => (int) $c->id)->sort()->values()->all();
            $hash = md5((string) json_encode($cohortIds));

            if (! isset($cohortBusyCache[$hash])) {
                $cohortBusyCache[$hash] = [];
                for ($w = 1; $w <= $weeks; $w++) {
                    $cohortBusyCache[$hash][$w - 1] = $toRanges(
                        $this->engine()->cohortBusyRanges($semesterId, $cohortIds, $w),
                        $w - 1,
                    );
                }
            }

            $merged = [];
            for ($w = 1; $w <= $weeks; $w++) {
                $merged[$w - 1] = array_values(array_unique(
                    array_merge($lecturerBusy[$w - 1], $cohortBusyCache[$hash][$w - 1]),
                    SORT_REGULAR,
                ));
            }

            // keyed by the SESSION id — the client re-keys the grid vectors
            // whenever the picked slot moves to another occurrence (L vs T vs P
            // of the same module have different cohort sets)
            $out[(string) $session->id] = $merged;
        }

        return $out;
    }

    /**
     * TEMP-HOLDER-LABEL — the busy ranges' holder texts, keyed
     * [weekIdx][day:slotStart:slotEnd] = list of texts. Same semantics as
     * the engine's busy queries (semester-scoped, the week's exception'd
     * pairs excluded). The viewer's own classes prefix 'You — '.
     *
     * @return array<int, array<string, list<string>>>
     */
    private function busyCellHolderLabels(int $semesterId, int $weeks): array
    {
        $out = [];

        $ex = ClassException::query()
            ->whereBetween('week_number', [1, $weeks])
            ->get(['class_session_id', 'week_number'])
            ->mapWithKeys(function (ClassException $e): array {
                return [$e->class_session_id.':'.$e->week_number => true];
            });

        $rows = ClassSession::query()
            ->join('modules as m', 'm.id', '=', 'class_sessions.module_id')
            ->join('venues as v', 'v.id', '=', 'class_sessions.venue_id')
            ->join('users as u', 'u.id', '=', 'class_sessions.lecturer_id')
            ->join('lecturers as le', 'le.user_id', '=', 'class_sessions.lecturer_id')
            ->where('class_sessions.semester_id', $semesterId)
            ->get([
                'class_sessions.id',
                'class_sessions.day_of_week',
                'class_sessions.start_time',
                'class_sessions.end_time',
                'class_sessions.session_type',
                'class_sessions.lecturer_id',
                'm.module_code',
                'v.room_code',
                'u.name as lecturer_name',
                'le.staff_id',
            ]);

        $cohortTexts = [];
        foreach ($rows as $row) {
            $session = ClassSession::with('cohorts.programme')->find($row->id);
            if ($session === null) {
                continue;
            }
            $cohortTexts[$row->id] = $session->cohorts
                ->map(fn (Cohort $c): string => $this->cohortCode($c))
                ->implode(', ');
        }

        $myId = (int) auth()->id();
        $labelFor = function ($row) use ($cohortTexts, $myId): string {
            $mine = (int) $row->lecturer_id === $myId;
            $prefix = $mine ? 'You — ' : '';

            return $prefix
                .$row->module_code.'('.(string) $row->session_type.')'
                .' · '.(string) $row->room_code
                .' · '.substr((string) $row->start_time, 0, 5).'-'.substr((string) $row->end_time, 0, 5)
                .' · '.(string) $row->lecturer_name.' ('.$row->staff_id.')'
                .' · cohorts: '.($cohortTexts[$row->id] ?? '-');
        };

        for ($w = 1; $w <= $weeks; $w++) {
            foreach ($rows as $row) {
                if (isset($ex[$row->id.':'.$w])) {
                    continue;   // the week's exception'd pairs are not busy (the engine's own semantics)
                }
                $key = (int) $row->day_of_week.':'.$this->slotIndex((string) $row->start_time).':'.$this->slotIndex((string) $row->end_time);
                $out[$w - 1][$key][] = $labelFor($row);
            }
        }

        /* SDD makeup-busy-vector (2026-10-27, design D4 + the reviewer's
           per-row key law): the make-up slots' holder text rides the SAME
           [weekIdx][day:slotStart:slotEnd] key — ONE tuple per time_slot
           row (13:30-14:00 / 14:00-14:30 = two), never the rr's span.
           The other chunks ("· Replacement") keep the aria honest. */
        $makeUpRows = DB::table('time_slots')
            ->whereIn('time_slots.status', ['pending', 'occupied'])
            ->where('time_slots.semester_id', $semesterId)
            ->where('time_slots.week_number', '<=', $weeks)
            ->join('replacement_request_slots',
                'replacement_request_slots.time_slot_id', '=', 'time_slots.id')
            ->join('replacement_requests',
                'replacement_requests.id', '=',
                'replacement_request_slots.replacement_request_id')
            ->whereIn('replacement_requests.status', ['pending', 'approved'])
            ->join('class_sessions as cs', 'cs.id', '=', 'replacement_requests.class_session_id')
            ->join('modules as m', 'm.id', '=', 'cs.module_id')
            ->join('venues as v', 'v.id', '=', 'time_slots.venue_id')
            ->join('users as u', 'u.id', '=', 'cs.lecturer_id')
            ->join('lecturers as le', 'le.user_id', '=', 'cs.lecturer_id')
            ->get([
                'time_slots.id as slot_id',
                'time_slots.week_number as wk',
                'time_slots.day_of_week',
                'time_slots.start_time',
                'time_slots.end_time',
                'cs.id as session_id',
                'cs.session_type',
                'cs.lecturer_id',
                'm.module_code',
                'v.room_code',
                'u.name as lecturer_name',
                'le.staff_id',
                'replacement_requests.status',
            ]);
        /* stdClass rows (DB::table) — the TimeSlot::get model getter on the
           aliased joins was phpstan-hostile; the query Builder row keeps
           the same columns. */

        $mkCohorts = [];
        foreach ($makeUpRows as $row) {
            if (! isset($mkCohorts[$row->session_id])) {
                $mkCohorts[$row->session_id] = ClassSession::query()
                    ->where('id', $row->session_id)
                    ->with('cohorts.programme')
                    ->first()
                    ?->cohorts
                    ->map(fn (Cohort $c): string => $this->cohortCode($c))
                    ->implode(', ');
            }
        }

        foreach ($makeUpRows as $row) {
            $w = (int) $row->wk;
            if ($w < 1 || $w > $weeks) {
                continue;
            }
            $mine = (int) $row->lecturer_id === (int) auth()->id();
            $label = ($mine ? 'You — ' : '')
                .$row->module_code.'('.(string) $row->session_type.')'
                .' · '.(string) $row->room_code
                .' · '.substr((string) $row->start_time, 0, 5).'-'.substr((string) $row->end_time, 0, 5)
                .' · '.(string) $row->lecturer_name.' ('.$row->staff_id.')'
                .' · cohorts: '.($mkCohorts[$row->session_id] ?? '-')
                .' · Replacement ('.(string) $row->status.')';
            $key = (int) $row->day_of_week.':'.$this->slotIndex((string) $row->start_time).':'.$this->slotIndex((string) $row->end_time);
            $out[$w - 1][$key][] = $label;
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
     * @return array<int|string, array<int, string>>
     */
    private function blockedVenuesPayload(Collection $sessions): array
    {
        $roomCodes = Venue::query()->orderBy('room_code')->pluck('room_code')
            ->map(fn ($code): string => (string) $code);
        $all = $roomCodes->all();

        $out = ['' => []];
        /* one venue load for ALL sessions (101+ rows would otherwise run a
           query per session); the venue rows are immutable per payload tick */
        $venues = Venue::query()->orderBy('room_code')->get(['id', 'room_code', 'capacity', 'room_type']);
        foreach ($sessions as $session) {
            /* venue-restrictions-enforcement: the SESSION-scoped admission
               (module-conditioned labs / the B006 widening / R6 / R2) — the
               grid's "venue renders occupied" truth per session. A venue
               not loading into the session-scoped gate reads as blocked
               (fail-closed). */
            $eligible = [];
            foreach ($venues as $venue) {
                if ($this->engine()->sessionVenueEligible($session, $venue)) {
                    $eligible[] = (string) $venue->room_code;
                }
            }

            $out[(string) $session->id] = $roomCodes
                ->reject(fn (string $roomCode): bool => in_array($roomCode, $eligible, true))
                ->values()
                ->all();
        }

        return $out;
    }

    /**
     * The mock's proceed() confirm now posts each selected block through the
     * real pipeline. Blocks arrive as {venue, week (0-based JS), day,
     * startHour, endHour}; each becomes one pending request for the PINNED
     * occurrence (contextJson: {sessionId, occurrenceWeek} — the D1 pair the
     * handoff carries; legacy entries without a pin target the slot's own
     * week) with the D8 rules, engine pre-check, OCC reserve and D5
     * compensation inside SubmitReplacementRequest. Returns per-block results
     * in input order so the page's own toast/modal vocabulary can report
     * successes and FR 4.10 conflicts.
     *
     * @return array<int, array{ok: bool, reason: string|null}>
     */
    #[Renderless]
    public function submitBlocks(string $blocksJson, string $code = '', string $origJson = '{}', string $contextJson = '{}'): array
    {
        // Renderless: this page's grid, dropdowns and selection state are all
        // client-built — a post-call re-render/morph wiped them (the failed
        // submit emptied the grid, the "table renders EMPTY" bug); every
        // result is reported through the page's own modal/toast vocabulary.

        // The context pins the occurrence (D1 pair) when the handoff carried
        // one; the id is ownership-scoped, so a fabricated id resolves to null.
        $context = json_decode($contextJson, true);
        $sessionId = is_array($context) && isset($context['sessionId']) ? (int) $context['sessionId'] : 0;
        $session = $sessionId > 0
            ? ClassSession::query()->where('lecturer_id', auth()->id())->find($sessionId)
            : $this->resolveSession($code, $origJson);
        if ($session === null) {
            return [['ok' => false, 'reason' => 'Class not found among your classes.']];
        }

        // The week the request REPLACES (the original occurrence's week — D1
        // indexes it, D7 releases it at approval) is independent of where the
        // new slot lives. occurrence-week-pin-truthfulness D-P2: the pin is
        // MANDATORY — the UI path ALWAYS sends {sessionId, week} (the pin);
        // 'occurrenceWeek' stays accepted for direct/legacy callers; NEITHER
        // keys' absence passes (the per-block loser fires in the loop). No
        // silent week takeover survives.
        $weeks = $this->timelineWeeks();
        $rawOccurrenceWeek = is_array($context) ? ($context['occurrenceWeek'] ?? $context['week'] ?? null) : null;
        $occurrenceWeek = $rawOccurrenceWeek !== null && (int) $rawOccurrenceWeek >= 1 && (int) $rawOccurrenceWeek <= $weeks
            ? (int) $rawOccurrenceWeek
            : null;

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

            // Full-block span derivation (SDD full-block-reservation, design
            // D1); the block's exclusive-end index the client already sends:
            // span = endHour − startHour, REJECTED out-of-bounds BEFORE any
            // business pre-check (round-5 fold) — never clamped (a 3h ask is a
            // rejection, not a shortened booking).
            $rawSpan = (int) ($block['endHour'] ?? -1) - $startHour;
            if ($rawSpan < SubmitReplacementRequest::SPAN_MIN || $rawSpan > SubmitReplacementRequest::SPAN_MAX) {
                $results[] = ['ok' => false, 'reason' => $this->conflictText('invalid_span')];

                continue;
            }

            // D1 — one active request per block occurrence (the partial unique
            // index enforces it atomically; this pre-check makes it a friendly
            // conflict message instead of a 500 on the race).
            // occurrence-week-pin-truthfulness D-P2: the fallback's death
            // sentence — a missing pin NEVER silently takes the slot's week.
            if ($occurrenceWeek === null) {
                // POSITIONAL CONTRACT: push BEFORE continue — the client
                // pairs results[i] with blocks[i] (a skipped row shifts
                // every later verdict onto the wrong block).
                $results[] = ['ok' => false, 'reason' => $this->conflictText('no_occurrence_pin')];

                continue;
            }
            $requestWeek = $occurrenceWeek;
            if (ReplacementRequest::query()
                ->where('class_session_id', $session->id)
                ->where('week_number', $requestWeek)
                ->whereIn('status', ['pending', 'approved'])
                ->exists()) {
                $results[] = ['ok' => false, 'reason' => $this->conflictText('duplicate_active_request')];

                continue;
            }

            // D2 (2026-10-11 user decision) — holidays are unbookable: the new
            // slot may not land on a public-holiday day. (Replacing a PH-hit
            // occurrence remains exactly what the flow is for.)
            $isHoliday = Holiday::query()
                ->where('semester_id', $semesterId)
                ->where('week_number', $week)
                ->where('day_of_week', $day)
                ->exists();
            if ($isHoliday) {
                $results[] = ['ok' => false, 'reason' => $this->conflictText('holiday')];

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
                'week_number' => $requestWeek,
                'replacement_time_slot_id' => (int) $slot->id,
                'remarks' => null,
            ], StoreReplacementRequest::rules());

            if ($validator->fails()) {
                $results[] = ['ok' => false, 'reason' => (string) $validator->errors()->first()];

                continue;
            }

            $result = $action(auth()->user(), $session, $requestWeek, (int) $slot->id, null, $rawSpan);

            $results[] = [
                'ok' => (bool) $result['ok'],
                'reason' => $result['ok'] ? null : $this->conflictText(
                    (string) $result['conflict_reason'],
                    $result['failing_start'] ?? null,
                    $result['failing_end'] ?? null,
                    $result['failing_kind'] ?? null,
                    $this->holderRange((int) $slot->id, $result['failing_kind'] ?? null, $result['failing_start'] ?? null),
                ),
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

    /**
     * Full-block routing table (design D6 frozen; user amendment 2026-10-16
     * context view "B"): named-time copies interpolate the failing slot's
     * times; occupied losers append the holder's live range via
     * `holder_start`/`holder_end` (resolved per failing slot from its live
     * junction link; cell-only fallback when no live link resolves).
     *
     * @param  array{start: string, end: string}|null  $holderRange
     */
    private function conflictText(
        string $reason,
        ?string $failingStart = null,
        ?string $failingEnd = null,
        ?string $failingKind = null,
        ?array $holderRange = null,
    ): string {
        // slot-availability losers get the named-time + copy, per-kind.
        if ($reason === 'slot_not_available' || $reason === 'span_overlap') {
            $spanWord = 'Block';
            if ($failingKind === 'missing') {
                return 'Part of the block falls outside the booking grid — adjust the selection.';
            }
            if (! $failingStart) {
                return 'That slot was just taken.'; // no-kind + no-times → canned
            }
            $root = 'The block runs into an existing booking at '.substr((string) $failingStart, 0, 5).'–'.substr((string) $failingEnd, 0, 5);
            if ($reason === 'span_overlap') {
                $root = 'Your block runs into an existing booking at '.substr((string) $failingStart, 0, 5).'–'.substr((string) $failingEnd, 0, 5);
            }
            if ($failingKind === 'occupied' && $holderRange !== null) {
                $root .= sprintf(
                    ' (part of a held %.4g-hour block, %s–%s)',
                    max(0.5, ((strtotime((string) $holderRange['end']) - strtotime((string) $holderRange['start'])) / 3600)),
                    substr((string) $holderRange['start'], 0, 5),
                    substr((string) $holderRange['end'], 0, 5),
                );
            }

            return $root.' — pick another slot.';
        }

        return match ($reason) {
            'slot_not_found' => 'That slot does not exist in the booking grid.',
            'invalid_span' => 'Blocks are 30 minutes to 2 hours — adjust the selection.',
            'engine_precheck_failed' => 'You or the cohort is no longer free at that time.',
            'reserve_interrupted' => 'The booking could not be completed — try again.',
            'duplicate_active_request' => 'This occurrence already has an active replacement request — see My Request History.',
            'holiday' => 'That day is a public holiday — replacement classes cannot be booked on it.',
            'occurrence_not_conflicted' => 'That week has no conflict to replace — pick an occurrence that needs a make-up (holiday, cancellation, venue restriction).',
            'no_occurrence_pin' => 'Pick the slot on the grid — the week being replaced must be the conflicted occurrence\'s own.',
            default => 'Booking conflict — refresh and try again.',
        };
    }

    /**
     * The holder's LIVE range for the failed slot (user amendment): follow the
     * junction's live link → its request → every junction slot's min/max.
     * Frozen entity name: the LIVE-receipt — 'never re-retain released rows'
     * (a released slot resolves to no holder → null).
     *
     * @return array{start: string, end: string}|null
     */
    private function holderRange(int $failingSlotId, ?string $failingKind, ?string $failingStart): ?array
    {
        if ($failingKind !== 'occupied' || ! $failingStart) {
            return null;
        }

        $holderRequest = ReplacementRequestSlot::query()
            ->where('time_slot_id', $failingSlotId)
            ->first();

        if (! $holderRequest) {
            return null;
        }

        $holderRows = DB::table('replacement_request_slots')
            ->join('time_slots', 'replacement_request_slots.time_slot_id', '=', 'time_slots.id')
            ->where('replacement_request_slots.replacement_request_id', $holderRequest->replacement_request_id)
            ->orderBy('time_slots.start_time')
            ->get(['time_slots.start_time', 'time_slots.end_time']);

        if ($holderRows->isEmpty()) {
            return null;
        }

        return [
            'start' => (string) $holderRows->first()->start_time,
            'end' => (string) $holderRows->last()->end_time,
        ];
    }
}
