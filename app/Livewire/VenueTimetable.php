<?php

namespace App\Livewire;

use App\Concerns\CancelsClasses;
use App\Concerns\ResolvesTimetableTimeline;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\Venue;
use App\Services\LectureSessionCatalog;
use App\Services\ReplacementRequestMapper;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Venue Timetable — real slot-level data (SDD: venue-timetable-db).
 * READ-only v1: grid, slot states, class details, summary — all from
 * time_slots/class_sessions. Booking arrives with Slice B (affordances
 * deliberately absent, honest hint instead).
 *
 * Render-only (Slice A house pattern): week nav is the shared client-side
 * WeekNavigator over the all-weeks event map; venue switching happens via
 * `?venue=` full-render links (mount reads the param once — deep links work,
 * scripts re-run naturally on navigation).
 */
class VenueTimetable extends Component
{
    use CancelsClasses;
    use ResolvesTimetableTimeline;

    public string $venueCode = '';

    #[Title('Venue Timetable')]
    public function mount(): void
    {
        $requested = (string) request()->query('venue', '');
        $venue = Venue::where('room_code', $requested)->first()
            ?? Venue::orderBy('room_code')->first();

        $this->venueCode = $venue !== null ? $venue->room_code : '';
    }

    /**
     * "Select a Session" model (SDD arrange-intent-session-choice design D2/D3,
     * user directive 2026-10-16 apply round 4): the pool is WEEK-AGNOSTIC —
     * every lecturer session with ≥1 conflicted occurrence anywhere in the
     * semester that THIS venue admits (venue-entry + B005 rules). A conflicted
     * class may be arranged at any week past the lead time — the conflict week
     * is where the need LIVES, not where the arrangement may happen (the
     * jump-to-week hints were removed by user request 2026-10-16: the pool is
     * week-agnostic, week hints implied a restriction the data does not have).
     * $week1 stays the wire contract's booked-week anchor (no longer shapes the
     * response). Wire-callable, owner-pinned (auth()->id() query — never
     * another lecturer's sessions); duration/day-end + span fit is client
     * arithmetic against the shared hours constant + the week's own grid.
     *
     * @param  int  $week1  1-based booked week (the clicked cell's week + 0-based→1-based convention)
     * @return array{modules: list<array<string, mixed>>, roomType: string, venue: string}
     */
    public function intentSessionOptions(int $week1): array
    {
        /* D2 apply fix (wire-call reality): Livewire does not persist private
           render state into a subsequent wire-call request — so the venue facts
           are re-derived from the HYDRATED public $venueCode (tamper-proof:
           an unknown code resolves to no venue → empty options, never an error). */
        $venueRow = Venue::where('room_code', $this->venueCode)->first();
        if ($venueRow === null) {
            return ['modules' => [], 'roomType' => '', 'venue' => $this->venueCode];
        }
        $rawRoomType = (string) $venueRow->room_type;
        $semesterId = $this->timelineSemester()?->id;
        $catalog = new LectureSessionCatalog;

        $lecturerSessions = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme'])
            ->where('lecturer_id', (int) auth()->id())
            ->where('semester_id', $semesterId)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        /* USER DIRECTIVE (2026-10-16, apply round 4): a conflicted class can be
           arranged at ANY week past the lead time — the "Jump to Week" buttons
           are a HINT (where the need lives), never a restriction. The modal
           pool is therefore WEEK-AGNOSTIC: every session with ≥1 conflicted
           occurrence anywhere in the semester that this venue's type admits
           (venue-entry + B005 cohort rule), regardless of the viewed week. */
        $conflicted = $catalog->conflictedFor($lecturerSessions);
        $eligible = $lecturerSessions
            ->filter(fn (ClassSession $s): bool => isset($conflicted[$s->id])
                && LectureSessionCatalog::sessionAllowedForVenue((string) $s->session_type, $rawRoomType, $s, (string) $venueRow->room_code))
            ->values();

        $built = array_values($catalog->build($eligible, null));
        foreach ($built as $moduleIndex => $module) {
            foreach ($module['order'] as $sessionId) {
                $facts = $conflicted[$sessionId];
                $built[$moduleIndex]['sessions'][(string) $sessionId]['conflictReason'] = $facts['reason'];
                $built[$moduleIndex]['sessions'][(string) $sessionId]['conflictWeeks'] = $facts['weeks'];
                // occurrence facts for the option label + the details tooltip:
                // (type) W{week}, {day}, {date} . {time} — the occurrence the
                // system flagged, wherever it lives in the semester.
                $built[$moduleIndex]['sessions'][(string) $sessionId]['conflictOccurrences'] = $facts['occurrences'];
            }
        }

        return [
            'modules' => $built,
            'roomType' => $rawRoomType,
            'venue' => $venueRow->room_code,
        ];
    }

    public function render(): View
    {
        $venue = Venue::where('room_code', $this->venueCode)->first();

        if ($venue === null) {
            // Empty venues table (fresh DB) — render with empty payloads.
            return $this->renderPayload(
                collect(),
                ['code' => '', 'name' => '', 'capacity' => 0, 'rawRoomType' => ''],
                array_fill(0, $this->timelineWeeks(), []),
                array_fill(0, $this->timelineWeeks(), ['total' => 0, 'available' => 0, 'occupied' => 0, 'myClasses' => 0, 'myHours' => '0']),
            );
        }

        // VenueDropdown contract (ui-common.js): code/name/capacity + display type.
        $venuesJs = Venue::orderBy('room_code')->get(['id', 'room_code', 'room_name', 'room_type', 'capacity', 'description'])
            ->map(fn (Venue $v) => [
                'code' => $v->room_code,
                'name' => $v->room_name ?? $v->room_code,
                'capacity' => (int) $v->capacity,
                // SDD venue-description-doc-b006-rule: the plain room
                // description rides along for the page's summary line.
                'description' => $v->description !== null ? (string) $v->description : '',
                // Raw DB encoding rides along (arrange-intent-session-choice
                // D0/D1 + lt-venue-capacity-rule): the modal + fallback + the
                // page's session pool consume ruleMap over room_type values;
                // the display mapping below stays for the dropdown filter.
                'rawRoomType' => (string) $v->room_type,
                'type' => match ($v->room_type) {
                    'lecture_hall' => 'LectureHall',
                    'cisco_lab' => 'CiscoLab',
                    'lab' => 'Lab',
                    default => 'Tutorial',
                },
            ]);

        // All-weeks events (no week filter) — grouped into the contiguous
        // eventsByWeek map the shared grid engine consumes (index w-1).
        $slots = TimeSlot::with(['classSession.module', 'classSession.lecturer', 'classSession.venue', 'classSession.cohorts.programme'])
            ->where('venue_id', $venue->id)
            ->whereIn('status', ['occupied', 'pending'])
            ->get();

        $weeks = $this->timelineWeeks();
        $eventsByWeek = array_fill(0, $weeks, []);
        $myUserId = auth()->id();
        // Per-week MY-slot accumulation for the ownership cards (occupied only —
        // pending bookings are not teaching). myClasses counts session ROWS
        // (each class counts separately, upstream wording), myHours = slots × 0.5.
        $mySlotCount = array_fill(0, $weeks, 0);
        $mySessionIds = array_fill(0, $weeks, []);
        // Coalescing groups (venue-block-span-coalescing): keyed w:day:session.
        $groups = [];

        foreach ($slots as $slot) {
            $session = $slot->classSession;
            $w = (int) $slot->week_number;
            if ($session === null || $w < 1 || $w > $weeks) {
                continue;
            }

            // Ownership cards stay ROW-counted (each 30-min slot = 0.5 h).
            $isMine = $session->lecturer_id === $myUserId;
            if ($isMine && $slot->status === 'occupied') {
                $mySlotCount[$w - 1]++;
                $mySessionIds[$w - 1][$session->id] = true;
            }

            $start = $this->slotIndex($slot->start_time);
            $end = $this->slotIndex($slot->end_time) - 1;
            $severity = $this->venueRestrictionConflict($session)
                ? 3
                : ($slot->status === 'pending' ? 2 : 0);

            // Coalescing pre-pass (venue-block-span-coalescing S1): the import
            // stores a full half-hour grid per venue/week — a class OCCUPIES its
            // adjacent rows (a 2-hour lecture = 4 rows). Group rows by
            // class_session_id within (week, day) and emit ONE event spanning
            // min→max slot index. NEVER key on module code + adjacency: B006
            // Monday runs BMIT2154 (L) 09:00–11:00 straight into BMIT2154 (T)
            // 11:00–12:00 — two distinct sessions. Min/max semantics (the slot
            // query has no orderBy); rows are contiguous by construction, so no
            // gap-splitting. Rendering-only: the cards below stay row-counted.
            $key = $w.':'.$slot->day_of_week.':'.$session->id;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'w' => $w,
                    'di' => (int) $slot->day_of_week,
                    'id' => $slot->id,
                    'start' => $start,
                    'end' => $end,
                    'severity' => $severity,
                    'session' => $session,
                ];

                continue;
            }

            $g = &$groups[$key];
            $g['id'] = min($g['id'], $slot->id);
            $g['start'] = min($g['start'], $start);
            $g['end'] = max($g['end'], $end);
            $g['severity'] = max($g['severity'], $severity);
            unset($g);
        }

        foreach ($groups as $g) {
            $session = $g['session'];
            $event = [
                'id' => $g['id'],
                'di' => $g['di'],
                'start' => $g['start'],
                'end' => $g['end'],
                'code' => $session->module->module_code,
                'name' => $session->module->module_name,
                'type' => $session->session_type,
                'venue' => $venue->room_code,
                'lecturer' => $session->lecturer->name,
                'cohort' => $this->cohortLabel($session->cohorts),
                'cohorts' => $session->cohorts->map(fn ($c) => $this->cohortCode($c))->all(),
                'studentCount' => (int) $session->cohorts->sum('student_count'),
                'status' => match ($g['severity']) {
                    3 => 'conflict',
                    2 => 'pending',
                    default => 'normal',
                },
                'remarks' => '',
                'mine' => $session->lecturer_id === $myUserId,
                /* class-modal-affordances T3: the Replace Now handoff targets
                   ?session= — the events' `id` is the SLOT id (the grid's
                   row key), so the class_session id rides here (additive). */
                'sessionId' => $session->id,
            ];

            // Twin-merge pre-bucketing (combined lectures): same venue + day +
            // start collapses in mergeTwinEvents() after coalescing. The
            // occupied/pending partial unique index makes real twins
            // impossible until Slice B — the merge is defensive contract
            // parity, unit-tested directly (see VenueTimetableTest).
            $eventsByWeek[$g['w'] - 1][$event['di'].':'.$event['start']][] = $event;
        }

        // Reserved-slot second pass (venue-pending-and-type-labels D1; SDD
        // full-block-reservation round-1 review): the span-aware read-model —
        // ONE event per REQUEST (the junction's span reachmentioned where the
        // anchor-keyed join can't see tails). One row per reserved slot would
        // never coalesce (mergeTwinEvents merges same-cell twins only), so the
        // pending join keys through replacement_request_slots and each request
        // emits ONE event with end = start + junctionCount − 1 — the blade's
        // span-N colSpan renders it. Class facts still come from the request's
        // ORIGINAL session; `mine` reads the proposer_id column directly.
        $reserved = $slots->where('class_session_id', null); // pending AND approved-occupied rows ride the junction second pass (the D4 approved-bucket switch)

        // Junction spans: slot id → its request id (pending + approved).
        $junctionLink = DB::table('replacement_request_slots')
            ->whereIn('time_slot_id', $reserved->pluck('id'))
            ->pluck('replacement_request_id', 'time_slot_id');

        // occurrence-week-pin-truthfulness D-P5: the original* keys read
        // classSession.venue — eager-load it (relation-unloaded today).
        $requests = ReplacementRequest::with(['classSession.module', 'classSession.lecturer', 'classSession.venue', 'classSession.cohorts.programme', 'proposer'])
            ->whereIn('status', ['pending', 'approved'])
            ->whereIn('id', $junctionLink->unique()->values())
            ->get()
            ->keyBy('id');

        // The span cover per request (min/max of its junction rows' ordered cells).
        $spans = DB::table('replacement_request_slots')
            ->join('time_slots', 'replacement_request_slots.time_slot_id', '=', 'time_slots.id')
            ->whereIn('replacement_request_slots.replacement_request_id', $junctionLink->unique()->values())
            ->groupBy('replacement_request_slots.replacement_request_id',
                'replacement_request_slots.created_at')
            ->orderBy('replacement_request_slots.replacement_request_id')
            ->get([
                'replacement_request_slots.replacement_request_id',
                DB::raw('MIN(time_slots.start_time) as first_start'),
            ])
            ->keyBy('replacement_request_id');

        $emittedRequests = [];
        foreach ($reserved as $slot) {
            $requestId = (int) ($junctionLink[$slot->id] ?? 0);
            $request = $requests->get($requestId);
            $session = $request?->classSession;
            // Orphan defence (design D1): cancel/submit keep slot+request in
            // lockstep, so a pending slot without an active request (or the
            // class row it points at) should not exist — emit nothing, no
            // error, no log noise.
            if ($request === null || $session === null) {
                continue;
            }
            // One event PER REQUEST (identifier = the anchor slot id — frozen
            // round-3 💡): skip the other junction-ordered rows.
            if (in_array($requestId, $emittedRequests, true)) {
                continue;
            }
            $emittedRequests[] = $requestId;
            // Week guard: same 1..$weeks window as the imported path.
            $w = (int) $slot->week_number;
            if ($w < 1 || $w > $weeks) {
                continue;
            }

            $isApproved = (string) $request->status === 'approved';
            $start = $this->slotIndex($slot->start_time);
            // The ORIGINAL occurrence's date (my-timetable's remarks family —
            // '22-Oct-2026'): semester start + (request week − 1) × 7 + the
            // class's day — computed from the request's own week.
            $originalDate = $this->timelineSemester()?->start_date
                ?->copy()
                ?->addWeeks((int) $request->week_number - 1)
                ?->addDays((int) $session->day_of_week)
                ?->format('d-M-Y');
            // The junction cover: span = the request's LIVE junction row count
            // (start = the anchor's own slot; end extends contiguously).
            $junctionCount = $junctionLink->filter(fn ($rid) => $rid === $requestId)->count();
            $spanSlots = max(1, $junctionCount);
            $end = min($start + $spanSlots - 1, 21); // the venue page's grid constants: 8:00..18:30 = 21 slots (design D8's shared-hours discipline)
            $event = [
                'id' => (int) $request->replacement_time_slot_id,
                'di' => (int) $slot->day_of_week,
                'start' => $start,
                'end' => $end,
                'code' => $session->module->module_code,
                'name' => $session->module->module_name,
                'type' => $session->session_type,
                'venue' => $venue->room_code,
                'lecturer' => $session->lecturer->name,
                'cohort' => $this->cohortLabel($session->cohorts),
                'cohorts' => $session->cohorts->map(fn ($c) => $this->cohortCode($c))->all(),
                'studentCount' => (int) $session->cohorts->sum('student_count'),
                // occurrence-week-pin-truthfulness D-P5: the Original rows'
                // payload keys (status-independent — one shape for both
                // statuses; venue-less sessions send empty strings, never a
                // read-on-null).
                'originalVenue' => $session->venue !== null ? (string) $session->venue->room_code : '',
                'originalVenueName' => $session->venue !== null ? (string) ($session->venue->room_name ?? '') : '',
                'originalStartTime' => substr((string) $session->start_time, 0, 5),
                'originalEndTime' => substr((string) $session->end_time, 0, 5),
                // occurrence-week-pin-truthfulness popup-accuracy rider: the
                // pending popup's Requested At/By rows (MYT; the proposer's
                // name) — the venue modal showed nulls before.
                'requestedAt' => app(ReplacementRequestMapper::class)->stampDisplay($request->submitted_at, 'd M Y, h:i A'),
                'requestedBy' => (string) ($request->proposer->name ?? ''),
                'status' => $isApproved ? 'replacement' : 'pending', // the frozen legend (STATUSES now carry the direct blue my-timetable class; pending stays amber)
                'remarks' => $isApproved
                    ? (string) $originalDate
                    : 'Replacement request awaiting PL approval', // byte-matches the legend tip
                'mine' => $request->proposer_id === $myUserId,
            ];

            $eventsByWeek[$w - 1][$event['di'].':'.$event['start']][] = $event;

            // USER 2026-10-19 summary accuracy (A&B): the viewer's OWN approved
            // replacement is real teaching — count it in the My-Teaching cards
            // (one class + its live-junction span × 0.5 h), matching the block
            // the grid actually renders.
            if ($isApproved && $request->proposer_id === $myUserId) {
                $mySlotCount[$w - 1] += $spanSlots;
                $mySessionIds[$w - 1][$session->id] = true;
            }
        }

        // ═══ occurrence-week-pin-truthfulness T20 — the DERIVED holiday family ═══
        // The holiday import skipped whole week/day families (W8-Mon, W14-Thu).
        // The venue page's row-keyed pass then renders NOTHING for classes whose
        // venue contains them — while the timeline trait derives its blocks from
        // the RECURRING session rows. This pass closes the gap: for every
        // holiday (week, day) pair, the recurring sessions whose venue = the
        // viewed venue + the day = the holiday's day build synthetic conflict
        // rows — UNLESS a stamp (class_exception) or a live request
        // (pending/approved) already HANDLES the pair (the same handled rule
        // the timeline uses). The imported rows win the de-dupe (their payload
        // is richer); the overlapping derived clones drop away.
        // the derived pass's OWN semester read + the TOTals map hoist (the
        // derived + the imported family share ONE map — the T20 pre-lift)
        $totalsByWeek = array_fill(0, $weeks, ['total' => 0, 'available' => 0, 'occupied' => 0, 'myClasses' => 0, 'myHours' => '0']);
        $t20Semester = $this->timelineSemester();
        $holidayByWeekDay = [];
        if ($t20Semester !== null) {
            foreach ($t20Semester->holidays()->get(['week_number', 'day_of_week', 'label']) as $h) {
                $holidayByWeekDay[(int) $h->week_number][(int) $h->day_of_week] = (string) $h->label;
            }
        }

        // the handled set: (a) the STAMPS (the exact (session, week) pairs —
        // NOT every week of that session), (b) the live request pairs.
        $handledPairs = [];
        foreach (DB::table('class_exceptions')->get() as $stamp) {
            $handledPairs[$stamp->class_session_id.':'.$stamp->week_number] = true;
        }
        foreach (ReplacementRequest::whereIn('status', ['pending', 'approved'])
            ->get(['class_session_id', 'week_number']) as $req) {
            $handledPairs[$req->class_session_id.':'.$req->week_number] = true;
        }

        $recurring = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme'])
            ->where('venue_id', $venue->id)
            ->get();

        foreach ($recurring as $session) {
            $dayIdx = (int) $session->day_of_week;
            foreach ($holidayByWeekDay as $w3 => $daySet) {
                $weekNum = (int) $w3;
                if ($weekNum < 1 || $weekNum > $weeks) {
                    continue;   // the D1 week-guard (the 1..$weeks window — the semester may shrink)
                }
                if (! isset($daySet[$dayIdx])) {
                    continue;
                }
                // the handled rule: the stamp / the live request owns the pair
                if (isset($handledPairs[$session->id.':'.$weekNum])) {
                    continue;
                }

                // the 0-BASED INCLUSIVE-cell convention (the timeline trait's
                // own shipped shape: end = slotIndex(end_time) − 1 — the T20
                // derive pass originally wrote the exclusive index, which read
                // a half-hour long: the 1:30 PM bug)
                $startIdx = $this->slotIndex((string) $session->start_time);
                $endIdx = $this->slotIndex((string) $session->end_time) - 1;

                // the de-dupe: an imported ROW already covers the span (the
                // eventsByWeek map keys on 'di:start'; the anchor rows use
                // start < endIdx && end > startIdx pairwise)
                $covered = false;
                foreach ($eventsByWeek[$weekNum - 1] as $rows) {
                    foreach ($rows as $existing) {
                        if ((int) $existing['di'] === $dayIdx
                            && (int) $existing['start'] < $endIdx
                            && (int) $existing['end'] > $startIdx) {
                            $covered = true;
                            break 2;
                        }
                    }
                }
                if ($covered) {
                    continue;
                }

                $label = $daySet[$dayIdx];
                $event = [
                    'id' => null,  // NO row — the Class Ref row drops from the popup (honest)
                    /* SDD cohort-venue-replace-now: the Replace Now handoff
                       targets ?session= — the derived family's rows (this
                       holiday-conflict pass) MUST carry the class_session id
                       too (the base-event rows did since class-modal-afford-
                       ances; this branch missed it → the button rendered but
                       goToReplacementFor's !sessionId guard swallowed the
                       click). Additive row, same contract as :249. */
                    'sessionId' => $session->id,
                    'di' => $dayIdx,
                    'start' => $startIdx,
                    'end' => $endIdx,
                    'code' => (string) $session->module->module_code,
                    'name' => (string) $session->module->module_name,
                    'type' => (string) $session->session_type,
                    'venue' => (string) $venue->room_code,
                    'lecturer' => (string) ($session->lecturer->name ?? ''),
                    'cohort' => $this->cohortLabel($session->cohorts),
                    'cohorts' => $session->cohorts->map(fn ($c) => $this->cohortCode($c))->all(),
                    'studentCount' => (int) $session->cohorts->sum('student_count'),
                    'status' => 'conflict',  // the DERIVED family's own status
                    'remarks' => 'Public Holiday ('.$label.')', // byte-parity the timeline's conflict reasons
                    'mine' => $session->lecturer_id === $myUserId,
                ];
                // the same ONE-anchor-cell push form the imported pass uses
                // (the FE's slotMap renders the span over the colSpan rows)
                $eventsByWeek[$weekNum - 1][$dayIdx.':'.$startIdx][] = $event;
                // the derived blocks count as OCCUPIED (the A&B law: the summary
                // reflects the blocks the grid actually shows)
                if (isset($totalsByWeek[$weekNum - 1]['occupied'])) {
                    $totalsByWeek[$weekNum - 1]['occupied'] += ($endIdx - $startIdx + 1);
                }
            }
        }
        // ═══ end T20 derived pass ═══

        // Grid engine consumes a list — merge twins per week and strip keys.
        $eventsByWeek = array_map(fn (array $weekMap): array => $this->mergeTwinEvents($weekMap), $eventsByWeek);

        // Per-week slot totals (venue-event-blocks-db design §3):
        // Available = free slots EXCLUDING Sunday and public-holiday slots —
        // our own legend tip says those can't be booked and the grid renders
        // them as offday cells, so counting them Available was a semantics
        // bug (holiday rows do keep status='available' in the DB).
        // sumPending is GONE (no v1 pending data); ownership cards replace it.
        $offdayByWeek = [];
        $semester = $this->timelineSemester();
        if ($semester !== null) {
            $semester->holidays()->get(['week_number', 'day_of_week'])->each(function ($h) use (&$offdayByWeek): void {
                $offdayByWeek[(int) $h->week_number][(int) $h->day_of_week] = true;
            });
        }
        // Sundays (day_of_week 6) — no DB rows exist (6×20 grid); excluded
        // for contract completeness with the cellRender offday rules.
        $isOffday = fn (int $w, int $day): bool => $day === 6 || isset($offdayByWeek[$w][$day]);

        // (the second array_fill REMOVED — the T20 pre-hoisted map at the top
        // is the single family: the derived counts would otherwise be wiped
        // right after they are written; the D8 sum family stays one)
        DB::table('time_slots')
            ->where('venue_id', $venue->id)
            ->selectRaw('week_number, day_of_week, status, count(*) as c')
            ->groupBy('week_number', 'day_of_week', 'status')
            ->get()
            ->each(function ($row) use (&$totalsByWeek, $weeks, $isOffday): void {
                $w = (int) $row->week_number;
                if ($w < 1 || $w > $weeks || ! isset($totalsByWeek[$w - 1][$row->status])) {
                    return;
                }
                $c = (int) $row->c;
                $totalsByWeek[$w - 1][$row->status] += $c;
                $totalsByWeek[$w - 1]['total'] += $c;
                if ($row->status === 'available' && $isOffday($w, (int) $row->day_of_week)) {
                    $totalsByWeek[$w - 1]['available'] -= $c;
                }
            });

        foreach ($mySlotCount as $i => $count) {
            $totalsByWeek[$i]['myClasses'] = count($mySessionIds[$i]);
            // 28 slots → '14', 27 slots → '13.5' (strip trailing .0).
            $totalsByWeek[$i]['myHours'] = rtrim(rtrim(sprintf('%.1f', $count * 0.5), '0'), '.');
        }

        return $this->renderPayload($venuesJs, [
            'code' => $venue->room_code,
            'name' => $venue->room_name ?? $venue->room_code,
            'capacity' => (int) $venue->capacity,
            // SDD venue-description-doc-b006-rule: the viewed room's plain description.
            'description' => $venue->description !== null ? (string) $venue->description : '',
            'rawRoomType' => (string) $venue->room_type, // D2: the modal's server-side eligibility key
        ], $eventsByWeek, $totalsByWeek);
    }

    /**
     * @param  Collection<int, array{code: string, name: string, capacity: int, description: string, rawRoomType: string, type: 'CiscoLab'|'Lab'|'LectureHall'|'Tutorial'}>  $venuesJs
     * @param  array{code: string, name: string, capacity: int, rawRoomType: string}  $venue
     * @param  array<int, array<int, array<string, mixed>>>  $eventsByWeek
     * @param  array<int, array<string, int|string>>  $totalsByWeek
     */
    private function renderPayload(
        Collection $venuesJs,
        array $venue,
        array $eventsByWeek,
        array $totalsByWeek,
    ): View {
        return view('livewire.venue-timetable', [
            'venuesJs' => $venuesJs,
            'venue' => $venue,
            'eventsByWeek' => $eventsByWeek,
            'totalsByWeek' => $totalsByWeek,
            'weekData' => $this->weekData(),
            'semesterJs' => $this->semesterJs(),
            'holidaysJs' => $this->holidaysForJs(),
        ])
            ->extends('layouts.ui-template', ['activeNav' => 'venue-timetable', 'pageKey' => 'venueTimetable'])
            ->section('content');
    }

    /**
     * Twin-merge: keyed events (di:start → list) collapse into single blocks.
     * Status by severity (conflict > pending > replacement > normal), cohort
     * labels joined, cohort codes unioned, students summed, mine = OR.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $keyed
     * @return array<int, array<string, mixed>>
     */
    private function mergeTwinEvents(array $keyed): array
    {
        return array_values(array_map(fn (array $twins): array => $this->reduceTwins($twins), $keyed));
    }

    /**
     * @param  array<int, array<string, mixed>>  $twins
     * @return array<string, mixed>
     */
    private function reduceTwins(array $twins): array
    {
        $merged = $twins[0];
        $severity = ['normal' => 0, 'replacement' => 1, 'pending' => 2, 'conflict' => 3];

        foreach (array_slice($twins, 1) as $twin) {
            if (($severity[$twin['status']] ?? 0) > ($severity[$merged['status']] ?? 0)) {
                $merged['status'] = $twin['status'];
            }
            if (str_contains((string) $merged['cohort'], (string) $twin['cohort']) === false) {
                $merged['cohort'] = trim((string) $merged['cohort'].' + '.$twin['cohort'], ' +');
            }
            foreach ($twin['cohorts'] as $cohortCode) {
                if (! in_array($cohortCode, $merged['cohorts'], true)) {
                    $merged['cohorts'][] = $cohortCode;
                }
            }
            $merged['studentCount'] += $twin['studentCount'];
            $merged['mine'] = $merged['mine'] || $twin['mine'];
        }

        return $merged;
    }

    /**
     * Multi-cohort label (trait cohortCode per cohort, joined) — mirrors baseEvent.
     *
     * @param  Collection<int, Cohort>  $cohorts
     */
    private function cohortLabel($cohorts): string
    {
        if ($cohorts->isEmpty()) {
            return '—';
        }
        if ($cohorts->count() === 1) {
            return $this->cohortCode($cohorts->first());
        }

        return $cohorts->map(fn ($c) => $this->cohortCode($c))->implode(' + ');
    }
}
