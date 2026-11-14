<?php

namespace Tests\Feature;

use App\Livewire\CohortTimetable;
use App\Livewire\MyTimetable;
use App\Livewire\StudentMyTimetable;
use App\Livewire\VenueTimetable;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\CancelledOccurrenceEvidence;
use App\Services\TimetableEventIdentity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * cancelled-slot-reuse A11/A12/A13 — the PAYLOAD projections (specs S13–S16).
 *
 * Fixtures are minimal RefreshDatabase builds with valid 30-minute cells,
 * full spans and the frozen 2026-08-31 semester. Everything asserts the
 * SERVER payload (eventsByWeek / totalsByWeek) — never the DOM.
 *
 * The R203 revision-2 matrix (spec S16, 2026-11-14): ONLY a stored
 * `approved` request for that exact original occurrence suppresses the
 * cancelled event on all four surfaces; pending keeps both blocks and
 * dead/absent requests keep the original with no dead landing. A display
 * read never touches business rows (a before/after snapshot proves it).
 *
 * The S13 discriminator: a cancelled HISTORY event needs the current
 * exception AND a non-maintenance `class_cancelled` audit for the exact
 * (session, week) pair. An approval-only `official_event` stamp alone never
 * gains history; the shared {@see TimetableEventIdentity} contract is pinned
 * here first (the later CLI reuses the same public API).
 */
class CancelledSlotTimelineTest extends TestCase
{
    use RefreshDatabase;

    private User $lecturerA;

    private User $lecturerB;

    private Semester $semester;

    private Venue $venue;

    private Module $module;

    private Cohort $cohortA;

    private Cohort $cohortB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semester = Semester::create([
            'semester_code' => '202605',
            'label' => '202605 Semester',
            'start_date' => '2026-08-31',
            'end_date' => '2026-12-06',
            'week_count' => 14,
        ]);

        $faculty = Faculty::create(['faculty_code' => 'FOCS', 'faculty_name' => 'FOCS']);
        $dept = Department::create(['dept_code' => 'DCIT', 'dept_name' => 'DCIT', 'faculty_id' => $faculty->id]);
        $prog = Programme::create(['programme_code' => 'RSD', 'programme_name' => 'RSD', 'faculty_id' => $faculty->id]);

        $this->cohortA = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 9,
        ]);
        $this->cohortB = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 2, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 8,
        ]);

        $this->lecturerA = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer A']);
        Lecturer::create(['user_id' => $this->lecturerA->id, 'staff_id' => '6001', 'dept_id' => $dept->id, 'is_pl' => false]);
        $this->lecturerB = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer B']);
        Lecturer::create(['user_id' => $this->lecturerB->id, 'staff_id' => '6002', 'dept_id' => $dept->id, 'is_pl' => false]);

        $this->venue = Venue::create([
            'room_code' => 'B100', 'room_name' => 'Tutorial Room B100',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T', 'capacity' => 35,
        ]);
        $this->module = Module::create([
            'module_code' => 'BMIT1001', 'module_name' => 'Software Engineering',
            'allowed_session_types' => 'L,T',
        ]);
    }

    /* ── fixture helpers ──────────────────────────────────────────────── */

    private function sessionFor(User $lecturer, ?array $cohortIds = null, int $day = 1, string $start = '10:00:00', string $end = '12:00:00'): ClassSession
    {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach($cohortIds ?? [$this->cohortA->id]);

        return $session;
    }

    /** The REAL user-cancellation receipt: exception + non-maintenance audit. */
    private function cancelFor(ClassSession $session, int $week, string $reason = 'annual_leave'): void
    {
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => $week,
            'reason' => $reason,
        ]);
        AuditLog::create([
            'user_id' => $session->lecturer_id,
            'action' => 'class_cancelled',
            'details' => [
                'class_session_id' => (int) $session->id,
                'week_number' => $week,
                'reason' => $reason,
            ],
        ]);
    }

    /** The APPROVAL stamp: the exception alone (ApproveRequest D3's shape). */
    private function stampFor(ClassSession $session, int $week, string $reason = 'official_event'): void
    {
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => $week,
            'reason' => $reason,
        ]);
    }

    /**
     * A live request with its FULL junction span (valid 30-minute cells).
     * `$staleClassId` crafts the released/reused row shape that keeps a
     * non-null `class_session_id` on request-held rows. The HELD rows carry
     * the real `time_slots.status` CHECK values — an approved request's
     * cells are 'occupied', a pending one's 'pending' (the request row
     * itself carries the request status).
     *
     * `$landingVenue` (optional, LAST) moves the LANDING's own room — the
     * source stays where the session teaches (the cross-room own-approved
     * landing case). Defaults to the existing venue.
     *
     * @return array{0: TimeSlot, 1: ReplacementRequest, 2: list<int>}
     */
    private function spanRequest(ClassSession $session, int $occurrenceWeek, int $anchorWeek, int $day, string $start, int $cells, string $status, ?int $staleClassId = null, ?Venue $landingVenue = null): array
    {
        $landingVenue ??= $this->venue;
        $first = null;
        $slotIds = [];
        $slotStatus = $status === 'pending' ? 'pending' : 'occupied';
        for ($k = 0; $k < $cells; $k++) {
            $from = Carbon::parse($start)->addMinutes($k * 30);
            $slot = TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => $staleClassId,
                'week_number' => $anchorWeek,
                'day_of_week' => $day,
                'start_time' => $from->format('H:i:s'),
                'end_time' => $from->copy()->addMinutes(30)->format('H:i:s'),
                'venue_id' => $landingVenue->id,
                'status' => $slotStatus,
                'version' => 1,
            ]);
            $slotIds[] = (int) $slot->id;
            $first ??= $slot;
        }

        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $session->lecturer_id,
            'class_session_id' => $session->id,
            'week_number' => $occurrenceWeek,
            'replacement_time_slot_id' => (int) $first->id,
            'replacement_span' => $cells,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        foreach ($slotIds as $slotId) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slotId,
            ]);
        }

        return [$first, $request, $slotIds];
    }

    /** "Cleared after release": the original rows fall back to available. */
    private function clearedOriginalRows(ClassSession $session, int $week, int $cells): void
    {
        for ($k = 0; $k < $cells; $k++) {
            $from = Carbon::parse((string) $session->start_time)->addMinutes($k * 30);
            TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => null,
                'week_number' => $week,
                'day_of_week' => (int) $session->day_of_week,
                'start_time' => $from->format('H:i:s'),
                'end_time' => $from->copy()->addMinutes(30)->format('H:i:s'),
                'venue_id' => $this->venue->id,
                'status' => 'available',
                'version' => 2,
            ]);
        }
    }

    /** Pre-cleanup shape: the original rows are STILL occupied (the cleanup
        has not run, no actual holder change — the raw occupancy truth). */
    private function rawOccupiedRows(ClassSession $session, int $week, int $cells): void
    {
        for ($k = 0; $k < $cells; $k++) {
            $from = Carbon::parse((string) $session->start_time)->addMinutes($k * 30);
            TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => $session->id,
                'week_number' => $week,
                'day_of_week' => (int) $session->day_of_week,
                'start_time' => $from->format('H:i:s'),
                'end_time' => $from->copy()->addMinutes(30)->format('H:i:s'),
                'venue_id' => $this->venue->id,
                'status' => 'occupied',
                'version' => 1,
            ]);
        }
    }

    private function cancelledEvent(array $weekEvents, ClassSession $session, int $week): ?array
    {
        $key = TimetableEventIdentity::occurrenceKey((int) $this->semester->id, (int) $session->id, $week);

        foreach ($weekEvents as $event) {
            if (($event['eventKey'] ?? null) === $key && ($event['status'] ?? null) === 'cancelled') {
                return $event;
            }
        }

        return null;
    }

    /* ── S13/S14 — the shared identity + discriminator API ────────────── */

    public function test_the_shared_identity_api_pins_keys_provenance_and_maintenance(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5, 'medical_leave');
        // a MAINTENANCE receipt for a different pair never counts
        AuditLog::create([
            'user_id' => $this->lecturerA->id,
            'action' => 'class_cancelled',
            'details' => [
                'class_session_id' => (int) $session->id,
                'week_number' => 7,
                'reason' => 'maintenance',
                'maintenance' => true,
            ],
        ]);

        $pairs = TimetableEventIdentity::userCancelledAuditPairs([$session->id]);
        $this->assertSame([''.$session->id.':5' => true], $pairs);

        $exception = ClassException::query()->firstOrFail();
        $this->assertSame(TimetableEventIdentity::PROVENANCE_USER_CANCELLED,
            TimetableEventIdentity::provenance($exception, true));
        $this->assertSame(TimetableEventIdentity::PROVENANCE_STAMP_ONLY,
            TimetableEventIdentity::provenance($exception, false));
        $this->assertSame(TimetableEventIdentity::PROVENANCE_AUDIT_ONLY,
            TimetableEventIdentity::provenance(null, true));
        $this->assertSame(TimetableEventIdentity::PROVENANCE_NONE,
            TimetableEventIdentity::provenance(null, false));

        $this->assertSame(
            'occ:'.$this->semester->id.':'.$session->id.':5',
            TimetableEventIdentity::occurrenceKey((int) $this->semester->id, (int) $session->id, 5),
        );
        $this->assertSame('request:24', TimetableEventIdentity::requestKey(24));

        // same-key duplicates merge ONCE (no double student count);
        // distinct identities at the same coordinates never merge.
        $base = fn (string $key, string $cohort, int $count) => [
            'id' => 1, 'di' => 1, 'start' => 4, 'end' => 7, 'status' => 'normal',
            'cohort' => $cohort, 'cohorts' => [$cohort], 'studentCounts' => [$count],
            'studentCount' => $count, 'mine' => false, 'eventKey' => $key,
        ];
        $merged = TimetableEventIdentity::dedupeSameKey([
            $base('occ:1:1:5', 'RSD3S1G1', 9),
            $base('occ:1:1:5', 'RSD3S1G2', 8),
            $base('occ:1:2:5', 'RSD3S1G1', 9),
        ]);
        $this->assertCount(2, $merged);
        $this->assertSame(['RSD3S1G1', 'RSD3S1G2'], $merged[0]['cohorts']);
        $this->assertSame(17, $merged[0]['studentCount']);
        $this->assertSame('occ:1:2:5', $merged[1]['eventKey']);

        // an exact duplicate (the same occurrence seen twice) inflates nothing
        $twice = TimetableEventIdentity::dedupeSameKey([$base('occ:1:1:5', 'RSD3S1G1', 9), $base('occ:1:1:5', 'RSD3S1G1', 9)]);
        $this->assertCount(1, $twice);
        $this->assertSame(9, $twice[0]['studentCount']);
    }

    /* ── A12 — personal timeline: lifecycle + approved suppression ─────── */

    public function test_exact_own_approved_suppresses_the_original_and_lands_once(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5);
        [$anchor, $request] = $this->spanRequest($session, 5, 5, 1, '10:00:00', 4, 'approved');

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $week5 = $events[4];

        // S16 revision 2: the OWN-approved handled original is suppressed —
        // the stored exception/audit stay, only the display changes
        $this->assertNull($this->cancelledEvent($week5, $session, 5),
            'an own-approved exact-reuse original is suppressed');

        // the approved landing still renders EXACTLY ONCE with its identity
        // and the TRUE full junction span
        $landing = array_values(array_filter($week5, fn ($e) => ($e['eventKey'] ?? null) === 'request:'.$request->id));
        $this->assertCount(1, $landing);
        $this->assertSame('replacement', $landing[0]['status']);
        $this->assertSame(4, $landing[0]['start']);   // the TRUE full junction span
        $this->assertSame(7, $landing[0]['end']);
        $this->assertSame(1, $landing[0]['di']);
        $this->assertSame((int) $request->id, $landing[0]['requestId']);
        $this->assertSame('B100', $landing[0]['venue']);
    }

    public function test_partial_own_approved_suppresses_the_original_and_lands_once(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5);
        [, $request] = $this->spanRequest($session, 5, 5, 1, '10:00:00', 2, 'approved');   // 10:00–11:00 inside 10:00–12:00

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $this->assertNull($this->cancelledEvent($events[4], $session, 5),
            'an own-approved PARTIAL overlap still suppresses the handled original');
        $landing = array_values(array_filter($events[4], fn ($e) => ($e['eventKey'] ?? null) === 'request:'.$request->id));
        $this->assertCount(1, $landing);
        $this->assertSame('replacement', $landing[0]['status']);
        $this->assertSame(4, $landing[0]['start']);   // the TRUE full junction span
        $this->assertSame(5, $landing[0]['end']);
    }

    public function test_non_overlapping_approval_suppresses_as_today(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5);
        $this->spanRequest($session, 5, 8, 4, '14:00:00', 4, 'approved');   // a different week/day/time

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $this->assertSame([], $events[4]);   // the handled original stays suppressed
        $this->assertNotEmpty($events[7]);   // the landing renders in its own week
    }

    public function test_another_in_scope_active_event_keeps_normal_reuse_after_own_approval(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5);
        $this->spanRequest($session, 5, 8, 4, '14:00:00', 2, 'approved');   // own landing, different week
        // ANOTHER class of the same lecturer now runs over the original range
        $reuse = $this->sessionFor($this->lecturerA, [$this->cohortA], 1, '10:00:00', '12:00:00');

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        // ONLY the handled original is suppressed: the other class's normal
        // reuse of the range keeps its own event, and the own landing still
        // renders in its own week
        $this->assertNull($this->cancelledEvent($events[4], $session, 5),
            'the own-approved handled original is suppressed even under another active reuse');
        $this->assertSame('normal', $events[4][0]['status']);   // the reusing class
        $this->assertSame('occ:'.$this->semester->id.':'.$reuse->id.':5', $events[4][0]['eventKey']);
        $landing = array_values(array_filter($events[7], fn ($e) => ($e['status'] ?? null) === 'replacement'));
        $this->assertCount(1, $landing, 'the own landing still renders in its own week');
    }

    public function test_approval_only_stamp_never_phantoms_the_history(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->stampFor($session, 5, 'official_event');   // approval stamp ONLY — no audit
        $this->spanRequest($session, 5, 8, 4, '14:00:00', 2, 'approved');
        $this->sessionFor($this->lecturerA, [$this->cohortA], 1, '10:00:00', '12:00:00');   // overlapping reuse

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');

        $this->assertNull($this->cancelledEvent($events[4], $session, 5),
            'an approval-only stamp never becomes a phantom user cancellation');
    }

    public function test_pending_then_rejected_lifecycle_keeps_history_and_drops_the_landing(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5);
        [, $request] = $this->spanRequest($session, 5, 8, 4, '14:00:00', 2, 'pending');

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $history = $this->cancelledEvent($events[4], $session, 5);
        $this->assertNotNull($history);   // pending coexists with the history
        $this->assertTrue($history['proposed']);
        $pending = array_values(array_filter($events[7], fn ($e) => ($e['status'] ?? null) === 'pending'));
        $this->assertCount(1, $pending);
        $this->assertSame('request:'.$request->id, $pending[0]['eventKey']);
        $this->assertSame(4, $pending[0]['di']);     // the make-up's own day
        $this->assertSame(12, $pending[0]['start']); // 14:00
        $this->assertSame(13, $pending[0]['end']);   // the FULL 2-cell span

        $request->update(['status' => 'rejected', 'decided_at' => now()]);
        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $this->assertNotNull($this->cancelledEvent($events[4], $session, 5));   // history survives
        $this->assertSame([], array_filter($events[7], fn ($e) => in_array($e['status'], ['pending', 'replacement'], true)));
    }

    public function test_junction_less_pending_landing_keeps_its_stored_span(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        // a junction-LESS pending request (the legacy receipt shape): the
        // stored `replacement_span` is the ONLY width evidence — the mapper's
        // anchor fallback must grow the amber past the 30-min anchor.
        $anchor = TimeSlot::create([
            'semester_id' => $this->semester->id, 'class_session_id' => null,
            'week_number' => 8, 'day_of_week' => 4, 'start_time' => '14:00:00',
            'end_time' => '14:30:00', 'venue_id' => $this->venue->id,
            'status' => 'pending', 'version' => 1,
        ]);
        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerA->id,
            'class_session_id' => $session->id,
            'week_number' => 5,
            'replacement_time_slot_id' => (int) $anchor->id,
            'replacement_span' => 2,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $amber = array_values(array_filter($events[7], fn ($e) => ($e['status'] ?? null) === 'pending'));
        $this->assertCount(1, $amber);
        $this->assertSame('request:'.$request->id, $amber[0]['eventKey']);
        $this->assertSame(12, $amber[0]['start']);   // 14:00
        $this->assertSame(13, $amber[0]['end']);     // the STORED 2-cell span, not the anchor alone
    }

    public function test_withdrawn_request_removes_only_the_landing(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5);
        [, $request] = $this->spanRequest($session, 5, 8, 4, '14:00:00', 2, 'pending');
        $request->update(['status' => 'cancelled', 'decided_at' => now()]);

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $this->assertNotNull($this->cancelledEvent($events[4], $session, 5));
        $this->assertSame([], array_filter($events[7], fn ($e) => in_array($e['status'], ['pending', 'replacement'], true)));
    }

    public function test_student_payload_keeps_the_public_label_and_no_private_detail(): void
    {
        $student = User::factory()->create(['role' => 'student', 'name' => 'Student A']);
        Student::create(['user_id' => $student->id, 'student_id' => '25RSD0001', 'cohort_id' => $this->cohortA->id]);
        $otherStudent = User::factory()->create(['role' => 'student', 'name' => 'Student B']);
        Student::create(['user_id' => $otherStudent->id, 'student_id' => '25RSD0002', 'cohort_id' => $this->cohortB->id]);

        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5, 'other');

        $events = Livewire::actingAs($student)->test(StudentMyTimetable::class)->viewData('eventsByWeek');
        $history = $this->cancelledEvent($events[4], $session, 5);
        $this->assertNotNull($history, 'the student sees the cancelled history of their own cohort');
        $this->assertSame('Other', $history['cancelledReason']);   // the GENERIC public label
        $this->assertArrayNotHasKey('otherDetail', $history);
        $this->assertArrayNotHasKey('other_detail', $history);
        $this->assertArrayNotHasKey('auditDetails', $history);
        foreach ($events as $weekEvents) {
            foreach ($weekEvents as $event) {
                $this->assertArrayNotHasKey('otherDetail', $event);
                $this->assertArrayNotHasKey('other_detail', $event);
            }
        }

        // unscoped exclusion: another cohort's student sees NO events at all.
        // The render fills every week (the 14-week outer shell is the payload's
        // shape), so the honest assertion reads the FLATTENED content — zero
        // events — not the outer shell.
        $others = Livewire::actingAs($otherStudent)->test(StudentMyTimetable::class)->viewData('eventsByWeek');
        $this->assertCount(0, array_merge(...array_values($others)));
    }

    /* ── A13 — the venue page ──────────────────────────────────────────── */

    public function test_venue_reads_active_requests_even_with_stale_class_ids(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        // the request-held rows KEEP the source class id (the stale shape) —
        // the live junction still owns the event, and the row pass must not
        // also emit the source slot as a normal recurring event.
        [, $request] = $this->spanRequest($session, 3, 5, 3, '14:00:00', 2, 'pending', $session->id);

        $component = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);
        $events = $component->viewData('eventsByWeek');

        $week5 = array_values(array_filter($events[4], fn ($e) => ($e['eventKey'] ?? null) === 'request:'.$request->id));
        $this->assertCount(1, $week5);
        $this->assertSame('pending', $week5[0]['status']);
        $this->assertSame(12, $week5[0]['start']);
        $this->assertSame(13, $week5[0]['end']);
        $this->assertNotContains('occ:'.$this->semester->id.':'.$session->id.':5',
            array_column($events[4], 'eventKey'), 'the request-held rows are not also a normal event');

        // PHP counters ride the request's OWN span, never duplicates
        $totals = $component->viewData('totalsByWeek');
        $this->assertSame(1, $totals[4]['myPendingClasses']);
        $this->assertSame('1', $totals[4]['myPendingHours']);
        $this->assertSame(2, $totals[4]['pending']);
        foreach ($events as $weekEvents) {
            foreach ($weekEvents as $event) {
                $this->assertArrayNotHasKey('other_detail', $event);   // no private reveal
            }
        }
    }

    public function test_venue_backfills_cancelled_history_after_originals_cleared(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 3);
        $this->clearedOriginalRows($session, 3, 4);   // released: available + ids nulled

        $component = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);
        $events = $component->viewData('eventsByWeek');

        $history = $this->cancelledEvent($events[2], $session, 3);
        $this->assertNotNull($history, 'the cancelled history survives cleared original rows');
        $this->assertSame('user_cancelled', $history['cancellationProvenance']);
        $this->assertSame(1, $history['di']);        // the ORIGINAL room/day/range
        $this->assertSame(4, $history['start']);
        $this->assertSame(7, $history['end']);
        $this->assertSame('B100', $history['venue']);

        // history adds ZERO room occupancy (D7)
        $totals = $component->viewData('totalsByWeek');
        $this->assertSame(0, $totals[2]['occupied']);
        $this->assertSame(0, $totals[2]['myClasses']);
    }

    public function test_venue_released_stamp_only_stays_suppressed(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->stampFor($session, 3, 'official_event');   // approval stamp, NO audit
        $this->clearedOriginalRows($session, 3, 4);
        $this->spanRequest($session, 3, 8, 4, '14:00:00', 2, 'approved');

        $component = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);
        $events = $component->viewData('eventsByWeek');

        $this->assertNull($this->cancelledEvent($events[2], $session, 3),
            'the approval-only stamp keeps today\'s suppression in all cases');
        $replacement = array_values(array_filter($events[7], fn ($e) => ($e['status'] ?? null) === 'replacement'));
        $this->assertCount(1, $replacement);
    }

    public function test_venue_released_user_cancelled_own_approved_suppresses_original(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 3);
        $this->clearedOriginalRows($session, 3, 4);
        // the exact original range is re-requested and approved; its occupied
        // rows keep the stale source id (A08's protection shape)
        [$anchor, $request] = $this->spanRequest($session, 3, 3, 1, '10:00:00', 4, 'approved', $session->id);

        $component = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);
        $events = $component->viewData('eventsByWeek');
        $week3 = $events[2];

        // S16 revision 2: the OWN-approved original is suppressed even though
        // its landing sits at the exact original coordinates — geometry is
        // not identity
        $this->assertNull($this->cancelledEvent($week3, $session, 3),
            'an own-approved original is suppressed on the venue surface too');
        // the approved landing still renders exactly once at the full span
        $landing = array_values(array_filter($week3, fn ($e) => ($e['eventKey'] ?? null) === 'request:'.$request->id));
        $this->assertCount(1, $landing);
        $this->assertSame('replacement', $landing[0]['status']);
        $this->assertSame(4, $landing[0]['start']);
        $this->assertSame(7, $landing[0]['end']);

        // the UNION summary is unchanged: the landing's 4 real cells, zero
        // history inflation
        $totals = $component->viewData('totalsByWeek');
        $this->assertSame(4, $totals[2]['occupied']);
        $this->assertSame(1, $totals[2]['myClasses']);
        $this->assertSame('2', $totals[2]['myHours']);
    }

    public function test_venue_distinct_same_start_identities_never_merge(): void
    {
        $cancelled = $this->sessionFor($this->lecturerA);
        $this->cancelFor($cancelled, 5);
        $this->clearedOriginalRows($cancelled, 5, 4);
        // a DIFFERENT session now runs the same room/week/day/start
        $running = $this->sessionFor($this->lecturerB, [$this->cohortB], 1, '10:00:00', '12:00:00');
        for ($k = 0; $k < 4; $k++) {
            $from = Carbon::parse('10:00')->addMinutes($k * 30);
            TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => $running->id,
                'week_number' => 5,
                'day_of_week' => 1,
                'start_time' => $from->format('H:i:s'),
                'end_time' => $from->copy()->addMinutes(30)->format('H:i:s'),
                'venue_id' => $this->venue->id,
                'status' => 'occupied',
                'version' => 1,
            ]);
        }

        $component = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);
        $week5 = $component->viewData('eventsByWeek')[4];

        $this->assertCount(2, $week5, 'same start, two DISTINCT identities — no twin fusion');
        $keys = array_column($week5, 'eventKey');
        $this->assertEqualsCanonicalizing([
            TimetableEventIdentity::occurrenceKey((int) $this->semester->id, (int) $cancelled->id, 5),
            TimetableEventIdentity::occurrenceKey((int) $this->semester->id, (int) $running->id, 5),
        ], $keys);
        $this->assertSame(4, $week5[0]['start']);
        $this->assertSame(4, $week5[1]['start']);
    }

    /* ── A21 — the real room matrix (green/bookable decisions) ────────── */

    public function test_venue_precleanup_occupied_cells_read_blocked_not_free(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 3);
        $this->rawOccupiedRows($session, 3, 4);   // 10:00–12:00 = indices 4..7, STILL occupied

        $component = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);

        // the history still renders from its own evidence — the layout keeps it
        $this->assertNotNull($this->cancelledEvent($component->viewData('eventsByWeek')[2], $session, 3));

        // the matrix carries the ACTUAL occupancy: the raw range is blocked,
        // never a bookable free offer under the history (the pre-cleanup
        // false-green blocker)
        $matrix = $component->viewData('roomCellStatesByWeek');
        foreach ([4, 5, 6, 7] as $hi) {
            $this->assertSame('blocked', $matrix[2]['1:'.$hi]);
        }
        // a cell with no offer row is ABSENT (missing = no offer, fail closed)
        $this->assertArrayNotHasKey('1:0', $matrix[2]);
        // the union summary keeps the real DB occupancy, no history inflation
        $this->assertSame(4, $component->viewData('totalsByWeek')[2]['occupied']);
    }

    public function test_venue_released_available_cells_read_free_for_the_base_row(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 3);
        $this->clearedOriginalRows($session, 3, 4);   // released: available, ids nulled

        $component = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);

        $this->assertNotNull($this->cancelledEvent($component->viewData('eventsByWeek')[2], $session, 3));
        $matrix = $component->viewData('roomCellStatesByWeek');
        foreach ([4, 5, 6, 7] as $hi) {
            $this->assertSame('free', $matrix[2]['1:'.$hi]);
        }
        // and the released rows add zero occupancy (the D7 law)
        $this->assertSame(0, $component->viewData('totalsByWeek')[2]['occupied']);
    }

    public function test_the_shared_evidence_predicate_and_scoped_audit_pairs(): void
    {
        $mine = $this->sessionFor($this->lecturerA);
        $this->cancelFor($mine, 5);
        $other = $this->sessionFor($this->lecturerB);
        $this->cancelFor($other, 6);

        // SCOPED: the requested session ids bound the SQL audit scan — another
        // session's cancellation never leaks into this read (the pre-fix bug
        // computed $ids but scanned and returned every audit regardless).
        $this->assertSame([$mine->id.':5' => true], TimetableEventIdentity::userCancelledAuditPairs([$mine->id]));
        $this->assertSame([], TimetableEventIdentity::userCancelledAuditPairs([]));

        // THE shared predicate contract (CancelledOccurrenceEvidence)
        $details = ['class_session_id' => (int) $mine->id, 'week_number' => 5];
        $this->assertSame(['session' => (int) $mine->id, 'week' => 5],
            CancelledOccurrenceEvidence::pairFromDetails($details));
        $this->assertTrue(CancelledOccurrenceEvidence::isUserCancellationAudit('class_cancelled', $details));
        // not user evidence: the undo action, a maintenance receipt, an
        // unreadable/invalid pair, a week outside the semester window
        $this->assertFalse(CancelledOccurrenceEvidence::isUserCancellationAudit('class_cancel_undone', $details));
        $this->assertFalse(CancelledOccurrenceEvidence::isUserCancellationAudit('class_cancelled',
            ['class_session_id' => (int) $mine->id, 'week_number' => 5, 'maintenance' => true]));
        $this->assertNull(CancelledOccurrenceEvidence::pairFromDetails(['week_number' => 5]));
        $this->assertNull(CancelledOccurrenceEvidence::pairFromDetails(['class_session_id' => 0, 'week_number' => 5]));
        $this->assertNull(CancelledOccurrenceEvidence::pairFromDetails(['class_session_id' => $mine->id, 'week_number' => 0]));
        $this->assertFalse(CancelledOccurrenceEvidence::isUserCancellationAudit('class_cancelled',
            ['class_session_id' => (int) $mine->id, 'week_number' => 15]));
        $this->assertTrue(CancelledOccurrenceEvidence::isUserCancellationAudit('class_cancelled',
            ['class_session_id' => (int) $mine->id, 'week_number' => 14]));
        // maintenance flag: truthy marks it, falsy does not
        $this->assertTrue(CancelledOccurrenceEvidence::isMaintenanceDetails(['maintenance' => 1]));
        $this->assertFalse(CancelledOccurrenceEvidence::isMaintenanceDetails(['maintenance' => 0]));
    }

    /* ── R203 — the all-4-surface matrix, own-approved locations and the
        unrelated-source approval control (spec S16, revision 2) ─────────── */

    /**
     * A DEAD request's LEGAL persisted end-state: released landing cells
     * (available, version bumped, owner nulled) and NO junctions — the shape
     * the unchanged release actions leave behind, built directly instead of
     * re-running them. An auto-rejected row carries the system reason and no
     * approver; a PL-decided row carries the deciding user.
     */
    private function deadRequest(ClassSession $session, int $occurrenceWeek, int $anchorWeek, int $day, string $start, int $cells, string $status, ?int $approverId, ?string $rejectionReason): ReplacementRequest
    {
        $first = null;
        for ($k = 0; $k < $cells; $k++) {
            $from = Carbon::parse($start)->addMinutes($k * 30);
            $slot = TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => null,
                'week_number' => $anchorWeek,
                'day_of_week' => $day,
                'start_time' => $from->format('H:i:s'),
                'end_time' => $from->copy()->addMinutes(30)->format('H:i:s'),
                'venue_id' => $this->venue->id,
                'status' => 'available',
                'version' => 2,
            ]);
            $first ??= $slot;
        }

        return ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $session->lecturer_id,
            'class_session_id' => $session->id,
            'week_number' => $occurrenceWeek,
            'replacement_time_slot_id' => (int) $first->id,
            'replacement_span' => $cells,
            'status' => $status,
            'approver_id' => $approverId,
            'rejection_reason' => $rejectionReason,
            'decided_at' => now(),
            'submitted_at' => now(),
        ]);
    }

    /** The request event with EXACTLY this request identity, not geometry. */
    private function requestEvent(array $weekEvents, int $requestId): ?array
    {
        foreach ($weekEvents as $event) {
            if (($event['requestId'] ?? null) === $requestId
                && ($event['eventKey'] ?? null) === TimetableEventIdentity::requestKey($requestId)) {
                return $event;
            }
        }

        return null;
    }

    /**
     * The business rows a display read must NEVER touch (S16's read-only
     * law: exceptions, audits, requests, junctions, slots, sessions),
     * serialised for a cheap deep compare.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function businessRowsSnapshot(): array
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

    /**
     * Every request lifecycle state against the SAME source occurrence:
     * cancelled week-5 Thu 13:30–14:30 (B100, cohort A), own landing week-5
     * Thu 13:00–14:00 — the reported half-hour overlap. Dead rows persist
     * their legal released end-state; "no request" books nothing.
     *
     * @return array<string, array{0: ?string, 1: string, 2: ?string}>
     */
    public static function lifecycleStatuses(): array
    {
        // [request end status, decided-by marker, stored rejection reason]
        return [
            'pending' => ['pending', 'none', null],
            'approved' => ['approved', 'pl', null],
            'rejected by the PL' => ['rejected', 'pl', 'Slot conflict — the room was rebooked for a seminar.'],
            'withdrawn by the owner' => ['cancelled', 'none', null],
            'auto-rejected by the system' => ['rejected', 'none', 'Automatically rejected by system — the 3 working hours deadline passed.'],
            'no request at all' => [null, 'none', null],
        ];
    }

    #[DataProvider('lifecycleStatuses')]
    public function test_all_four_surfaces_follow_the_request_lifecycle(?string $status, string $decidedBy, ?string $rejectionReason): void
    {
        $session = $this->sessionFor($this->lecturerA, [$this->cohortA], 4, '13:30:00', '14:30:00');
        $this->cancelFor($session, 5, 'annual_leave');
        // ANOTHER source week of the same class — never touched by the
        // week-5 request's fate (S16: other source weeks stay unaffected)
        $this->cancelFor($session, 6, 'medical_leave');

        $requestId = null;
        if ($status === 'pending' || $status === 'approved') {
            [, $request] = $this->spanRequest($session, 5, 5, 4, '13:00:00', 2, $status);
            if ($status === 'approved' && $decidedBy === 'pl') {
                $request->update(['approver_id' => $this->lecturerB->id, 'decided_at' => now()]);
            }
            $requestId = (int) $request->id;
        } elseif ($status !== null) {
            $approverId = $decidedBy === 'pl' ? $this->lecturerB->id : null;
            $requestId = (int) $this->deadRequest($session, 5, 5, 4, '13:00:00', 2, $status, $approverId, $rejectionReason)->id;
        }

        // one cohort-A student reads the student surface (the S15 precedent)
        $student = User::factory()->create(['role' => 'student', 'name' => 'Student A']);
        Student::create(['user_id' => $student->id, 'student_id' => '25RSD0001', 'cohort_id' => $this->cohortA->id]);

        $before = $this->businessRowsSnapshot();

        $staff = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $cohortAEvents = Livewire::actingAs($this->lecturerA)
            ->test(CohortTimetable::class)->viewData('eventsByCohort')[$this->cohortA->id];
        $studentEvents = Livewire::actingAs($student)->test(StudentMyTimetable::class)->viewData('eventsByWeek');
        $venueComponent = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);
        $venue = $venueComponent->viewData('eventsByWeek');

        // a display read mutates NOTHING — the six business row families are
        // byte-identical before and after all four reads
        $this->assertSame($before, $this->businessRowsSnapshot(),
            'a timetable display read must never touch business rows');

        foreach ([
            'staff MyTimetable' => $staff,
            'staff CohortTimetable cohort A' => $cohortAEvents,
            'own-cohort StudentMyTimetable' => $studentEvents,
            'VenueTimetable B100' => $venue,
        ] as $surface => $events) {
            // the request-less week-6 source keeps its history on EVERY status
            $this->assertNotNull($this->cancelledEvent($events[5], $session, 6),
                "$surface: the request-less week-6 source is unaffected");

            if ($status === 'approved') {
                // ONLY the approved status suppresses the exact original identity
                $this->assertNull($this->cancelledEvent($events[4], $session, 5),
                    "$surface: only the own-approved status suppresses the original");
                $this->assertCount(1, array_filter($events[4], fn ($e) => ($e['requestId'] ?? null) === $requestId),
                    "$surface: the approved landing appears exactly once");
                $landing = $this->requestEvent($events[4], (int) $requestId);
                $this->assertNotNull($landing, "$surface: the approved landing is emitted");
                $this->assertSame('replacement', $landing['status']);
                $this->assertSame(4, $landing['di']);
                $this->assertSame(10, $landing['start']);   // 1:00 PM — the TRUE junction span
                $this->assertSame(11, $landing['end']);     // through 2:00 PM — the half-hour overlap
                $this->assertSame('B100', $landing['venue']);
            } elseif ($status === 'pending') {
                // pending keeps BOTH: the original history and the amber landing
                $history = $this->cancelledEvent($events[4], $session, 5);
                $this->assertNotNull($history, "$surface: pending keeps the cancelled original");
                if ($surface === 'staff MyTimetable') {
                    $this->assertTrue($history['proposed'], 'the Proposed hint rides the pending pair');
                }
                $landing = $this->requestEvent($events[4], (int) $requestId);
                $this->assertNotNull($landing, "$surface: the pending landing is emitted");
                $this->assertSame('pending', $landing['status']);
                $this->assertSame(4, $landing['di']);
                $this->assertSame(10, $landing['start']);   // 1:00 PM
                $this->assertSame(11, $landing['end']);     // through 2:00 PM
                $this->assertSame('B100', $landing['venue']);
            } else {
                // a dead or absent request keeps the original and emits NO dead
                // landing anywhere — never an occupied claim just for display
                $this->assertNotNull($this->cancelledEvent($events[4], $session, 5),
                    "$surface: a dead/absent request keeps the cancelled original");
                $this->assertSame([], array_values(array_filter($events[4],
                    fn ($e) => in_array($e['status'] ?? '', ['pending', 'replacement'], true))),
                    "$surface: no dead landing block");
                if ($requestId !== null) {
                    foreach ($events as $weekEvents) {
                        $this->assertNull($this->requestEvent($weekEvents, (int) $requestId),
                            "$surface: the dead landing never rides any week");
                    }
                }
            }
        }

        // the venue page's REAL room truth: only an approved landing's two
        // cells occupy the room; a pending pair holds pending cells; dead
        // rows leave the released cells plain and the history adds nothing
        $totals = $venueComponent->viewData('totalsByWeek');
        $this->assertSame($status === 'approved' ? 2 : 0, $totals[4]['occupied'], 'venue: real room occupancy');
        $this->assertSame($status === 'pending' ? 2 : 0, $totals[4]['pending'], 'venue: real pending cells');
        $this->assertSame($status === 'approved' ? 1 : 0, $totals[4]['myClasses'], "venue: the viewer's classes");
        $this->assertSame($status === 'approved' ? '1' : '0', $totals[4]['myHours'], 'venue: real teaching hours');
    }

    /**
     * Own-approved landings at every geometry. The cancelled week-5 Thu
     * 13:30–14:30 (B100) original is suppressed on ALL FOUR surfaces while
     * the approved landing renders at its true full span in its own
     * week/day/room — landing coordinates never identify the original.
     *
     * @return array<string, array{0: int, 1: int, 2: string, 3: string}>
     */
    public static function ownApprovedLandings(): array
    {
        // [landing week, landing day, landing start, landing room]
        return [
            'exact same coordinates' => [5, 4, '13:30:00', 'B100'],
            'partial earlier half-hour' => [5, 4, '13:00:00', 'B100'],
            'partial later half-hour' => [5, 4, '14:00:00', 'B100'],
            'different week 8' => [8, 4, '13:30:00', 'B100'],
            'different day' => [5, 5, '13:30:00', 'B100'],
            'different room B101' => [5, 4, '13:30:00', 'B101'],
        ];
    }

    #[DataProvider('ownApprovedLandings')]
    public function test_own_approved_suppression_holds_at_every_landing_geometry(int $landingWeek, int $landingDay, string $landingStart, string $landingRoom): void
    {
        $session = $this->sessionFor($this->lecturerA, [$this->cohortA], 4, '13:30:00', '14:30:00');
        $this->cancelFor($session, 5, 'annual_leave');

        $landingVenue = $landingRoom === 'B100' ? $this->venue : Venue::create([
            'room_code' => 'B101', 'room_name' => 'Tutorial Room B101',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T', 'capacity' => 30,
        ]);
        [, $request] = $this->spanRequest($session, 5, $landingWeek, $landingDay, $landingStart, 2, 'approved', null, $landingVenue);
        $requestId = (int) $request->id;

        // the same half-hour index the trait's slotIndex() computes
        $startIdx = ((int) substr($landingStart, 0, 2) - 8) * 2 + intdiv((int) substr($landingStart, 3, 2), 30);
        $endIdx = $startIdx + 1;   // the TRUE 2-cell span

        $student = User::factory()->create(['role' => 'student', 'name' => 'Student A']);
        Student::create(['user_id' => $student->id, 'student_id' => '25RSD0001', 'cohort_id' => $this->cohortA->id]);

        $before = $this->businessRowsSnapshot();

        $staff = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $cohortAEvents = Livewire::actingAs($this->lecturerA)
            ->test(CohortTimetable::class)->viewData('eventsByCohort')[$this->cohortA->id];
        $studentEvents = Livewire::actingAs($student)->test(StudentMyTimetable::class)->viewData('eventsByWeek');
        $oldRoom = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);
        $oldRoomEvents = $oldRoom->viewData('eventsByWeek');
        if ($landingRoom === 'B100') {
            $landingRoomComponent = $oldRoom;
            $landingRoomEvents = $oldRoomEvents;
        } else {
            $landingRoomComponent = Livewire::withQueryParams(['venue' => 'B101'])
                ->actingAs($this->lecturerA)->test(VenueTimetable::class);
            $landingRoomEvents = $landingRoomComponent->viewData('eventsByWeek');
        }

        $this->assertSame($before, $this->businessRowsSnapshot(),
            'a timetable display read must never touch business rows');

        foreach ([
            'staff MyTimetable' => $staff,
            'staff CohortTimetable cohort A' => $cohortAEvents,
            'own-cohort StudentMyTimetable' => $studentEvents,
        ] as $surface => $events) {
            $this->assertNull($this->cancelledEvent($events[4], $session, 5),
                "$surface: the own-approved original is suppressed at the $landingRoom geometry");
            $this->assertCount(1, array_filter($events[$landingWeek - 1], fn ($e) => ($e['requestId'] ?? null) === $requestId),
                "$surface: the approved landing appears exactly once");
            $landing = $this->requestEvent($events[$landingWeek - 1], $requestId);
            $this->assertNotNull($landing, "$surface: the approved landing renders in its own week");
            $this->assertSame('replacement', $landing['status']);
            $this->assertSame($landingDay, $landing['di']);
            $this->assertSame($startIdx, $landing['start']);
            $this->assertSame($endIdx, $landing['end']);
            $this->assertSame($landingRoom, $landing['venue']);
            // no stray landing anywhere else — no source-week confusion
            foreach ($events as $w => $weekEvents) {
                if ($w === $landingWeek - 1) {
                    continue;
                }
                $this->assertNull($this->requestEvent($weekEvents, $requestId),
                    "$surface: no stray landing in week ".($w + 1));
            }
        }

        // the OLD room's venue view: the source is suppressed even when the
        // landing moved away, and the landing never leaks into B100
        $this->assertNull($this->cancelledEvent($oldRoomEvents[4], $session, 5),
            'venue B100: the source is suppressed even when the landing moved elsewhere');
        if ($landingRoom !== 'B100') {
            foreach ($oldRoomEvents as $w => $weekEvents) {
                $this->assertNull($this->requestEvent($weekEvents, $requestId),
                    'venue B100: the landing never leaks into the old room (week '.($w + 1).')');
            }
        }
        $this->assertSame($landingRoom === 'B100' ? 2 : 0, $oldRoom->viewData('totalsByWeek')[$landingWeek - 1]['occupied'],
            'venue B100: the real room occupancy follows only a landing that actually moved in');

        // the landing room's venue view: the full approved landing in its
        // proper week, with the real union occupancy
        $landingEvent = $this->requestEvent($landingRoomEvents[$landingWeek - 1], $requestId);
        $this->assertNotNull($landingEvent, "venue $landingRoom: the approved landing renders in its proper week");
        $this->assertSame('replacement', $landingEvent['status']);
        $this->assertSame($landingDay, $landingEvent['di']);
        $this->assertSame($startIdx, $landingEvent['start']);
        $this->assertSame($endIdx, $landingEvent['end']);
        $this->assertSame($landingRoom, $landingEvent['venue']);
        $landingTotals = $landingRoomComponent->viewData('totalsByWeek');
        $this->assertSame(2, $landingTotals[$landingWeek - 1]['occupied'], "venue $landingRoom: the landing's real occupancy");
        $this->assertSame(1, $landingTotals[$landingWeek - 1]['myClasses'], "venue $landingRoom: the viewer's landing class");
        $this->assertSame('1', $landingTotals[$landingWeek - 1]['myHours'], "venue $landingRoom: the landing's real hours");
    }

    /* ── R203 — the unrelated-source approval control (spec S16) ────────── */

    public function test_another_sources_approval_never_suppresses_this_original(): void
    {
        // source A: cancelled, NO own approved request
        $sourceA = $this->sessionFor($this->lecturerA, [$this->cohortA], 4, '13:30:00', '14:30:00');
        $this->cancelFor($sourceA, 5, 'annual_leave');
        // source B: ANOTHER occurrence of the same lecturer, cancelled AND
        // approved — its landing overlaps source A's original coordinates
        // EXACTLY. Suppression keys on the SOURCE identity, never geometry.
        $sourceB = $this->sessionFor($this->lecturerA, [$this->cohortB], 4, '13:30:00', '14:30:00');
        $this->cancelFor($sourceB, 5, 'medical_leave');
        [, $requestB] = $this->spanRequest($sourceB, 5, 5, 4, '13:30:00', 2, 'approved');
        $requestBId = (int) $requestB->id;

        $student = User::factory()->create(['role' => 'student', 'name' => 'Student A']);
        Student::create(['user_id' => $student->id, 'student_id' => '25RSD0001', 'cohort_id' => $this->cohortA->id]);

        $before = $this->businessRowsSnapshot();

        $staff = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $cohortAEvents = Livewire::actingAs($this->lecturerA)
            ->test(CohortTimetable::class)->viewData('eventsByCohort')[$this->cohortA->id];
        $studentEvents = Livewire::actingAs($student)->test(StudentMyTimetable::class)->viewData('eventsByWeek');
        $venueComponent = Livewire::withQueryParams(['venue' => 'B100'])
            ->actingAs($this->lecturerA)->test(VenueTimetable::class);
        $venue = $venueComponent->viewData('eventsByWeek');

        $this->assertSame($before, $this->businessRowsSnapshot(),
            'a timetable display read must never touch business rows');

        // EVERY surface: source A STAYS — another source's approval is not
        // evidence, even at identical/overlapping coordinates
        foreach ([
            'staff MyTimetable' => $staff,
            'staff CohortTimetable cohort A' => $cohortAEvents,
            'own-cohort StudentMyTimetable' => $studentEvents,
            'VenueTimetable B100' => $venue,
        ] as $surface => $events) {
            $history = $this->cancelledEvent($events[4], $sourceA, 5);
            $this->assertNotNull($history, "$surface: source A stays — the approval belongs to another source");
            $this->assertSame('user_cancelled', $history['cancellationProvenance']);
            $this->assertSame('Annual Leave', $history['cancelledReason']);
            $this->assertSame(11, $history['start']);   // the ORIGINAL 1:30–2:30 PM range
            $this->assertSame(12, $history['end']);
        }

        // where source B is IN SCOPE, its own approval suppresses IT and its
        // landing renders exactly once at the overlapping full span; where
        // source B is out of scope (the cohort-A views), neither appears
        foreach (['staff MyTimetable' => $staff, 'VenueTimetable B100' => $venue] as $surface => $events) {
            $this->assertNull($this->cancelledEvent($events[4], $sourceB, 5),
                "$surface: source B's OWN approval suppresses it");
            $landing = $this->requestEvent($events[4], $requestBId);
            $this->assertNotNull($landing, "$surface: the other-source landing is emitted");
            $this->assertSame('replacement', $landing['status']);
            $this->assertSame(4, $landing['di']);
            $this->assertSame(11, $landing['start']);
            $this->assertSame(12, $landing['end']);
        }
        foreach (['staff CohortTimetable cohort A' => $cohortAEvents, 'own-cohort StudentMyTimetable' => $studentEvents] as $surface => $events) {
            $this->assertNull($this->requestEvent($events[4], $requestBId),
                "$surface: the cohort-B landing is out of the cohort-A scope");
        }

        // the venue union counts the real landing cells, zero history inflation
        $totals = $venueComponent->viewData('totalsByWeek');
        $this->assertSame(2, $totals[4]['occupied']);
        $this->assertSame(1, $totals[4]['myClasses']);
        $this->assertSame('1', $totals[4]['myHours']);
    }
}
