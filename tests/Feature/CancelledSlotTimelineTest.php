<?php

namespace Tests\Feature;

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
use Tests\TestCase;

/**
 * cancelled-slot-reuse A11/A12/A13 — the PAYLOAD projections (specs S13–S16).
 *
 * Fixtures are minimal RefreshDatabase builds with valid 30-minute cells,
 * full spans and the frozen 2026-08-31 semester. Everything asserts the
 * SERVER payload (eventsByWeek / totalsByWeek) — never the DOM.
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
     * @return array{0: TimeSlot, 1: ReplacementRequest, 2: list<int>}
     */
    private function spanRequest(ClassSession $session, int $occurrenceWeek, int $anchorWeek, int $day, string $start, int $cells, string $status, ?int $staleClassId = null): array
    {
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
                'venue_id' => $this->venue->id,
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

    public function test_exact_reuse_keeps_the_user_cancelled_history_with_the_landing(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5);
        [$anchor, $request] = $this->spanRequest($session, 5, 5, 1, '10:00:00', 4, 'approved');

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $week5 = $events[4];

        $history = $this->cancelledEvent($week5, $session, 5);
        $this->assertNotNull($history, 'the proven original keeps its history above its exact-reuse landing');
        $this->assertSame('user_cancelled', $history['cancellationProvenance']);
        $this->assertSame('Annual Leave', $history['cancelledReason']);
        $this->assertSame(1, $history['di']);
        $this->assertSame(4, $history['start']);
        $this->assertSame(7, $history['end']);

        $landing = array_values(array_filter($week5, fn ($e) => ($e['eventKey'] ?? null) === 'request:'.$request->id));
        $this->assertCount(1, $landing);
        $this->assertSame('replacement', $landing[0]['status']);
        $this->assertSame(4, $landing[0]['start']);   // the TRUE full junction span
        $this->assertSame(7, $landing[0]['end']);
    }

    public function test_partial_reuse_also_keeps_the_history(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5);
        $this->spanRequest($session, 5, 5, 1, '10:00:00', 2, 'approved');   // 10:00–11:00 inside 10:00–12:00

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $this->assertNotNull($this->cancelledEvent($events[4], $session, 5));
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

    public function test_another_in_scope_active_event_retains_the_history(): void
    {
        $session = $this->sessionFor($this->lecturerA);
        $this->cancelFor($session, 5);
        $this->spanRequest($session, 5, 8, 4, '14:00:00', 2, 'approved');   // non-overlapping own landing
        // ANOTHER class of the same lecturer now runs over the original range
        $reuse = $this->sessionFor($this->lecturerA, [$this->cohortA], 1, '10:00:00', '12:00:00');

        $events = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class)->viewData('eventsByWeek');
        $history = $this->cancelledEvent($events[4], $session, 5);
        $this->assertNotNull($history, 'the in-scope reuse keeps the history visible');
        $this->assertSame('normal', $events[4][0]['status']);   // the reusing class
        $this->assertSame('occ:'.$this->semester->id.':'.$reuse->id.':5', $events[4][0]['eventKey']);
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

    public function test_venue_released_user_cancelled_reuse_keeps_history(): void
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

        $this->assertNotNull($this->cancelledEvent($week3, $session, 3),
            'the history stays above the same-coordinates approved landing');
        $landing = array_values(array_filter($week3, fn ($e) => ($e['eventKey'] ?? null) === 'request:'.$request->id));
        $this->assertCount(1, $landing);
        $this->assertSame('replacement', $landing[0]['status']);
        $this->assertSame(4, $landing[0]['start']);
        $this->assertSame(7, $landing[0]['end']);

        // the UNION summary: the landing's 4 real cells, zero history inflation
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
}
