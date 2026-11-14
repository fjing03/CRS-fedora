<?php

namespace Tests\Feature;

use App\Actions\CancelClass;
use App\Livewire\CohortTimetable;
use App\Livewire\MyTimetable;
use App\Livewire\ReplacementHistory;
use App\Livewire\ReplacementHome;
use App\Livewire\StudentMyTimetable;
use App\Livewire\VenueTimetable;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Notification;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\ReplacementRequestMapper;
use App\Support\CohortLabel;
use App\Support\LocalTime;
use App\Support\Notifications\NotificationTip;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SDD approved-replacement-cancellation, task T4 — the isolated backend
 * regression for cancelling an APPROVED replacement through the shared
 * Cancel Class paths (single `cancelClass` + day `cancelClassDay`).
 *
 * Frozen contract under test (design D1–D3, specs R1–R4):
 *   · the opened Approved identity is (request id + SOURCE session + LANDING
 *     week/day) — never inferred from the session alone, never a normal
 *     fallback; a wrong coordinate/owner/state/semester refuses;
 *   · the server window is enforced on the LANDING date (even for the single
 *     path), never on the old source date — an old source with an in-window
 *     landing still cancels; a stale landing refuses;
 *   · exactly the proven landing run (1/2/4 cells, or the legacy anchor+span
 *     fallback) releases ONCE — available, owner cleared, version +1, junction
 *     links deleted; replacement_span/approver/source exception/reason/audit
 *     stay untouched and no fabricated `class_cancelled` audit joins them;
 *   · the request persists `cancelled` + decided_at and a legal `cancelled`
 *     audit linked to the exact request with the reason, the trimmed Other
 *     detail and the actual landing schedule;
 *   · single and day selections share the batch/lock law: one unresolvable
 *     normal id refuses the whole selection; repeated ids normalize; a later
 *     failure rolls back EVERYTHING; refusals/rollbacks notify nobody;
 *   · request-linked notification rows carry the ACTUAL landing schedule and
 *     never the old source reason or the private Other detail; the tooltip
 *     uses the request's own span (the legacy fallback after the release);
 *   · after a REAL cancellation all four timetables drop the landing and
 *     restore the source history; My Replacements shows the no-request state
 *     and the student page shows Awaiting Arrangement.
 *
 * World: minimal, deterministic, RefreshDatabase — built in the
 * CancelledSlotFixture / CancelledSlotTimelineTest style (frozen campus
 * clock, no demo seed, no fixed manual ids). Every source occurrence is
 * cancelled FOR REAL through the shipped {@see CancelClass} action, so each
 * case's exception + audit + released originals are the REAL persisted
 * end-state the approval family leaves behind.
 *
 * Known shape notes (honest, per the frozen artifacts):
 *   · the receipt/audit detail KEY NAMES inside `details` are T1's
 *     implementation freedom — those assertions pin the FACTS (values), not
 *     invented keys, except where shipped keys already fix them;
 *   · a Sunday landing is unconstructible through valid persistence
 *     (`time_slots.day_of_week` CHECK 0–5), so the Sunday gate is covered
 *     here only by the day-range refusal.
 */
final class ApprovedReplacementCancellationTest extends TestCase
{
    use RefreshDatabase;

    /** The frozen campus now: Mon 8 Feb 2027, 09:00 MYT (Monday of week 2). */
    private const FROZEN_NOW = '2027-02-08 09:00:00';

    /** Week 2 — the reported source occurrence's week (Thu 11 Feb 2027). */
    private const SOURCE_WEEK = 2;

    /** Week 1 — before the 2-working-day window (Mon 1 Feb 2027). */
    private const OLD_SOURCE_WEEK = 1;

    private User $owner;

    private User $other;

    private User $studentUser;

    private Semester $semester;

    private Semester $otherSemester;

    private Module $module;

    private Cohort $cohortA;

    private Cohort $cohortB;

    private Venue $v1;

    private Venue $v2;

    private Venue $v3;

    private Venue $v4;

    /** The reported-case source session + its approved request. */
    private ClassSession $report;

    private ReplacementRequest $reportRequest;

    private ClassSession $peer;

    private ClassSession $foreignSession;

    /** Every named approved-target case: key => ['session', 'request', 'landing']. */
    private array $approved = [];

    private ReplacementRequest $pendingWithdrawal;

    private int $setupNotificationWatermark = 0;

    protected function setUp(): void
    {
        parent::setUp();

        /* the frozen campus clock holds through the WHOLE test — setUp only
           builds the world; tearDown clears it. If the world itself fails to
           build, clear the clock before rethrowing so nothing leaks past the
           fixture failure. */
        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW, LocalTime::TZ));

        try {
            $this->buildWorld();
        } catch (\Throwable $e) {
            Carbon::setTestNow();
            throw $e;
        }

        $this->setupNotificationWatermark = (int) (Notification::query()->max('id') ?? 0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ════════════════════════════ world ════════════════════════════ */

    private function buildWorld(): void
    {
        $this->semester = Semester::create([
            'semester_code' => 'APR2027',
            'label' => 'Approved Cancel Fixture',
            'start_date' => '2027-02-01', // a MONDAY
            'end_date' => '2027-05-30',
            'week_count' => 14,
        ]);
        $this->otherSemester = Semester::create([
            'semester_code' => 'APR2077',
            'label' => 'Approved Cancel Fixture Other',
            'start_date' => '2077-06-07',
            'end_date' => '2077-09-05',
            'week_count' => 14,
        ]);

        $faculty = Faculty::create(['faculty_code' => 'APRF', 'faculty_name' => 'Approved Fixture Faculty']);
        $dept = Department::create(['dept_code' => 'APRD', 'dept_name' => 'Approved Fixture Dept', 'faculty_id' => $faculty->id]);
        $prog = Programme::create(['programme_code' => 'APRP', 'programme_name' => 'Approved Fixture Programme', 'faculty_id' => $faculty->id]);

        $this->owner = $this->lecturer('a', 'Fixture Lecturer A', $dept);
        $this->other = $this->lecturer('b', 'Fixture Lecturer B', $dept);

        $this->cohortA = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2026/27', 'intake' => 'June 2024', 'student_count' => 20,
        ]);
        $this->cohortB = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 2, 'academic_year' => '2026/27', 'intake' => 'June 2024', 'student_count' => 12,
        ]);

        $this->studentUser = User::factory()->create(['role' => 'student', 'name' => 'Fixture Student']);
        Student::create(['user_id' => $this->studentUser->id, 'student_id' => '27APR0001', 'cohort_id' => $this->cohortA->id]);

        $this->v1 = $this->venue('B-APR-A');
        $this->v2 = $this->venue('B-APR-B');
        $this->v3 = $this->venue('B-APR-C');
        $this->v4 = $this->venue('B-APR-D');

        $this->module = Module::create([
            'module_code' => 'BMM101', 'module_name' => 'Fixture Module BMM101',
            'allowed_session_types' => 'L,T,P',
        ]);

        // ── the reported-case source: Thu 13:30–14:30 at B-APR-A ──
        // Its week-2 occurrence is cancelled FOR REAL (exception + audit +
        // released originals), then the approved landing re-books the released
        // 13:30 cell plus a fresh 13:00 cell — the reported half-hour overlap.
        $this->report = $this->makeSession($this->owner, $this->v1, 3, '13:30:00', '14:30:00', [$this->cohortA->id]);
        $released = $this->grid($this->report, self::SOURCE_WEEK);   // real originals
        $this->realCancel($this->report, self::SOURCE_WEEK);        // releases those very models
        $reused = $released[0]->refresh();                          // 13:30, available v2, owner null
        $reused->forceFill(['status' => 'occupied', 'version' => 3])->save();
        [$request] = $this->approvedRequest($this->report, self::SOURCE_WEEK, $this->v1, self::SOURCE_WEEK, 3, '13:00:00', 2, $reused);
        $this->reportRequest = $request;

        // ── the normal day class + the foreign class (unrelated holdings) ──
        $this->peer = $this->makeSession($this->owner, $this->v1, 3, '15:00:00', '16:00:00', [$this->cohortB->id]);
        $this->grid($this->peer, self::SOURCE_WEEK);
        $this->foreignSession = $this->makeSession($this->other, $this->v2, 3, '16:00:00', '17:00:00', [$this->cohortB->id]);
        $this->grid($this->foreignSession, self::SOURCE_WEEK);

        // ── the remaining approved-target cases, each on its OWN source
        //    occurrence (one live claim per occurrence is the real world's
        //    shape, and the batch's attached-ambiguity law relies on it) ──
        // key => [source venue, source day, source start, source cells,
        //         landing venue, landing week, landing day, landing start, landing cells, junctions]
        $cases = [
            'cross' => [$this->v2, 1, '09:00:00', 2, $this->v3, 8, 4, '14:00:00', 2, true],
            'four' => [$this->v3, 2, '09:00:00', 2, $this->v4, 5, 3, '09:00:00', 4, true],
            'one' => [$this->v4, 4, '09:00:00', 1, $this->v1, 6, 3, '13:30:00', 1, true],
            'legacy' => [$this->v4, 2, '10:00:00', 1, $this->v4, 9, 3, '10:00:00', 4, false],
            'stale' => [$this->v3, 4, '09:00:00', 1, $this->v3, self::OLD_SOURCE_WEEK, 1, '09:00:00', 1, true],
            'holiday' => [$this->v3, 1, '11:00:00', 1, $this->v3, 10, 3, '13:30:00', 1, true],
            'holder' => [$this->v4, 1, '11:00:00', 2, $this->v4, 11, 3, '09:00:00', 2, true],
            'drift' => [$this->v4, 4, '11:00:00', 1, $this->v4, 12, 3, '09:00:00', 1, true],
            'old' => [$this->v2, 0, '09:00:00', 1, $this->v2, 5, 4, '09:00:00', 2, true],
        ];

        foreach ($cases as $key => [$venue, $day, $start, $span, $landingVenue, $landingWeek, $landingDay, $landingStart, $landingSpan, $junctions]) {
            $source = $this->makeSession($this->owner, $venue, $day, $start, $this->endOf($start, $span), [$this->cohortA->id]);
            $this->grid($source, self::SOURCE_WEEK);

            $sourceWeek = self::SOURCE_WEEK;
            if ($key === 'old') {
                // the OLD source: its week-1 occurrence cancelled for real —
                // the legacy recurring path never enforced the window, so the
                // exception/audit evidence is real history; the week-2
                // occurrence still runs untouched
                $sourceWeek = self::OLD_SOURCE_WEEK;
                $this->grid($source, self::OLD_SOURCE_WEEK);   // the week-1 originals realCancel releases
                $this->realCancel($source, self::OLD_SOURCE_WEEK);
            } else {
                $this->realCancel($source, self::SOURCE_WEEK);
            }

            [$request, $cells] = $this->approvedRequest($source, $sourceWeek, $landingVenue, $landingWeek, $landingDay, $landingStart, $landingSpan, null, $junctions);

            $this->approved[$key] = ['session' => $source, 'request' => $request, 'landing' => $cells];
        }

        // drift: the landing cell no longer holds the claim state — the
        // receipt is stale there and the release must refuse, never shrink
        $this->approved['drift']['landing'][0]->forceFill(['status' => 'available'])->save();

        // the holiday landing's calendar row
        Holiday::create([
            'semester_id' => $this->semester->id,
            'week_number' => 10,
            'day_of_week' => 3,
            'label' => 'Fixture Holiday',
        ]);

        // the competing holder: ANOTHER live (pending) request — the legacy
        // junction-less anchor shape, because the per-slot partial unique
        // rightly forbids a second junction on an already-claimed cell —
        // claims the second cell of the `holder` landing. A newer holder is
        // never released, so cancelling that approved target must refuse.
        $pinSession = $this->makeSession($this->owner, $this->v2, 0, '08:00:00', '09:00:00', [$this->cohortA->id]);
        ClassException::create(['class_session_id' => $pinSession->id, 'week_number' => 4, 'reason' => 'official_event']);
        $holderCells = $this->approved['holder']['landing'];
        $this->makeRequest($pinSession, 4, $holderCells[1], 1, 'pending', false);

        // the semester-mismatch request: source session in the ACTIVE
        // semester, but the request row (and its landing) claim the other one
        $semSource = $this->makeSession($this->owner, $this->v1, 4, '11:00:00', '11:30:00', [$this->cohortA->id]);
        $this->grid($semSource, self::SOURCE_WEEK);   // real originals first, then realCancel releases them
        $this->realCancel($semSource, self::SOURCE_WEEK);
        $semCell = $this->cell($this->v1, self::SOURCE_WEEK, 3, '11:00:00', 'occupied', null, 1, $this->otherSemester->id);
        $this->approved['sem'] = [
            'session' => $semSource,
            'request' => $this->makeRequest($semSource, self::SOURCE_WEEK, $semCell, 1, 'approved', true, $this->otherSemester->id),
            'landing' => [$semCell],
        ];

        // a live PENDING withdrawal whose make-up block falls on the reported
        // day (the mixed day selection's ticked make-up row)
        $this->pendingWithdrawal = $this->pendingRequest($this->peer, 4, $this->v2, self::SOURCE_WEEK, 3, '17:00:00', 1);
    }

    private function lecturer(string $key, string $name, Department $dept): User
    {
        $user = User::factory()->create([
            'role' => 'lecturer',
            'name' => $name,
            'email' => 'apr-'.strtolower($key).'@apr-fixture.test',
        ]);
        Lecturer::create(['user_id' => $user->id, 'staff_id' => 'APR'.strtoupper($key), 'dept_id' => $dept->id, 'is_pl' => false]);

        return $user;
    }

    private function venue(string $code): Venue
    {
        return Venue::create([
            'room_code' => $code, 'room_name' => 'Fixture Room '.$code,
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T,P', 'capacity' => 40,
        ]);
    }

    private function makeSession(User $lecturer, Venue $venue, int $day, string $start, string $end, array $cohortIds): ClassSession
    {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'venue_id' => $venue->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach($cohortIds);

        return $session;
    }

    private function endOf(string $start, int $span): string
    {
        return Carbon::parse($start)->addMinutes(30 * $span)->format('H:i:s');
    }

    private function cell(Venue $venue, int $week, int $day, string $start, string $status, ?ClassSession $owner, int $version, ?int $semesterId = null): TimeSlot
    {
        return TimeSlot::create([
            'semester_id' => $semesterId ?? (int) $this->semester->id,
            'class_session_id' => $owner?->id,
            'week_number' => $week,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => Carbon::parse($start)->addMinutes(30)->format('H:i:s'),
            'venue_id' => $venue->id,
            'status' => $status,
            'version' => $version,
        ]);
    }

    /** The session's own original grid for one week (its full 30-minute run). */
    private function grid(ClassSession $session, int $week): array
    {
        $span = (int) (Carbon::parse((string) $session->start_time)->diffInMinutes(Carbon::parse((string) $session->end_time)) / 30);
        $cells = [];
        for ($k = 0; $k < $span; $k++) {
            $cells[] = $this->cell(
                $session->venue, $week, (int) $session->day_of_week,
                Carbon::parse((string) $session->start_time)->addMinutes($k * 30)->format('H:i:s'),
                'occupied', $session, 1,
            );
        }

        return $cells;
    }

    /** The REAL single-class cancellation — the shipped action, real evidence. */
    private function realCancel(ClassSession $session, int $week): void
    {
        $this->assertTrue(
            (new CancelClass)($this->owner, $session, $week, 'medical_leave'),
            'the fixture source cancellation was refused — the world cannot proceed',
        );
    }

    /**
     * An APPROVED request over freshly created occupied landing cells, with
     * its junction receipt (unless `$junctions` is false — the legacy
     * anchor+stored-span shape the mapper fallback owns).
     *
     * @return array{0: ReplacementRequest, 1: list<TimeSlot>}
     */
    private function approvedRequest(
        ClassSession $source,
        int $sourceWeek,
        Venue $venue,
        int $week,
        int $day,
        string $start,
        int $span,
        ?TimeSlot $reuseFirstCell = null,
        bool $junctions = true,
    ): array {
        $cells = [];

        for ($k = 0; $k < $span; $k++) {
            $cellStart = Carbon::parse($start)->addMinutes($k * 30)->format('H:i:s');
            if ($reuseFirstCell !== null
                && Carbon::parse((string) $reuseFirstCell->start_time)->equalTo(Carbon::parse($cellStart))) {
                $cells[] = $reuseFirstCell;

                continue;
            }
            $cells[] = $this->cell($venue, $week, $day, $cellStart, 'occupied', null, 1);
        }

        $request = $this->makeRequest($source, $sourceWeek, $cells[0], $span, 'approved', $junctions, null, $cells);

        return [$request, $cells];
    }

    private function makeRequest(
        ClassSession $source,
        int $sourceWeek,
        TimeSlot $anchor,
        int $span,
        string $status,
        bool $junctions,
        ?int $semesterId = null,
        ?array $junctionCells = null,
    ): ReplacementRequest {
        $request = ReplacementRequest::create([
            'semester_id' => $semesterId ?? (int) $this->semester->id,
            'proposer_id' => (int) $source->lecturer_id,
            'class_session_id' => (int) $source->id,
            'week_number' => $sourceWeek,
            'replacement_time_slot_id' => (int) $anchor->id,
            'replacement_span' => $span,
            'status' => $status,
            'approver_id' => $status === 'pending' ? null : (int) $this->other->id,
            'submitted_at' => now(),
            'decided_at' => $status === 'pending' ? null : now(),
        ]);

        if ($junctions) {
            foreach ($junctionCells ?? [$anchor] as $cell) {
                ReplacementRequestSlot::create([
                    'replacement_request_id' => $request->id,
                    'time_slot_id' => (int) $cell->id,
                ]);
            }
        }

        return $request;
    }

    /** A live PENDING request + its pending landing cell (a withdrawable row). */
    private function pendingRequest(ClassSession $source, int $sourceWeek, Venue $venue, int $week, int $day, string $start, int $span): ReplacementRequest
    {
        $cells = [];
        for ($k = 0; $k < $span; $k++) {
            $cells[] = $this->cell($venue, $week, $day, Carbon::parse($start)->addMinutes($k * 30)->format('H:i:s'), 'pending', null, 1);
        }

        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => (int) $source->lecturer_id,
            'class_session_id' => (int) $source->id,
            'week_number' => $sourceWeek,
            'replacement_time_slot_id' => (int) $cells[0]->id,
            'replacement_span' => $span,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        foreach ($cells as $cell) {
            ReplacementRequestSlot::create(['replacement_request_id' => $request->id, 'time_slot_id' => (int) $cell->id]);
        }

        return $request;
    }

    /* ══════════════════════════ callers ══════════════════════════ */

    /** The new single approved shape: (sourceSessionId, LANDING week, reason, detail, requestId, LANDING day). */
    private function cancelApproved(ClassSession $session, int $landingWeek, int $landingDay, string $reason, ?string $detail, ReplacementRequest $request, ?User $as = null): array
    {
        $this->actingAs($as ?? $this->owner);

        return (new MyTimetable)->cancelClass(
            (string) $session->id, $landingWeek, $reason, $detail, (int) $request->id, $landingDay,
        );
    }

    /** (week, day, reason, detail, pendingIds, normalSessionIds, approvedIds). */
    private function cancelDay(int $week, int $day, string $reason, ?string $detail, array $pendingIds = [], array $sessionIds = [], array $approvedIds = [], ?User $as = null): array
    {
        $this->actingAs($as ?? $this->owner);

        return (new MyTimetable)->cancelClassDay($week, $day, $reason, $detail, $pendingIds, $sessionIds, $approvedIds);
    }

    /* ══════════════════════════ assertion helpers ══════════════════════════ */

    /** The single response's cancelled receipt row (the client contract: res.cancelled). */
    private function receiptRow(array $res): array
    {
        $this->assertTrue((bool) ($res['ok'] ?? false), 'the approved cancellation answers ok: '.json_encode($res));
        $this->assertArrayHasKey('cancelled', $res, 'the single approved response carries the cancelled receipt');
        $this->assertIsArray($res['cancelled']);
        $this->assertCount(1, $res['cancelled'], 'exactly one acknowledged receipt row');
        $this->assertNull($res['exceptionId'] ?? null, 'an approved cancellation creates no source exception');

        return $res['cancelled'][0];
    }

    private function assertApprovedReceipt(array $row, ClassSession $session, ReplacementRequest $request, int $landingWeek, int $landingDay, string $start, string $end, Venue $venue): void
    {
        $this->assertSame((int) $session->id, (int) $row['session_id'], 'the receipt names the SOURCE session');
        $this->assertSame((int) $request->week_number, (int) $row['week'], 'the receipt week is the SOURCE week');
        $this->assertSame((int) $request->id, (int) $row['request_id'], 'the receipt carries the request id');
        $this->assertTrue(! isset($row['exception_id']) || $row['exception_id'] === null, 'no exception rides an approved receipt');
        $this->assertSame($landingWeek, (int) $row['landing_week'], 'the receipt names the actual landing week');
        $this->assertSame($landingDay, (int) $row['landing_day'], 'the receipt names the actual landing day');
        $this->assertSame(substr($start, 0, 5), (string) $row['start']);
        $this->assertSame(substr($end, 0, 5), (string) $row['end']);
        $this->assertSame((string) $this->module->module_code, (string) $row['code']);
        $this->assertSame('L', (string) $row['type']);
        $this->assertSame((string) $venue->room_code, (string) $row['venue']);
        $this->assertIsArray($row['cohorts']);
        $this->assertContains(CohortLabel::spaced($this->cohortA), array_map('strval', $row['cohorts']));
    }

    /**
     * @param  array<int, array{status: string, version: int}>  $before
     * @param  list<TimeSlot>  $cells
     */
    private function assertReleased(array $cells, array $before): void
    {
        foreach ($cells as $cell) {
            $cell->refresh();
            $this->assertSame('available', (string) $cell->status, 'the proven landing cell is available');
            $this->assertNull($cell->class_session_id, 'the owner pointer is cleared');
            $this->assertSame(
                $before[(int) $cell->id]['version'] + 1,
                (int) $cell->version,
                'version incremented EXACTLY once',
            );
        }
    }

    /**
     * @param  array<int, array{status: string, version: int}>  $before
     * @param  list<TimeSlot>  $cells
     */
    private function assertUntouched(array $cells, array $before): void
    {
        foreach ($cells as $cell) {
            $cell->refresh();
            $this->assertSame($before[(int) $cell->id]['status'], (string) $cell->status);
            $this->assertSame($before[(int) $cell->id]['version'], (int) $cell->version);
        }
    }

    /**
     * Slot facts captured BEFORE an action: id => {status, version}.
     *
     * @param  list<TimeSlot>  $cells
     * @return array<int, array{status: string, version: int}>
     */
    private function capture(array $cells): array
    {
        $facts = [];
        foreach ($cells as $cell) {
            $cell->refresh();
            $facts[(int) $cell->id] = ['status' => (string) $cell->status, 'version' => (int) $cell->version];
        }

        return $facts;
    }

    /** The six business families, serialised for a cheap deep compare. */
    private function businessSnapshot(): array
    {
        $rows = [];
        foreach ([
            'exceptions' => ClassException::query(),
            'audits' => AuditLog::query(),
            'requests' => ReplacementRequest::query(),
            'junctions' => ReplacementRequestSlot::query(),
            'slots' => TimeSlot::query(),
            'sessions' => ClassSession::query(),
        ] as $key => $query) {
            $rows[$key] = $query->orderBy('id')->get()
                ->map(static fn ($row) => $row->attributesToArray())
                ->all();
        }

        return $rows;
    }

    private function assertSnapshotUnchanged(array $before): void
    {
        $this->assertSame($before, $this->businessSnapshot(), 'a refusal must not write anything');
    }

    /**
     * Whether a details blob mentions every value — the FACT-level audit
     * assertion (the frozen design leaves the exact key names to the
     * implementation, so the tests pin the facts, never invented keys).
     */
    private function detailsMention(array $details, string ...$values): bool
    {
        $json = json_encode($details) ?: '';

        foreach ($values as $value) {
            if (! str_contains($json, $value)) {
                return false;
            }
        }

        return true;
    }

    private function approvedAuditFor(ReplacementRequest $request): AuditLog
    {
        $audit = AuditLog::query()
            ->where('action', 'cancelled')
            ->where('replacement_request_id', (int) $request->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit, 'the legal `cancelled` audit exists for the exact request');
        $this->assertSame('approved', (string) $audit->old_status, 'the audit records the Approved → cancelled identity');
        $this->assertSame((int) $request->replacement_time_slot_id, (int) $audit->time_slot_id, 'the audit anchors on the request\'s own anchor cell');

        return $audit;
    }

    /** The class_cancelled audit count for one (session, week) source pair. */
    private function sourceAuditCount(ClassSession $session, int $week): int
    {
        return AuditLog::query()
            ->where('action', 'class_cancelled')
            ->where('details->class_session_id', (string) $session->id)
            ->where('details->week_number', (string) $week)
            ->count();
    }

    /** The fan-out rows written after setup. */
    private function newNotifications(): array
    {
        return Notification::query()
            ->where('id', '>', $this->setupNotificationWatermark)
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function assertNoNewNotifications(): void
    {
        $this->assertSame(0, Notification::query()->where('id', '>', $this->setupNotificationWatermark)->count(), 'no fan-out on a refusal/rollback');
    }

    private function notificationsSince(int $watermark): int
    {
        return Notification::query()->where('id', '>', $watermark)->count();
    }

    /* ══════════════ the reported same-week partial-overlap case ══════════════ */

    public function test_the_reported_partial_overlap_landing_cancels_and_keeps_the_source_history_intact(): void
    {
        // the world shape the report describes: the source occurrence Thu
        // 13:30–14:30 is ALREADY cancelled (real exception + audit + released
        // originals) and its approved landing 13:00–14:00 sits in the SAME
        // week/day/room, overlapping the released 13:30 cell
        $this->assertSame(1, $this->sourceAuditCount($this->report, self::SOURCE_WEEK));

        $landing = $this->approvedLandingCells($this->reportRequest);
        $before = $this->capture($landing);
        $snapshot = $this->businessSnapshot();
        $releasedOriginal = TimeSlot::query()
            ->where('semester_id', $this->semester->id)->where('week_number', self::SOURCE_WEEK)
            ->where('day_of_week', 3)->where('venue_id', $this->v1->id)
            ->where('start_time', '14:00:00')->firstOrFail();
        $originalBefore = $this->capture([$releasedOriginal]);

        $res = $this->cancelApproved($this->report, self::SOURCE_WEEK, 3, 'medical_leave', null, $this->reportRequest);
        $row = $this->receiptRow($res);

        $this->assertApprovedReceipt($row, $this->report, $this->reportRequest, self::SOURCE_WEEK, 3, '13:00:00', '14:00:00', $this->v1);

        // exactly the proven 2-cell run released once; the released original
        // OUTSIDE the claim (the 14:00 cell) stays as it was
        $this->assertReleased($landing, $before);
        $releasedOriginal->refresh();
        $this->assertSame('available', (string) $releasedOriginal->status);
        $this->assertSame($originalBefore[(int) $releasedOriginal->id]['version'], (int) $releasedOriginal->version);

        // the request persists Cancelled with its evidence
        $this->reportRequest->refresh();
        $this->assertSame('cancelled', (string) $this->reportRequest->status);
        $this->assertNotNull($this->reportRequest->decided_at);
        $this->assertSame(2, (int) $this->reportRequest->replacement_span, 'the stored span is retained');
        $this->assertSame((int) $this->other->id, (int) $this->reportRequest->approver_id, 'approval evidence is retained');
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $this->reportRequest->id)->count(), 'the junction links are gone');

        // the source exception + audit + history are untouched; no fabricated
        // source-occurrence audit joins them
        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $this->report->id)->where('week_number', self::SOURCE_WEEK)
            ->where('reason', 'medical_leave')->count(), 'the source exception survives');
        $this->assertSame(1, $this->sourceAuditCount($this->report, self::SOURCE_WEEK), 'no fabricated class_cancelled audit');
        $this->assertSame($snapshot['exceptions'], $this->businessSnapshot()['exceptions'], 'no exception row was created or changed');

        // the legal cancelled audit: exact request identity + reason + landing facts
        $audit = $this->approvedAuditFor($this->reportRequest);
        $this->assertTrue($this->detailsMention(
            $audit->details ?? [],
            (string) $this->reportRequest->id,
            (string) $this->report->id,
            'medical_leave',
            '13:00', '14:00',
        ), 'the audit names the request, the source occurrence, the reason and the actual landing schedule');

        // the fan-out: the actor's record row + the cohort student, both
        // request-linked with the actual landing schedule
        $rows = $this->newNotifications();
        $this->assertNotEmpty($rows);
        foreach ($rows as $n) {
            $this->assertSame('Scheduled class cancelled', (string) $n->title);
            // R4: the request-linked body carries the FULL actual landing span
            $this->assertSame('BMM101 (L) · W2 · Thu · B-APR-A · 1:00 PM ~ 2:00 PM · cancelled', (string) $n->body);
            $this->assertSame(self::SOURCE_WEEK, (int) $n->week);
            $this->assertNull($n->class_session_id, 'the unsaved projection is never stored by session id');
            $this->assertSame((int) $this->reportRequest->id, (int) $n->request_id, 'the rows link the request');
        }
        $this->assertTrue(collect($rows)->contains(fn (Notification $n) => (int) $n->user_id === (int) $this->owner->id && (string) $n->role_view === 'lecturer'));
        $this->assertTrue(collect($rows)->contains(fn (Notification $n) => (int) $n->user_id === (int) $this->studentUser->id && (string) $n->role_view === 'student'));
    }

    /* ══════════════ old source vs landing window ══════════════ */

    public function test_an_old_source_outside_the_window_still_cancels_an_in_window_landing(): void
    {
        $case = $this->approved['old'];
        $before = $this->capture($case['landing']);

        // explicit precondition: the old source occurrence is the week-1
        // MONDAY — 2027-02-01, strictly EARLIER than the Thu 4 Feb 2027
        // two-working-day cutoff the frozen clock (Mon 8 Feb) implies — so
        // this case genuinely proves "old source, in-window landing"
        $this->assertSame(0, (int) $case['session']->day_of_week, 'the old source runs on MONDAY of week 1');
        $this->assertTrue(
            Carbon::parse('2027-02-01', LocalTime::TZ)->startOfDay()
                ->lt(Carbon::parse('2027-02-04', LocalTime::TZ)->startOfDay()),
            'Mon 1 Feb 2027 is earlier than the Thu 4 Feb 2027 cutoff',
        );

        $res = $this->cancelApproved($case['session'], 5, 4, 'medical_leave', null, $case['request']);
        $row = $this->receiptRow($res);

        // the receipt week is the SOURCE week (1); the landing week/day are
        // the actual ones — history keeps the old occurrence, the landing now
        $this->assertSame(self::OLD_SOURCE_WEEK, (int) $row['week']);
        $this->assertSame(5, (int) $row['landing_week']);
        $this->assertSame(4, (int) $row['landing_day']);

        $this->assertReleased($case['landing'], $before);

        // the old source's exception/audit survive unchanged
        $this->assertSame(1, ClassException::query()
            ->where('class_session_id', $case['session']->id)->where('week_number', self::OLD_SOURCE_WEEK)
            ->where('reason', 'medical_leave')->count());
        $this->assertSame(1, $this->sourceAuditCount($case['session'], self::OLD_SOURCE_WEEK));
    }

    public function test_a_stale_landing_outside_the_window_refuses_without_writes(): void
    {
        $case = $this->approved['stale'];   // landing Tue 2 Feb 2027 < Thu 4 Feb cutoff
        $snapshot = $this->businessSnapshot();

        $res = $this->cancelApproved($case['session'], self::OLD_SOURCE_WEEK, 1, 'medical_leave', null, $case['request']);

        $this->assertFalse((bool) ($res['ok'] ?? true), 'the out-of-window landing refuses (the single approved path always enforces the window)');
        $this->assertNotEmpty((string) ($res['hint'] ?? ''), 'the refusal explains itself');
        $this->assertSnapshotUnchanged($snapshot);
        $this->assertNoNewNotifications();
    }

    public function test_a_cross_week_day_room_landing_reports_and_releases_its_own_cells(): void
    {
        $case = $this->approved['cross'];
        $before = $this->capture($case['landing']);

        $res = $this->cancelApproved($case['session'], 8, 4, 'medical_leave', null, $case['request']);
        $row = $this->receiptRow($res);

        $this->assertApprovedReceipt($row, $case['session'], $case['request'], 8, 4, '14:00:00', '15:00:00', $this->v3);

        $this->assertReleased($case['landing'], $before);

        $audit = $this->approvedAuditFor($case['request']);
        $this->assertTrue($this->detailsMention(
            $audit->details ?? [],
            (string) $case['request']->id,
            '14:00', '15:00',
        ), 'the audit carries the landing schedule facts');

        // R4: the request-linked fan-out body carries the FULL actual landing
        // range — cross-week lands on W8 Fri 14:00–15:00 at B-APR-C
        $crossRows = Notification::query()->where('request_id', (int) $case['request']->id)->get();
        $this->assertNotEmpty($crossRows);
        foreach ($crossRows as $n) {
            $this->assertSame('Scheduled class cancelled', (string) $n->title);
            $this->assertSame('BMM101 (L) · W8 · Fri · B-APR-C · 2:00 PM ~ 3:00 PM · cancelled', (string) $n->body);
        }
    }

    /* ══════════════ spans: 1 / 2 / 4 cells and the legacy stored span ══════════════ */

    public function test_one_two_and_four_cell_landings_release_exactly_their_proven_run(): void
    {
        // capture EVERY case's landing once, before anything is cancelled
        $facts = [];
        foreach ($this->approved as $key => $case) {
            $facts[$key] = $this->capture($case['landing']);
        }
        $peerBefore = $this->capture($this->peerCells());

        $cancelled = [];
        foreach (['one' => 1, 'four' => 4] as $key => $span) {
            $case = $this->approved[$key];
            $this->assertCount($span, $case['landing'], 'the world built the full '.$span.'-cell run');

            $res = $this->cancelApproved(
                $case['session'],
                (int) $case['landing'][0]->week_number,
                (int) $case['landing'][0]->day_of_week,
                'medical_leave', null, $case['request'],
            );
            $this->receiptRow($res);

            $this->assertReleased($case['landing'], $facts[$key]);
            $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $case['request']->id)->count());
            $this->assertSame($span, (int) $case['request']->refresh()->replacement_span, 'the stored span survives');
            $cancelled[] = $key;

            // unrelated holdings stay put: every other case's landing and the
            // normal classes are untouched
            foreach ($this->approved as $otherKey => $otherCase) {
                if (in_array($otherKey, array_merge($cancelled, ['drift']), true)) {
                    continue;
                }
                $this->assertUntouched($otherCase['landing'], $facts[$otherKey]);
            }
            $this->assertUntouched($this->peerCells(), $peerBefore);
        }
    }

    public function test_a_legacy_junction_less_stored_span_releases_the_full_mapped_run(): void
    {
        $case = $this->approved['legacy'];
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $case['request']->id)->count(), 'the legacy shape has no junction receipt');
        $before = $this->capture($case['landing']);

        $res = $this->cancelApproved($case['session'], 9, 3, 'medical_leave', null, $case['request']);
        $this->receiptRow($res);

        $this->assertCount(4, $case['landing'], 'the mapper fallback proved the whole 4-cell run');
        $this->assertReleased($case['landing'], $before);
        $this->assertSame(4, (int) $case['request']->refresh()->replacement_span, 'the legacy stored span is retained');
    }

    /* ══════════════ the day batch: mixed / approved-only / atomicity ══════════════ */

    public function test_a_mixed_day_decision_cancels_withdraws_and_cancels_approved_in_one_transaction(): void
    {
        $landing = $this->approvedLandingCells($this->reportRequest);
        $peerCells = $this->peerCells();
        $withdrawCell = TimeSlot::find((int) $this->pendingWithdrawal->replacement_time_slot_id);
        $landingBefore = $this->capture($landing);
        $peerBefore = $this->capture($peerCells);
        $snapshot = $this->businessSnapshot();

        $res = $this->cancelDay(self::SOURCE_WEEK, 3, 'medical_leave', null,
            [$this->pendingWithdrawal->id], [$this->peer->id], [$this->reportRequest->id]);

        $this->assertTrue((bool) ($res['ok'] ?? false), 'the mixed day decision answers ok: '.json_encode($res));

        // the receipt is EXACTLY the acknowledged set: the opened approved
        // target + the normal class; the withdrawn make-up is not a receipt row
        $cancelled = $res['cancelled'];
        $this->assertCount(2, $cancelled);
        $approvedRow = null;
        $normalRow = null;
        foreach ($cancelled as $row) {
            if ((int) ($row['request_id'] ?? 0) === (int) $this->reportRequest->id) {
                $approvedRow = $row;
            } elseif ((int) ($row['session_id'] ?? 0) === (int) $this->peer->id) {
                $normalRow = $row;
            }
        }
        $this->assertNotNull($approvedRow, 'the approved receipt row is present');
        $this->assertNotNull($normalRow, 'the normal receipt row is present');

        $this->assertApprovedReceipt($approvedRow, $this->report, $this->reportRequest, self::SOURCE_WEEK, 3, '13:00:00', '14:00:00', $this->v1);
        $this->assertSame((int) $this->peer->id, (int) $normalRow['session_id']);
        $this->assertSame(self::SOURCE_WEEK, (int) $normalRow['week'], "a normal receipt's week is its own week");
        $this->assertNotNull($normalRow['exception_id'] ?? null, 'a normal receipt carries its exception');
        $this->assertSame('15:00', (string) $normalRow['start']);
        $this->assertSame('16:00', (string) $normalRow['end']);
        $this->assertIsArray($normalRow['cohorts'], 'normal receipts carry the display fields too');

        $this->assertSame(1, (int) $res['requestsClosed'], 'the approved request counts as a closure');
        $this->assertSame(1, (int) $res['requestsWithdrawn'], 'the pending withdrawal keeps its own meaning');

        // the approved release
        $this->assertReleased($landing, $landingBefore);
        $this->reportRequest->refresh();
        $this->assertSame('cancelled', (string) $this->reportRequest->status);
        $this->assertNotNull($this->reportRequest->decided_at);
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $this->reportRequest->id)->count());

        // the normal cancellation: exception + released originals + audit
        $this->assertSame(1, ClassException::query()->where('class_session_id', $this->peer->id)->where('week_number', self::SOURCE_WEEK)->count());
        $this->assertReleased($peerCells, $peerBefore);
        $this->assertSame(1, AuditLog::query()
            ->where('action', 'class_cancelled')
            ->where('details->class_session_id', (string) $this->peer->id)
            ->where('details->week_number', (string) self::SOURCE_WEEK)
            ->count());

        // the withdrawal: request closed, its pending block freed
        $this->pendingWithdrawal->refresh();
        $this->assertSame('cancelled', (string) $this->pendingWithdrawal->status);
        $this->assertSame('available', (string) $withdrawCell->refresh()->status);

        // the source history stays untouched; no fabricated source audit
        $this->assertSame(1, $this->sourceAuditCount($this->report, self::SOURCE_WEEK));
        $this->assertSame(
            $snapshot['exceptions'],
            ClassException::query()->where('class_session_id', '!=', $this->peer->id)->orderBy('id')->get()
                ->map(static fn ($r) => $r->attributesToArray())->all(),
            'the only new exception is the normal class\'s',
        );

        // ONE decision, ONE fan-out: both classes notified
        $rows = $this->newNotifications();
        $this->assertTrue(collect($rows)->contains(fn (Notification $n) => (int) $n->request_id === (int) $this->reportRequest->id), 'the approved target notified');
        $this->assertTrue(collect($rows)->contains(fn (Notification $n) => (int) $n->class_session_id === (int) $this->peer->id && $n->request_id === null), 'the normal class notified');
    }

    public function test_an_approved_only_day_selection_cancels_no_normal_class(): void
    {
        $landing = $this->approvedLandingCells($this->reportRequest);
        $landingBefore = $this->capture($landing);
        $peerBefore = $this->capture($this->peerCells());
        $snapshot = $this->businessSnapshot();

        $res = $this->cancelDay(self::SOURCE_WEEK, 3, 'medical_leave', null, [], [], [$this->reportRequest->id]);

        $this->assertTrue((bool) ($res['ok'] ?? false), 'an approved-only selection answers ok');
        $this->assertCount(1, $res['cancelled'], 'exactly the approved target');
        $this->assertSame((int) $this->reportRequest->id, (int) $res['cancelled'][0]['request_id']);
        $this->assertSame(1, (int) $res['requestsClosed']);
        $this->assertSame(0, (int) $res['requestsWithdrawn']);

        // empty sessionIds means NO normal classes — never the whole-day default
        $this->assertReleased($landing, $landingBefore);
        $this->assertSame(0, ClassException::query()->where('class_session_id', $this->peer->id)->count(), 'the normal class was not cancelled');
        $this->assertUntouched($this->peerCells(), $peerBefore);
        $this->assertSame($snapshot['exceptions'], $this->businessSnapshot()['exceptions'], 'no exception row anywhere');
    }

    public function test_repeated_approved_ids_in_one_selection_normalize_to_one_cancellation(): void
    {
        $before = $this->capture($this->approvedLandingCells($this->reportRequest));

        $res = $this->cancelDay(self::SOURCE_WEEK, 3, 'medical_leave', null, [], [], [$this->reportRequest->id, $this->reportRequest->id]);

        $this->assertTrue((bool) ($res['ok'] ?? false), 'harmless repeated ids normalize');
        $this->assertCount(1, $res['cancelled'], 'the same request is never closed twice');
        $this->assertReleased($this->approvedLandingCells($this->reportRequest), $before);
        $this->assertSame(1, AuditLog::query()->where('action', 'cancelled')->where('replacement_request_id', $this->reportRequest->id)->count());
    }

    public function test_one_unresolvable_normal_id_refuses_the_whole_day_selection(): void
    {
        $snapshot = $this->businessSnapshot();

        $res = $this->cancelDay(self::SOURCE_WEEK, 3, 'medical_leave', null, [], [$this->peer->id, 424242], []);

        $this->assertFalse((bool) ($res['ok'] ?? true), 'every selected normal id must resolve — one fabricated id refuses the selection');
        $this->assertSnapshotUnchanged($snapshot);
        $this->assertNoNewNotifications();
    }

    public function test_a_later_approved_target_failure_rolls_back_the_whole_day(): void
    {
        $snapshot = $this->businessSnapshot();

        // the `holder` approved target loses to a NEWER pending holder —
        // whatever phase the refusal fires in, NOTHING may stay half-written
        $res = $this->cancelDay(self::SOURCE_WEEK, 3, 'medical_leave', null, [], [$this->peer->id], [$this->approved['holder']['request']->id]);

        $this->assertFalse((bool) ($res['ok'] ?? true), 'the day answers refused: '.json_encode($res));
        $this->assertSnapshotUnchanged($snapshot);
        $this->assertNoNewNotifications();

        // every target unchanged: the normal class still runs, the approved
        // request is still Approved, its landing still occupied
        $this->assertSame(0, ClassException::query()->where('class_session_id', $this->peer->id)->count());
        $this->assertSame('approved', (string) $this->approved['holder']['request']->refresh()->status);
        foreach ($this->approved['holder']['landing'] as $cell) {
            $cell->refresh();
            $this->assertSame('occupied', (string) $cell->status);
        }
    }

    public function test_a_late_normal_audit_failure_rolls_back_an_already_released_approved_target(): void
    {
        // a LATER failure, not preflight drift: the mixed day writes the
        // approved release FIRST (Phase B), then the normal class's
        // `class_cancelled` audit is sabotaged by a temporary AuditLog-creating
        // listener armed AFTER the world setup — proving the ONE transaction
        // rolls back the approved release too, and the response carries the
        // honest generic day-fault copy, never withdrawal blame
        $landing = $this->approvedLandingCells($this->reportRequest);
        $landingBefore = $this->capture($landing);
        $snapshot = $this->businessSnapshot();

        $events = app('events');
        $eventName = 'eloquent.creating: '.AuditLog::class;
        $saved = $events->getListeners($eventName);
        $events->listen($eventName, function (AuditLog $audit): void {
            $details = (array) ($audit->getAttribute('details') ?? []);
            if ((string) $audit->getAttribute('action') === 'class_cancelled'
                && (int) ($details['class_session_id'] ?? 0) === (int) $this->peer->id) {
                throw new \RuntimeException('fixture_late_audit_failure');
            }
        });

        try {
            $res = $this->cancelDay(self::SOURCE_WEEK, 3, 'medical_leave', null,
                [], [$this->peer->id], [$this->reportRequest->id]);
        } finally {
            // restore the exact listener set: forget, then re-register the
            // saved ones — the temporary listener never leaks past this test
            $events->forget($eventName);
            foreach ($saved as $savedListener) {
                $events->listen($eventName, $savedListener);
            }
        }

        $this->assertFalse((bool) ($res['ok'] ?? true), 'the late audit failure refuses the whole day: '.json_encode($res));
        $hint = (string) ($res['hint'] ?? '');
        $this->assertStringContainsString('nothing was changed', $hint, 'the generic day-fault hint');
        $this->assertStringNotContainsString('withdraw', $hint, 'a day fault must NOT blame the withdrawal');
        $this->assertSame(0, (int) ($res['requestsWithdrawn'] ?? -1));

        // EXACT six-family rollback: the ALREADY released approved target is
        // fully restored — request, receipt, cells and versions all unmade
        $this->assertSnapshotUnchanged($snapshot);
        $this->assertSame('approved', (string) $this->reportRequest->refresh()->status, 'the approved request is still Approved');
        $this->assertSame(2, ReplacementRequestSlot::query()->where('replacement_request_id', $this->reportRequest->id)->count(), 'the junction receipt is back');
        $this->assertUntouched($landing, $landingBefore);
        $this->assertSame(0, ClassException::query()
            ->where('class_session_id', $this->peer->id)->where('week_number', self::SOURCE_WEEK)
            ->count(), 'the normal class is not cancelled');
        $this->assertNoNewNotifications();
    }

    /* ══════════════ negatives: identity / state / coordinate / input ══════════════ */

    public function test_a_wrong_landing_coordinate_refuses(): void
    {
        $snapshot = $this->businessSnapshot();
        $case = $this->approved['one'];   // landing week 6 day 3

        $byWeek = $this->cancelApproved($case['session'], 8, 3, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($byWeek['ok'] ?? true), 'the landing week must match the shown coordinate');
        $this->assertSnapshotUnchanged($snapshot);

        $byDay = $this->cancelApproved($case['session'], 6, 4, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($byDay['ok'] ?? true), 'the landing day must match the shown coordinate');
        $this->assertSnapshotUnchanged($snapshot);
    }

    public function test_a_foreign_actor_and_a_pre_closed_request_refuse(): void
    {
        $snapshot = $this->businessSnapshot();
        $case = $this->approved['one'];

        $asForeign = $this->cancelApproved($case['session'], 6, 3, 'medical_leave', null, $case['request'], $this->other);
        $this->assertFalse((bool) ($asForeign['ok'] ?? true), 'only the owner cancels');
        $this->assertSnapshotUnchanged($snapshot);

        // a request that is no longer Approved refuses — no normal fallback
        $case['request']->forceFill(['status' => 'cancelled'])->save();
        $afterClose = $this->businessSnapshot();
        $closed = $this->cancelApproved($case['session'], 6, 3, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($closed['ok'] ?? true), 'a dead request cannot be cancelled again');
        $this->assertSnapshotUnchanged($afterClose);
    }

    public function test_a_retry_after_success_never_releases_twice(): void
    {
        $case = $this->approved['four'];

        $first = $this->cancelApproved($case['session'], 5, 3, 'medical_leave', null, $case['request']);
        $this->receiptRow($first);
        $releasedFacts = $this->capture($case['landing']);
        $watermark = (int) (Notification::query()->max('id') ?? 0);
        $auditCount = AuditLog::query()->where('action', 'cancelled')->where('replacement_request_id', $case['request']->id)->count();

        $retry = $this->cancelApproved($case['session'], 5, 3, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($retry['ok'] ?? true), 'the closed request refuses a retry');
        $this->assertSame($releasedFacts, $this->capture($case['landing']), 'the cells were not released again');
        $this->assertSame($auditCount, AuditLog::query()->where('action', 'cancelled')->where('replacement_request_id', $case['request']->id)->count(), 'no second audit');
        $this->assertSame(0, $this->notificationsSince($watermark), 'no second fan-out');
    }

    public function test_invalid_reason_and_blank_other_detail_refuse(): void
    {
        $snapshot = $this->businessSnapshot();
        $case = $this->approved['cross'];

        $badReason = $this->cancelApproved($case['session'], 8, 4, 'sick_day', null, $case['request']);
        $this->assertFalse((bool) ($badReason['ok'] ?? true), 'an unknown reason refuses even when the target is valid');
        $this->assertSnapshotUnchanged($snapshot);

        $blankOther = $this->cancelApproved($case['session'], 8, 4, 'other', '   ', $case['request']);
        $this->assertFalse((bool) ($blankOther['ok'] ?? true), 'a blank Other detail refuses');
        $this->assertSnapshotUnchanged($snapshot);
    }

    public function test_an_out_of_range_landing_week_or_day_refuses(): void
    {
        $snapshot = $this->businessSnapshot();
        $case = $this->approved['one'];

        $week = $this->cancelApproved($case['session'], 15, 3, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($week['ok'] ?? true), 'week 15 is outside the semester');
        $this->assertSnapshotUnchanged($snapshot);

        $day = $this->cancelApproved($case['session'], 6, 9, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($day['ok'] ?? true), 'day 9 is outside the week');
        $this->assertSnapshotUnchanged($snapshot);
    }

    public function test_a_semester_mismatch_refuses(): void
    {
        $snapshot = $this->businessSnapshot();
        $case = $this->approved['sem'];

        $res = $this->cancelApproved($case['session'], self::SOURCE_WEEK, 3, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($res['ok'] ?? true), 'a request from another semester is not the active one');
        $this->assertSnapshotUnchanged($snapshot);
    }

    public function test_a_holiday_landing_refuses(): void
    {
        $snapshot = $this->businessSnapshot();
        $case = $this->approved['holiday'];

        $res = $this->cancelApproved($case['session'], 10, 3, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($res['ok'] ?? true), 'a landing on a holiday refuses');
        $this->assertSnapshotUnchanged($snapshot);
    }

    public function test_a_competing_holder_refuses_and_releases_nothing(): void
    {
        $snapshot = $this->businessSnapshot();
        $case = $this->approved['holder'];

        $res = $this->cancelApproved($case['session'], 11, 3, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($res['ok'] ?? true), 'a newer holder is never released');
        $this->assertSnapshotUnchanged($snapshot);
        $this->assertSame('approved', (string) $case['request']->refresh()->status);
    }

    public function test_a_drifted_landing_claim_refuses(): void
    {
        $snapshot = $this->businessSnapshot();
        $case = $this->approved['drift'];

        $res = $this->cancelApproved($case['session'], 12, 3, 'medical_leave', null, $case['request']);
        $this->assertFalse((bool) ($res['ok'] ?? true), 'a stale/contradicting receipt refuses, never shrinks');
        $this->assertSnapshotUnchanged($snapshot);
    }

    /* ══════════════ privacy: the Other detail lives in the audit only ══════════════ */

    public function test_the_private_other_detail_reaches_the_audit_and_never_the_fanout(): void
    {
        $case = $this->approved['cross'];

        $res = $this->cancelApproved($case['session'], 8, 4, 'other', '  Surgery on my hand  ', $case['request']);
        $this->receiptRow($res);

        $audit = $this->approvedAuditFor($case['request']);
        $this->assertTrue($this->detailsMention($audit->details ?? [], 'Surgery on my hand'), 'the audit keeps the trimmed Other detail');

        foreach ($this->newNotifications() as $n) {
            $this->assertStringNotContainsString('Surgery on my hand', (string) $n->body, 'the private detail never rides a notification body');
            $this->assertStringNotContainsString('Surgery on my hand', (string) $n->title);
        }
    }

    /* ══════════════ the tooltip: actual landing span, never the old reason ══════════════ */

    public function test_the_request_linked_tip_prints_the_landing_span_and_omits_the_old_reason(): void
    {
        $case = $this->approved['cross'];

        $res = $this->cancelApproved($case['session'], 8, 4, 'medical_leave', null, $case['request']);
        $this->receiptRow($res);

        $row = Notification::query()->where('request_id', (int) $case['request']->id)->where('role_view', 'lecturer')->firstOrFail();
        $this->assertSame('class_cancelled', NotificationTip::kind((string) $row->type, (string) $row->title));

        // the island's own fallback shape: junctions are gone, so the span is
        // the request's anchor + stored width — the legacy fallback the frozen
        // design names explicitly
        $request = ReplacementRequest::query()->with('replacementTimeSlot.venue')->findOrFail((int) $case['request']->id);
        $mapper = app(ReplacementRequestMapper::class);
        $span = $mapper->spanFromAnchor($request->replacementTimeSlot, $request->replacement_span);
        $this->assertNotNull($span, 'the released legacy fallback still maps the landing run');
        $this->assertSame(8, (int) $span['week']);
        $venue = Venue::find((int) $span['venue_id']);
        $tip = app(NotificationTip::class)->compose(
            'class_cancelled', $request, $request->classSession, $span, $venue,
            Carbon::parse((string) $this->semester->start_date),
            'Medical/Sick Leave',   // the ORIGINAL exception's reason — must be omitted
            'lecturer',
            self::SOURCE_WEEK,
        );

        $this->assertNotNull($tip);
        $this->assertStringContainsString('Week 8 · Fri, 26 Mar 2027 · 2:00 PM ~ 3:00 PM @ B-APR-C', $tip, 'the tip names the ACTUAL landing schedule');
        $this->assertStringContainsString('Request ID: #', $tip);
        $this->assertStringNotContainsString('Reason:', $tip, 'the old source reason is omitted, never reused as the new one');
        $this->assertStringNotContainsString('Medical/Sick Leave', $tip);
        $this->assertStringNotContainsString('Week 2 · Thu, 11 Feb 2027', $tip, 'the tip never reconstructs the original recurring schedule');
    }

    public function test_a_request_linked_tip_with_a_missing_span_omits_the_schedule_line(): void
    {
        $case = $this->approved['cross'];

        $tip = app(NotificationTip::class)->compose(
            'class_cancelled', $case['request'], $case['session'], null, null,
            Carbon::parse((string) $this->semester->start_date),
            'Medical/Sick Leave',   // the ORIGINAL exception's reason — must never pose as the landing's
            'lecturer',
            self::SOURCE_WEEK,
        );

        $this->assertNotNull($tip, 'Subject + Request ID still make a two-line tip');
        $this->assertStringContainsString('Request ID: #'.(int) $case['request']->id, $tip);
        $this->assertStringNotContainsString('Cancelled Class Schedule', $tip, 'a missing span OMITS the schedule line — never the original');
        $this->assertStringNotContainsString('Week 2 · Thu, 11 Feb 2027', $tip, 'the tip never reconstructs the original recurring schedule');
        $this->assertStringNotContainsString('Medical/Sick Leave', $tip);
    }

    public function test_a_request_less_cancellation_tip_keeps_its_existing_shape(): void
    {
        $tip = app(NotificationTip::class)->compose(
            'class_cancelled', null, $this->peer, null, null,
            Carbon::parse((string) $this->semester->start_date),
            'Medical/Sick Leave',
            'lecturer',
            self::SOURCE_WEEK,
        );

        $this->assertNotNull($tip);
        $this->assertStringContainsString('Cancelled Class Schedule: Week 2 · Thu, 11 Feb 2027 · 3:00 PM ~ 4:00 PM @ B-APR-A', $tip);
        $this->assertStringContainsString('Reason: Medical/Sick Leave', $tip, 'the request-less tip keeps its reason line');
    }

    /* ══════════════ the four timetables + owner/student history ══════════════ */

    public function test_all_four_timetables_and_histories_follow_a_real_cancellation(): void
    {
        $case = $this->approved['cross'];
        $session = $case['session'];
        $requestId = (int) $case['request']->id;
        $weekIndex = self::SOURCE_WEEK - 1;
        $landingIndex = 7;   // week 8

        $student = $this->studentUser;

        $readMine = fn (): array => Livewire::actingAs($this->owner)->test(MyTimetable::class)->viewData('eventsByWeek');
        $readCohort = fn (): array => Livewire::actingAs($this->owner)->test(CohortTimetable::class)->viewData('eventsByCohort')[$this->cohortA->id];
        $readStudent = fn (): array => Livewire::actingAs($student)->test(StudentMyTimetable::class)->viewData('eventsByWeek');
        $readVenueSource = fn (): array => Livewire::withQueryParams(['venue' => 'B-APR-B'])->actingAs($this->owner)->test(VenueTimetable::class)->viewData('eventsByWeek');
        $readVenueLanding = fn () => Livewire::withQueryParams(['venue' => 'B-APR-C'])->actingAs($this->owner)->test(VenueTimetable::class);

        $occurrenceKey = 'occ:'.$this->semester->id.':'.$session->id.':'.self::SOURCE_WEEK;
        $requestKey = 'request:'.$requestId;

        // BEFORE: the own-approved suppression hides the source history and
        // the landing renders once on every MY-surface
        foreach (['mine' => $readMine(), 'cohort' => $readCohort(), 'student' => $readStudent()] as $surface => $events) {
            $this->assertNull($this->findEvent($events[$weekIndex], $occurrenceKey), "$surface: the own-approved source history is suppressed");
            $this->assertCount(1, $this->eventsWithKey($events[$landingIndex], $requestKey), "$surface: the landing renders once");
        }

        // the SOURCE room (B-APR-B): the source original is suppressed there
        // too, and the landing — a DIFFERENT room — never renders out of room
        $sourceRoom = $readVenueSource();
        $this->assertNull($this->findEvent($sourceRoom[$weekIndex], $occurrenceKey), 'venue source room: the own-approved source history is suppressed');
        $this->assertCount(0, $this->eventsWithKey($sourceRoom[$landingIndex], $requestKey), 'venue source room: no out-of-room landing');

        // the LANDING room (B-APR-C): the landing renders there, and no
        // foreign source occurrence (B-APR-B's) leaks into it
        $landingRoom = $readVenueLanding();
        $landingRoomEvents = $landingRoom->viewData('eventsByWeek');
        $this->assertCount(1, $this->eventsWithKey($landingRoomEvents[$landingIndex], $requestKey), 'landing room: the landing renders once');
        $this->assertNull($this->findEvent($landingRoomEvents[$weekIndex], $occurrenceKey), 'landing room: no foreign source occurrence');

        // the landing room's REAL occupancy while approved
        $this->assertSame(2, $landingRoom->viewData('totalsByWeek')[$landingIndex]['occupied'], 'the approved landing occupies its room');
        $this->assertSame(1, $landingRoom->viewData('totalsByWeek')[$landingIndex]['myClasses']);

        // the REAL cancellation (single approved path)
        $res = $this->cancelApproved($session, 8, 4, 'medical_leave', null, $case['request']);
        $this->receiptRow($res);

        // AFTER: the landing is gone everywhere and the source history is back
        foreach (['mine' => $readMine(), 'cohort' => $readCohort(), 'student' => $readStudent(), 'venue source room' => $readVenueSource()] as $surface => $events) {
            $this->assertCount(0, $this->eventsWithKey($events[$landingIndex], $requestKey), "$surface: no dead landing block");
            $history = $this->findEvent($events[$weekIndex], $occurrenceKey);
            $this->assertNotNull($history, "$surface: the exact source cancellation is visible again");
            $this->assertSame('cancelled', (string) ($history['status'] ?? ''));
        }

        // the landing room's real truth after the release: no dead landing
        // and it never gains the out-of-room source history
        $landingRoom = $readVenueLanding();
        $landingRoomEvents = $landingRoom->viewData('eventsByWeek');
        $this->assertCount(0, $this->eventsWithKey($landingRoomEvents[$landingIndex], $requestKey), 'landing room: no dead landing block');
        $this->assertNull($this->findEvent($landingRoomEvents[$weekIndex], $occurrenceKey), 'landing room: no out-of-room source history');
        $this->assertSame(0, $landingRoom->viewData('totalsByWeek')[$landingIndex]['occupied'], 'the released cells free the room');
        $this->assertSame(0, $landingRoom->viewData('totalsByWeek')[$landingIndex]['myClasses']);

        // My Replacements: ONE source row, its request identity kept, the
        // no-active-request display (blank requested slot, no proposal)
        $home = Livewire::actingAs($this->owner)->test(ReplacementHome::class)->viewData('payload')['conflictedClasses'];
        $row = collect($home)->first(fn (array $r): bool => (int) $r['sessionId'] === (int) $session->id && (int) $r['week'] === self::SOURCE_WEEK);
        $this->assertNotNull($row, 'the source occurrence row stays');
        $this->assertSame($requestId, (int) $row['requestId'], 'the request identity rides the row');
        $this->assertNull($row['requestStatus'], 'a cancelled request displays as no active request');
        $this->assertSame('Need to Arrange', (string) $row['requestStatusShort']);
        $this->assertTrue(empty($row['replacementDate']) && empty($row['replacementTime']), 'the requested slot is blank');
        $this->assertNull($row['remarks']);

        // the student Class Replacements page: Awaiting Arrangement, no
        // proposal, and the bare "Class Cancelled" wording
        $history = Livewire::actingAs($student)->test(ReplacementHistory::class)->viewData('payload')['replacementHistory'];
        $studentRow = collect($history)->first(fn (array $r): bool => $r['id'] === $session->id.':'.self::SOURCE_WEEK);
        $this->assertNotNull($studentRow, 'the student sees the source occurrence');
        $this->assertNull($studentRow['requestId'], 'the dead request is not the row\'s identity');
        $this->assertSame('none', (string) $studentRow['status']);
        $this->assertSame('Awaiting Arrangement', (string) $studentRow['statusShort']);
        $this->assertNull($studentRow['req'], 'no requested slot is shown');
        $this->assertSame('Class Cancelled', (string) $studentRow['conflictKind']);
        $this->assertStringNotContainsString('medical', json_encode($studentRow) ?: '', 'no leave reason leaves to the student payload');
    }

    /* ══════════════ small shared probes ══════════════ */

    /** @return list<TimeSlot> */
    private function approvedLandingCells(ReplacementRequest $request): array
    {
        $junctionIds = ReplacementRequestSlot::query()
            ->where('replacement_request_id', (int) $request->id)
            ->orderBy('time_slot_id')
            ->pluck('time_slot_id');

        if ($junctionIds->isEmpty()) {
            // the legacy fallback: the mapped run at the anchor + stored span
            $anchor = TimeSlot::findOrFail((int) $request->replacement_time_slot_id);
            $span = max(1, (int) $request->replacement_span);

            return TimeSlot::query()
                ->where('semester_id', (int) $request->semester_id)
                ->where('week_number', (int) $anchor->week_number)
                ->where('day_of_week', (int) $anchor->day_of_week)
                ->where('venue_id', (int) $anchor->venue_id)
                ->where('start_time', '>=', (string) $anchor->start_time)
                ->where('start_time', '<', Carbon::parse((string) $anchor->start_time)->addMinutes(30 * $span)->format('H:i:s'))
                ->orderBy('start_time')
                ->get()
                ->all();
        }

        return TimeSlot::query()->whereIn('id', $junctionIds)->orderBy('start_time')->get()->all();
    }

    /** @return list<TimeSlot> */
    private function peerCells(): array
    {
        return TimeSlot::query()
            ->where('semester_id', $this->semester->id)
            ->where('week_number', self::SOURCE_WEEK)
            ->where('day_of_week', (int) $this->peer->day_of_week)
            ->where('venue_id', (int) $this->peer->venue_id)
            ->where('start_time', '>=', (string) $this->peer->start_time)
            ->where('start_time', '<', (string) $this->peer->end_time)
            ->orderBy('start_time')
            ->get()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function eventsWithKey(array $weekEvents, string $key): array
    {
        return array_values(array_filter($weekEvents, fn (array $e): bool => ($e['eventKey'] ?? null) === $key));
    }

    private function findEvent(array $weekEvents, string $key): ?array
    {
        return $this->eventsWithKey($weekEvents, $key)[0] ?? null;
    }
}
