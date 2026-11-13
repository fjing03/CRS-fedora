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
use App\Services\ArrangementSlotHolders;
use App\Services\LectureSessionCatalog;
use App\Services\MatrixIntersectionEngine;
use App\Services\ReplacementRequestMapper;
use App\Support\LocalTime;
use App\Support\ReasonLabel;
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

    /**
     * The ONE definition of "already being handled" (D1): a request in either
     * state reserves the occurrence's whole block. Shared by the conflict-grid
     * filter (conflictEventsPayload) and the ENTRY GUARD below so the two can
     * never drift — rejected/withdrawn/cancelled requests do NOT block.
     */
    private const ACTIVE_REQUEST_STATUSES = ['pending', 'approved'];

    /**
     * `?from=` → landing route, mirroring the client's BackNavigator.#routes
     * (public/js/ui-common.js) exactly: the blocked entry sends the viewer to
     * the same place the page's own Back button would have.
     *
     * @var array<string, string>
     */
    private const FROM_LANDING_ROUTES = [
        'replacement-home' => '/my-replacements',
        'my-request-history' => '/my-replacements',
        'venue-timetable' => '/venue-timetable-ui',
        'my-timetable' => '/my-timetable-ui',
        'cohort-timetable' => '/cohort-timetable',
    ];

    /**
     * ENTRY GUARD, layer A (server) — the unproposed+own rule: the arrangement
     * page must not render for an occurrence that is ALREADY under an active
     * (pending/approved) replacement request. Ownership was always enforced
     * (a foreign/fabricated ?session= resolves to null); this closes the other
     * half, and it covers EVERY entry path (typed URL, my-timetable link,
     * notification deep-link, F5) because it runs on the initial mount.
     *
     * Ran from mount() BEFORE any payload work; the page redirects out with
     * the reason in `?blocked=` and the landing page's arrival toast explains
     * it (ui-common.js reads the param).
     */
    public function mount(): void
    {
        [$sessionId, $week] = $this->entryPinFromUrl();
        $landing = $this->entryBlockedLanding($sessionId, $week);
        if ($landing !== null) {
            $this->redirect($landing, navigate: false);
        }
    }

    /**
     * ENTRY GUARD, layer B (bfcache) — the browser may restore a frozen copy of
     * this page (back button after submitting), replaying the stale DOM as if
     * no request existed. Layer A can't help there: no server round-trip runs.
     * The client calls this on `pageshow` with `persisted === true` and the
     * server re-runs the same check.
     *
     * URL-VS-PIN DOCTRINE (2026-11-01, lane conflict-subject-switch-autoselect):
     * the URL CAN lag the live pin — a POST-boot subject switch moves
     * window.pinnedOccurrence (the pair the submit honours) while the URL keeps
     * the ARRIVAL pair. The client therefore sends the LIVE PIN FIRST and the
     * URL pair as the fallback, and BOTH are checked: a pending request on
     * either occurrence blocks the restore. A URL-only key would re-open this
     * bug for a pinned occurrence reached by an auto-pin.
     *
     * `$from` rides from the client because a Livewire update request is a POST
     * to the Livewire endpoint — `request()->query('from')` is EMPTY there, so
     * the landing choice cannot be re-read server-side on this path.
     *
     * RETURNS the landing URL (null = honest, stay) and lets the CLIENT navigate:
     * a bfcache restore is exactly the state where Livewire's own JS may be
     * mid-resume, and a plain `window.location.href` assignment cannot be
     * swallowed by a suspended effect queue. (Layer A keeps the server-side
     * redirect — there is no client to ask on a fresh page load.)
     */
    #[Renderless]
    public function revalidateEntry(?int $pinSessionId = null, ?int $pinWeek = null, ?int $urlSessionId = null, ?int $urlWeek = null, ?string $from = null): ?string
    {
        $weeks = $this->timelineWeeks();

        $candidates = [
            [$pinSessionId, $pinWeek],
            [$urlSessionId, $urlWeek],
        ];

        foreach ($candidates as [$sessionId, $week]) {
            if ($sessionId === null || $week === null) {
                continue;
            }

            $weeksBound = $week >= 1 && $week <= $weeks;
            if (! $weeksBound) {
                continue;
            }

            $landing = $this->entryBlockedLanding($sessionId, $week, $from);
            if ($landing !== null) {
                return $landing;
            }
        }

        return null;
    }

    /**
     * The guard itself: resolve the pair to the VIEWER'S OWN session, then
     * decide. Returns the landing URL when the occurrence is already handled,
     * null when the entry is honest.
     */
    private function entryBlockedLanding(?int $sessionId, ?int $week, ?string $from = null): ?string
    {
        if ($sessionId === null || $week === null || $sessionId < 1 || $week < 1) {
            return null;
        }

        // Ownership first (FR 2.11): a foreign/fabricated pair is simply not
        // ours — it never blocks, it just resolves to nothing (today's law).
        $owned = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->whereKey($sessionId)
            ->exists();
        if (! $owned) {
            return null;
        }

        $active = ReplacementRequest::query()
            ->where('class_session_id', $sessionId)
            ->where('week_number', $week)
            ->whereIn('status', self::ACTIVE_REQUEST_STATUSES)
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->first();
        if ($active === null) {
            return null;
        }

        return $this->blockedLandingUrl((string) $active->status, $from);
    }

    /**
     * The blocked landing URL: the `from` source when known (else the
     * replacement home), carrying the toast reason for the landing page.
     */
    private function blockedLandingUrl(string $status, ?string $from = null): string
    {
        $from = $from ?? (string) request()->query('from', '');
        $base = self::FROM_LANDING_ROUTES[$from] ?? '/my-replacements';

        return $base.'?blocked='.($status === 'approved' ? 'request-approved' : 'request-pending');
    }

    /**
     * The `?session=&week=` pair as the entry pin (the same reads
     * pinnedSessionPayload validates — kept side by side so the guard can
     * never disagree with what the page would have pinned).
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function entryPinFromUrl(): array
    {
        $sessionId = (int) request()->query('session', '');
        $week = (int) request()->query('week', '');

        return [
            $sessionId >= 1 ? $sessionId : null,
            $week >= 1 ? $week : null,
        ];
    }

    private ?MatrixIntersectionEngine $engineInstance = null;

    /**
     * The Part B holder blocks' time format rides the ONE shared 12-hour
     * `start ~ end` formatter (the mapper's mailTime shape) — never a
     * page-local second clock. (Lazy resolution: Livewire's factory news
     * components directly, bypassing constructor injection.)
     */
    private ?ReplacementRequestMapper $mapperInstance = null;

    private function mapper(): ReplacementRequestMapper
    {
        return $this->mapperInstance ??= app(ReplacementRequestMapper::class);
    }

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

        /* SDD occupied-cell-explanations D1 (the share law): the holder
           label map is computed ONCE per payload run and threaded into
           both the busy-vector arm and the venue-slot grid arm — the
           internal call inside busyByWeekPayload() is GONE. The payload
           gate's DECISION (measured: 397KB inline dilation vs 161KB map
           — both > the 90KB threshold) enacted the DICTIONARY fallback:
           the grid arm carries INDICES into this deduped text library
           (one copy per unique block, never per row); the busy-vector
           arm keeps its pre-existing inline strings (the frozen
           TEMP-HOLDER-LABEL shape). */
        /* cancelled-slot-reuse Part B (D8/S24): the venue-slot grid arm's
           holder resolution is now EVIDENCE-based. The slots are read once,
           the live request claims are validated through the shared strict
           core (ArrangementSlotHolders → OriginalOccurrenceReservation::
           validateRequestClaim / the mapper's anchor-span fallback), and the
           per-slot owner beats any stale class geometry; an unproven claim
           stays neutral. The label library is IDENTITY-deduped
           (`session:<id>` / `request:<id>`), so one factual holder reached
           through several arms shares one entry while two different holders
           stay distinct even with identical display strings. */
        $slots = TimeSlot::query()
            ->with('venue')
            ->where('semester_id', $semesterId)
            ->whereIn('week_number', range(1, $weeks))
            ->whereIn('status', ['occupied', 'pending'])
            ->get();

        $claims = app(ArrangementSlotHolders::class)->resolve($slots);

        $holderLabels = $this->busyCellHolderLabels($semesterId, $weeks, $claims);
        [$holderTexts, $holderIds, $identityIndex, $holderKinds] = $this->holderTextLibrary($holderLabels);

        /* the venue arm's containment view is CLASS-ONLY: a proven request
           claim names its request directly, and an unproven one stays
           neutral — class geometry must never speak over a live claim. */
        $classHolderIds = [];
        foreach ($holderIds as $weekIdx => $weekMap) {
            foreach ($weekMap as $key => $idList) {
                $classOnly = array_values(array_filter(
                    $idList,
                    fn (int $id): bool => ($holderKinds[$id] ?? '') === 'class',
                ));
                if ($classOnly !== []) {
                    $classHolderIds[$weekIdx][(string) $key] = $classOnly;
                }
            }
        }

        $requestHolderIds = [];
        foreach ($identityIndex as $identity => $libraryId) {
            if (str_starts_with((string) $identity, 'request:')) {
                $requestHolderIds[(int) substr((string) $identity, 8)] = (int) $libraryId;
            }
        }

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
            'venueSlotsByWeek' => $this->venueSlotsByWeekPayload(
                $slots,
                $claims['owners'],
                $claims['unknownSlotIds'],
                $classHolderIds,
                $requestHolderIds,
            ),
            'busyByWeek' => $this->busyByWeekPayload($sessions, $semesterId, $weeks, $holderLabels, $holderIds),
            'blockedVenues' => $this->blockedVenuesPayload($sessions),
            'pinnedSession' => $this->pinnedSessionPayload($weeks),
            /* arrange-intent-session-choice (user amendment, conflicted-only):
               for a hand-typed intent (no ?session=), the fallback must pick a
               class the system flagged for that week — same definition as the
               replacement-home list (exception / holiday / venue restriction),
               minus occurrences already under an active request (D1). */
            'holderTexts' => $holderTexts,
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
            // SDD rearrangement-toolbar-tooltips: the REAL session-type
            // letter (display-only) so the arrival anchor's picker tip
            // names the slot honestly instead of fail-opening away.
            'type' => (string) $session->session_type,
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
        $startDate = Carbon::parse($semester !== null ? $semester->start_date : LocalTime::today());
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
            ->whereIn('status', self::ACTIVE_REQUEST_STATUSES)
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
                        /* SDD conflict-schedule-header-status-tip: the
                           cancelled event rides the ONE label law (ReasonLabel)
                           additively — the strip header's hover truth reads it. */
                        'cancelledReason' => $exception !== null
                            ? ReasonLabel::from((string) $exception->reason)
                            : null,
                        'name' => $session->module->displayName(),
                        'type' => (string) $session->session_type,
                        'code' => (string) $session->module->module_code,
                    ],
                ];
            }
        }

        return $events;
    }

    /**
     * The deduped block-text library (the payload gate's dictionary
     * fallback) — PART B IDENTITY LAW: one entry per UNIQUE holder
     * IDENTITY (`session:<id>` / `request:<id>`), first-appearance order.
     * The SAME factual holder shares one entry across every arm; two
     * DIFFERENT holders keep separate entries even when their display
     * strings are identical (they stay distinct blocks in the merged tip).
     * Returns [texts, ids keyed [weekIdx][day:slotStart:slotEnd:room]
     * (index-aligned with the label entries), identity→index, index→kind].
     *
     * @param  array<int, array<string, list<array{identity: string, text: string, kind: string}>>>  $holderLabels
     * @return array{0: list<string>, 1: array<int, array<string, list<int>>>, 2: array<string, int>, 3: array<int, string>}
     */
    private function holderTextLibrary(array $holderLabels): array
    {
        $texts = [];
        $kinds = [];
        $index = [];
        $ids = [];

        foreach ($holderLabels as $weekIdx => $weekMap) {
            foreach ($weekMap as $key => $entries) {
                $rowIds = [];
                foreach ($entries as $entry) {
                    $identity = (string) $entry['identity'];
                    if (! isset($index[$identity])) {
                        $index[$identity] = count($texts);
                        $texts[] = (string) $entry['text'];
                        $kinds[] = (string) $entry['kind'];
                    }
                    $rowIds[] = $index[$identity];
                }
                $ids[$weekIdx][(string) $key] = $rowIds;
            }
        }

        return [$texts, $ids, $index, $kinds];
    }

    /**
     * Real venue slot states, week-keyed: [weekIdx][venueCode] = [[day, hourIdx,
     * status, holderIds], …] — 1 occupied, 3 pending (mine), 4 reserved
     * (others'). The 4th key rides the cell's ACTUAL holder identity as an
     * ARRAY of INDICES into the deduped holderTexts library, or null when no
     * label applies (the negative arm — tip-less, byte-identical styling).
     *
     * cancelled-slot-reuse Part B (D8/S24) — the holder is resolved from
     * EVIDENCE per slot, never from a stale id:
     *
     *   proven claim  — the owning request's library id (the live junction
     *                   receipt or the validated anchor/span fallback; the
     *                   block names the request's FULL actual span);
     *   unknown claim — null: the cell stays blocked and the tip stays
     *                   neutral (no fabricated holder, no resurrected
     *                   cancelled original);
     *   no claim      — the class-geometry containment over the CLASS-ONLY
     *                   library ids (exception'd pairs are already excluded
     *                   upstream, so a freed occurrence never speaks).
     *
     * @param  Collection<int, TimeSlot>  $slots
     * @param  array<int, int>  $slotOwners  slot id → proven owning request id
     * @param  list<int>  $unknownSlotIds  slots an active claim touches without proving it
     * @param  array<int, array<string, list<int>>>  $classHolderIds  class-only library ids keyed [weekIdx][day:startIdx:endIdx:room]
     * @param  array<int, int>  $requestHolderIds  request id → library index
     * @return array<int|string, array<int|string, list<array{int, int, int, list<int>|null}>>> The
     *                                                                                          mock's map gains the week dimension; the view's cellRender lookup is the
     *                                                                                          one documented line that reads it. Availability stays client-side (the
     *                                                                                          mock's model: green = venue-free); the server re-validates lecturer +
     *                                                                                          cohort freedom at submit (engine pre-check → FR 4.10 conflict alert).
     */
    private function venueSlotsByWeekPayload(
        Collection $slots,
        array $slotOwners,
        array $unknownSlotIds,
        array $classHolderIds,
        array $requestHolderIds,
    ): array {
        // Full-block ownership read (design D7 item 2, round-4 🔴1): the
        // anchor-keyed pluck can't see a span's tail slots — derive the
        // owner per slot from the JUNCTION (pending-geometric ONLY; approved
        // and cancelled keep the legacy column semantics elsewhere).
        $proposers = ReplacementRequestSlot::query()
            ->join('replacement_requests', 'replacement_request_slots.replacement_request_id', '=', 'replacement_requests.id')
            ->where('replacement_requests.status', 'pending')
            ->whereIn('replacement_request_slots.time_slot_id', $slots->pluck('id')->all())
            ->pluck('replacement_requests.proposer_id', 'replacement_request_slots.time_slot_id');

        $myId = (int) auth()->id();
        $unknown = array_fill_keys(array_map('intval', $unknownSlotIds), true);

        /* Part B: the class-only containment view keyed by the FULL class
           range (day:startIdx:endIdx:room); the slot grid rows are 30-minute
           half-cells. Per week, build a day+room-indexed view of [startIdx,
           endIdx, ids[]], then per slot row run the client's containment
           predicate (startIdx <= hi && hi < endIdx). The LIMIT-3 explode
           keeps the room as the remainder (room codes carry no colons) — the
           round-5 phantom guard. */
        $view = [];
        foreach ($classHolderIds as $weekIdx => $weekMap) {
            foreach ($weekMap as $key => $labelIds) {
                [$day, $startIdx, $rest] = explode(':', (string) $key, 3);
                [$endIdx, $room] = explode(':', (string) $rest, 2);
                $view[$weekIdx][(int) $day.':'.(string) $room][] = [(int) $startIdx, (int) $endIdx, (array) $labelIds];
            }
        }

        $out = [];
        foreach ($slots as $slot) {
            $status = $slot->status === 'occupied'
                ? 1
                : ((int) ($proposers->get($slot->id)) === $myId ? 3 : 4);

            $weekIdx = (int) $slot->week_number - 1;
            $day = (int) $slot->day_of_week;
            $startIdx = $this->slotIndex((string) $slot->start_time);
            $endIdx = $this->slotIndex((string) $slot->end_time);
            $room = $slot->venue !== null ? (string) $slot->venue->room_code : '';

            /* Part B: the ACTUAL holder. A proven active claim names its
               request; an unknown/malformed one stays neutral (never a
               guessed class); without a claim the class geometry speaks. */
            $picked = [];
            if (isset($unknown[$slot->id])) {
                $picked = [];
            } elseif (isset($slotOwners[$slot->id])) {
                $ownerIndex = $requestHolderIds[$slotOwners[$slot->id]] ?? null;
                $picked = $ownerIndex !== null ? [$ownerIndex] : [];
            } else {
                foreach ($view[$weekIdx][$day.':'.$room] ?? [] as [$s, $e, $ids]) {
                    if ($s <= $startIdx && $endIdx <= $e) {
                        $picked = array_merge($picked, $ids);
                    }
                }
            }

            $out[$weekIdx][$room][] = [
                $day,
                $startIdx,
                $status,
                $picked !== [] ? array_values(array_unique($picked)) : null,
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
     * @param  array<int, array<string, list<array{identity: string, text: string, kind: string}>>>  $holderLabels  the
     *                                                                                                              threaded shared holder map (identity-tagged entries per key)
     * @param  array<int, array<string, list<int>>>  $holderIds  the library ids keyed [weekIdx][day:slotStart:slotEnd:room],
     *                                                           index-aligned with $holderLabels' entries
     * @return array<int|string, array<int, list<array{int, int, int, string|null, string|null, bool, 6?: list<string>, 7?: list<int>}>>>
     */
    private function busyByWeekPayload(Collection $sessions, int $semesterId, int $weeks, array $holderLabels, array $holderIds): array
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
        /* SDD occupied-cell-explanations D1: the map is threaded IN from
           pagePayload — the internal busyCellHolderLabels() call (the
           was-here double computation) is deleted; see mount. */

        /* SDD tidy-arrangement-cell-tooltips (2026-10-27): one TUPLE per
           blocking ROW — the old `implode(' | ')` glued all the classes
           sharing one range key into a single wall-of-text; the client now
           sorts blocks per room + counts them for the conflict header, so
           each label rides its own tuple. Duplicate ranges stay harmless:
           `engineBlocked` is a some() over the tuples and the payload's
           array_unique dedup (the client-side) is per-block. */
        /* arrangement-slot-ownership-legend (2026-11-06): the 6th tuple key is
           `mine` — does this blocking row belong to the VIEWER's own calendar?
           It is what decides a cell's border thickness (3px = yours), so it
           must be data, never inferred from the holder text or the colour.
           `$defaultMine` covers the producer's own scope: the lecturer vector
           IS the viewer (true), the cohort vector is not (false) — cohort
           make-up rows override it from their proposer. */
        /* SDD arrangement-cell-state-labels D2 (user 2026-11-07, answer 1A): the
           7th tuple key is `arms` — WHICH calendar put the block there ('own' =
           the viewer's own classes/make-ups, 'cohort' = the cohorts' shared
           calendar). The cell label names the reason from this data; nothing
           parses a holder string for it any more. */
        $toRanges = function (array $busy, int $weekIdx, bool $defaultMine, array $defaultArms = []) use ($holderLabels, $holderIds): array {
            $out = [];
            foreach ($busy as $range) {
                $day = (int) $range['day'];
                $startIdx = $this->slotIndex((string) $range['start']);
                $endIdx = $this->slotIndex((string) $range['end']);
                $room = (string) ($range['room'] ?? '');
                $key = $day.':'.$startIdx.':'.$endIdx.':'.$room;
                /* SDD makeup-busy-vector round 2: the make-up rows ride
                   their status kind ('pending'|'approved'), null for the
                   class-side rows — the client's pending arm keys on it. */
                $statusKind = $range['status'] ?? null;
                $mine = (bool) ($range['mine'] ?? $defaultMine);
                $arms = array_values(array_unique((array) ($range['arms'] ?? $defaultArms)));
                /* cancelled-slot-reuse Part B: the entries are IDENTITY-tagged
                   and index-aligned with $holderIds, so each exploded tuple
                   carries its holder's canonical identity as the 8th key —
                   the merged tip dedups arms by identity, and two different
                   holders stay distinct even with identical display strings. */
                $entries = $holderLabels[$weekIdx][$key] ?? [];
                $entryIds = $holderIds[$weekIdx][$key] ?? [];
                if ($entries === []) {
                    $out[] = [$day, $startIdx, $endIdx, null, $statusKind, $mine, $arms, []];

                    continue;
                }
                foreach ($entries as $i => $entry) {
                    $out[] = [
                        $day, $startIdx, $endIdx,
                        (string) $entry['text'],
                        $statusKind, $mine, $arms,
                        isset($entryIds[$i]) ? [(int) $entryIds[$i]] : [],
                    ];
                }
            }

            return $out;
        };

        $lecturerBusy = [];
        for ($w = 1; $w <= $weeks; $w++) {
            $lecturerBusy[$w - 1] = $toRanges($this->engine()->lecturerBusyRanges($semesterId, $lecturerId, $w), $w - 1, true, ['own']);
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
                        $this->engine()->cohortBusyRanges($semesterId, $cohortIds, $w, $lecturerId),
                        $w - 1,
                        false,
                        ['cohort'],
                    );
                }
            }

            $merged = [];
            for ($w = 1; $w <= $weeks; $w++) {
                $merged[$w - 1] = $this->dedupeBusyRanges(array_merge(
                    $lecturerBusy[$w - 1],
                    $cohortBusyCache[$hash][$w - 1],
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
     * Merge blocking rows that are the SAME fact. A slot that is both the
     * viewer's own class and one of their selected cohorts' classes arrives
     * twice (lecturer vector `mine = true`, cohort vector `mine = false`) with
     * an identical `[day, start, end, label, statusKind]`. Leaving them apart
     * breaks the cell tooltip, which counts blocks (`renderHolderTip()`'s
     * header + the flat aria string): two rows would print
     * "2 classes in the way" with the block listed twice.
     *
     * So identical first five keys collapse into one row whose `mine` is the OR
     * of the twins (any-mine wins, matching the resolver's "is *any* row mine?").
     * Rows that differ in label or statusKind stay separate — they are
     * different facts (e.g. a pending and an approved make-up in one half-hour).
     * The client therefore never sees twins, and the tip stays byte-identical.
     *
     * cancelled-slot-reuse Part B: the 8th key unions too — twins that carry
     * DIFFERENT canonical identities (the same display string from two
     * different holders) survive as two identities; the same factual holder's
     * twins share one library id and stay one block.
     *
     * @param  list<array{int, int, int, string|null, string|null, bool, 6?: list<string>, 7?: list<int>}>  $ranges
     * @return list<array{int, int, int, string|null, string|null, bool, list<string>, list<int>}>
     */
    private function dedupeBusyRanges(array $ranges): array
    {
        $merged = [];

        foreach ($ranges as $range) {
            $key = implode('|', [
                (string) $range[0],
                (string) $range[1],
                (string) $range[2],
                (string) ($range[3] ?? ''),
                (string) ($range[4] ?? ''),
            ]);

            if (isset($merged[$key])) {
                $merged[$key][5] = $merged[$key][5] || (bool) $range[5];
                /* SDD arrangement-cell-state-labels D2: a twin that arrives from
                   BOTH calendars (my class that my cohort also attends) must keep
                   both reasons — `mine` ORs, `arms` unions. */
                $merged[$key][6] = array_values(array_unique(array_merge(
                    $merged[$key][6],
                    $range[6] ?? [],
                )));
                /* Part B: the holder identities union the same way. */
                $merged[$key][7] = array_values(array_unique(array_merge(
                    $merged[$key][7],
                    array_map('intval', $range[7] ?? []),
                )));

                continue;
            }

            $merged[$key] = [
                (int) $range[0], (int) $range[1], (int) $range[2],
                $range[3], $range[4], (bool) $range[5],
                $range[6] ?? [],
                array_values(array_unique(array_map('intval', $range[7] ?? []))),
            ];
        }

        return array_values($merged);
    }

    /**
     * THE holder blocks (cancelled-slot-reuse Part B, D8/S24) — one
     * IDENTITY-tagged entry per actual active holder per covered cell key,
     * [weekIdx][day:slotStart:slotEnd:room]. Two sources, one vocabulary:
     *
     *   class   — every semester session NOT exception'd for that week (a
     *             cancelled occurrence is never a holder), keyed by its FULL
     *             range; public state NORMAL;
     *   request — every active request whose claim PROVES through the shared
     *             strict core, keyed per proven half-cell with the request's
     *             FULL actual span; public state PENDING (pending) /
     *             REPLACEMENT (approved).
     *
     * Identity = `session:<id>` / `request:<id>`: the same factual holder
     * reached through several arms shares one library entry, and two
     * different holders never share one even when their display strings
     * match. Blocks carry the permitted public facts only — room · code
     * (type) · 12-hour actual time, the lecturer line, the cohorts, the
     * state word; never a remark, a private cancellation reason or a
     * submission timestamp.
     *
     * @param  array{owners: array<int, int>, cells: array<int, list<array{slot_id: int, semester_id: int, week_number: int, day_of_week: int, venue_id: int, start_time: string, end_time: string, status: string, version: int, class_session_id: int|null}>>, unknownSlotIds: list<int>, requests: array<int, ReplacementRequest>}  $claims  ArrangementSlotHolders::resolve()
     * @return array<int, array<string, list<array{identity: string, text: string, kind: string}>>>
     */
    private function busyCellHolderLabels(int $semesterId, int $weeks, array $claims): array
    {
        $out = [];

        $ex = ClassException::query()
            ->whereBetween('week_number', [1, $weeks])
            ->get(['class_session_id', 'week_number'])
            ->mapWithKeys(function (ClassException $e): array {
                return [$e->class_session_id.':'.$e->week_number => true];
            });

        /* ONE eager-loaded read (the old per-row firstWhere re-read was an
           N+1). The old inner joins dropped sessions missing a module/venue/
           lecturer-profile row — the labels keep that honesty and skip them. */
        $sessions = ClassSession::query()
            ->with(['module', 'venue', 'lecturer.lecturer', 'cohorts.programme'])
            ->where('semester_id', $semesterId)
            ->get();

        foreach ($sessions as $session) {
            if ($session->module === null || $session->venue === null || $session->lecturer === null) {
                continue;
            }
            $profile = $session->lecturer->lecturer;
            if ($profile === null) {
                continue;
            }

            $startIdx = $this->slotIndex((string) $session->start_time);
            $endIdx = $this->slotIndex((string) $session->end_time);
            if ($endIdx <= $startIdx) {
                continue;   // a non-grid range proves no half-cell
            }

            /* the key includes the ROOM CODE (round 5): classes at the
               exact same (day, start, end) in DIFFERENT rooms must not
               collide into one lookup — the phantom block from the
               bug9 follow-up receipt. */
            $key = (int) $session->day_of_week.':'.$startIdx.':'.$endIdx.':'.(string) $session->venue->room_code;
            $entry = [
                'identity' => 'session:'.(int) $session->id,
                'text' => $this->holderBlock(
                    (string) $session->venue->room_code,
                    (string) $session->module->module_code,
                    (string) $session->session_type,
                    (string) $session->start_time,
                    (string) $session->end_time,
                    (int) $session->lecturer_id,
                    (string) $session->lecturer->name,
                    (string) $profile->staff_id,
                    $session->cohorts->map(fn (Cohort $c): string => $this->cohortCode($c))->implode(', '),
                    'NORMAL',
                ),
                'kind' => 'class',
            ];

            for ($w = 1; $w <= $weeks; $w++) {
                if (isset($ex[(int) $session->id.':'.$w])) {
                    continue;   // the week's exception'd pairs are not busy (the engine's own semantics)
                }
                $out[$w - 1][$key][] = $entry;
            }
        }

        /* The PROVEN active requests (the live receipt / the mapper's full
           fallback span beats any stale class id): ONE block per request —
           its FULL actual span — riding every proven half-cell key. A
           refused claim produces NO label anywhere (the strict unknown →
           neutral law; the busy rows it would have labelled simply fall
           back to the neutral arms). */
        $cells = $claims['cells'];

        if ($cells !== []) {
            $roomCodes = Venue::query()
                ->whereIn('id', collect($cells)->flatten(1)->pluck('venue_id')->unique()->all())
                ->pluck('room_code', 'id');

            foreach ($cells as $requestId => $requestCells) {
                $request = $claims['requests'][$requestId] ?? null;
                if (! $request instanceof ReplacementRequest || $requestCells === []) {
                    continue;
                }
                $source = $request->classSession;
                if ($source === null || $source->module === null
                    || $source->lecturer === null || $source->lecturer->lecturer === null) {
                    continue;
                }

                $state = (string) $request->status === 'approved' ? 'REPLACEMENT' : 'PENDING';
                /* the FULL actual span: the proven run's min start / max end
                   ('HH:MM:SS' strings compare lexically, zero-padded) */
                $start = (string) $requestCells[0]['start_time'];
                $end = (string) $requestCells[0]['end_time'];
                foreach ($requestCells as $cell) {
                    if ((string) $cell['start_time'] < $start) {
                        $start = (string) $cell['start_time'];
                    }
                    if ((string) $cell['end_time'] > $end) {
                        $end = (string) $cell['end_time'];
                    }
                }
                $room = (string) ($roomCodes[(int) $requestCells[0]['venue_id']] ?? '');
                if ($room === '') {
                    continue;   // an unreadable landing room proves no label
                }

                $entry = [
                    'identity' => 'request:'.(int) $requestId,
                    'text' => $this->holderBlock(
                        $room,
                        (string) $source->module->module_code,
                        (string) $source->session_type,
                        (string) $start,
                        (string) $end,
                        (int) $source->lecturer_id,
                        (string) $source->lecturer->name,
                        (string) $source->lecturer->lecturer->staff_id,
                        $source->cohorts->map(fn (Cohort $c): string => $this->cohortCode($c))->implode(', '),
                        $state,
                    ),
                    'kind' => 'request',
                ];

                foreach ($requestCells as $cell) {
                    $w = (int) $cell['week_number'];
                    if ($w < 1 || $w > $weeks) {
                        continue;
                    }
                    $cellStartIdx = $this->slotIndex((string) $cell['start_time']);
                    $cellEndIdx = $this->slotIndex((string) $cell['end_time']);
                    if ($cellEndIdx <= $cellStartIdx) {
                        continue;
                    }
                    $cellKey = (int) $cell['day_of_week'].':'.$cellStartIdx.':'.$cellEndIdx.':'.(string) ($roomCodes[(int) $cell['venue_id']] ?? '');
                    if (str_ends_with($cellKey, ':')) {
                        continue;   // an unreadable cell room proves no label
                    }
                    $out[$w - 1][$cellKey][] = $entry;
                }
            }
        }

        return $out;
    }

    /**
     * ONE holder block (the tidy-arrangement-cell-tooltips shape, Part B
     * copy): `ROOM · CODE (TYPE) · start ~ end` / the lecturer line (the
     * 'You — ' ownership prefix) / the cohorts / the final PUBLIC state word
     * (NORMAL · PENDING · REPLACEMENT — the one shared vocabulary, D8).
     * Times use the common 12-hour `start ~ end` joiner — the ONE shared
     * formatter (`ReplacementRequestMapper::mailTime`'s shape), never a
     * page-local second clock. No remark, no private cancellation reason,
     * no submission metadata — the permitted public facts only.
     */
    private function holderBlock(
        string $roomCode,
        string $code,
        string $type,
        string $startTime,
        string $endTime,
        int $lecturerId,
        string $lecturerName,
        string $staffId,
        string $cohorts,
        string $state,
    ): string {
        $mine = $lecturerId === (int) auth()->id();

        return $roomCode
            .' · '.$code.' ('.$type.')'
            .' · '.$this->holderTimeRange($startTime, $endTime)
            ."\n".($mine ? 'You — ' : '')
            .$lecturerName.' ('.$staffId.')'
            ."\n".($cohorts !== '' ? $cohorts : '—')
            ."\n".$state;
    }

    /**
     * The holder block's ACTUAL time — the common 12-hour `start ~ end`
     * range, delegated to the ONE shared formatter (the mapper's mailTime).
     */
    private function holderTimeRange(string $start, string $end): string
    {
        return (string) $this->mapper()->mailTime(['start' => $start, 'end' => $end]);
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
                // my-replacements-after-submit (2026-11-05 user): the created
                // request's id rides along so the redirect can carry it and the
                // new row flashes on arrival. Additive only — nothing else reads
                // this array; failures keep it null.
                'request_id' => $result['ok'] ? ($result['request_id'] ?? null) : null,
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
            /* FR 2.17's server gate (2026-11-06) — the SAME sentence the grid's
               read-only cell shows, so the rule reads one way everywhere. */
            'slot_too_soon' => 'Slots must be at least 3 working days from today.',
            'invalid_span' => 'Blocks are 30 minutes to 2 hours — adjust the selection.',
            'engine_precheck_failed' => 'You or the cohort is no longer free at that time.',
            'reserve_interrupted' => 'The booking could not be completed — try again.',
            'duplicate_active_request' => 'This occurrence already has an active replacement request — see My Replacements.',
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
