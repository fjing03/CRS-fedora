<?php

namespace Tests\Feature;

use App\Livewire\ReplacementArrangement;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * replacement-flow-wiring spec (task 2.3): the arrangement is 100% the frozen
 * UI design — the component is a data bridge emitting the mock's exact data
 * shapes (venueSlots triples, cohortTimetable conflict events, courses,
 * venues) from real rows, plus submitBlocks() running the real pipeline
 * (D8 rules → SubmitReplacementRequest: engine pre-check, OCC reserve, D5).
 */
final class ReplacementArrangementTest extends TestCase
{
    use RefreshDatabase;

    private User $lecturerA;

    private User $lecturerB;

    private Semester $semester;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        // Sequences are NOT transactional — TimetableWiringTest asserts literal
        // ids, so every test here starts from a pristine counter.
        foreach (['replacement_requests', 'class_exceptions', 'audit_logs'] as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), 1, false)");
        }

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

        $this->venue = Venue::create([
            'room_code' => 'B100', 'room_name' => 'Tutorial Room B100',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T', 'capacity' => 35,
        ]);
        Venue::create([
            'room_code' => 'B005', 'room_name' => 'Networking Lab B005',
            'room_type' => 'lab', 'allowed_session_types' => 'L,T', 'capacity' => 30,
        ]);

        $this->lecturerA = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer A']);
        Lecturer::create(['user_id' => $this->lecturerA->id, 'staff_id' => '6001', 'dept_id' => $dept->id, 'is_pl' => false]);
        $this->lecturerB = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer B']);
        Lecturer::create(['user_id' => $this->lecturerB->id, 'staff_id' => '6002', 'dept_id' => $dept->id, 'is_pl' => false]);

        $cohortA = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 20,
        ]);
        $cohortB = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 2, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 18,
        ]);

        $moduleA = Module::create(['module_code' => 'BMIT1001', 'module_name' => 'Software Engineering', 'allowed_session_types' => 'L,T']);
        $moduleB = Module::create(['module_code' => 'BMIT2002', 'module_name' => 'Databases', 'allowed_session_types' => 'L,T']);

        // A's class: Monday 10:00–12:00 at B100 (slot indices 4–7).
        $this->createSession($this->lecturerA, $moduleA, [$cohortA]);
        $this->createSession($this->lecturerB, $moduleB, [$cohortB]);

        Holiday::create(['semester_id' => $this->semester->id, 'week_number' => 5, 'day_of_week' => 1, 'label' => 'Test Holiday']);
    }

    private function createSession(User $lecturer, Module $module, array $cohorts): ClassSession
    {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => 1,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach(collect($cohorts)->pluck('id')->all());

        return $session;
    }

    private function createSlot(int $week, int $day, string $start, string $status = 'available'): TimeSlot
    {
        return TimeSlot::create([
            'semester_id' => $this->semester->id, 'class_session_id' => null,
            'week_number' => $week, 'day_of_week' => $day,
            'start_time' => $start, 'end_time' => date('H:i:s', strtotime($start) + 1800),
            'venue_id' => $this->venue->id, 'status' => $status,
        ]);
    }

    private function payloadOf(User $lecturer): array
    {
        $payload = null;

        Livewire::actingAs($lecturer)->test(ReplacementArrangement::class)
            ->assertViewHas('payload', function (array $p) use (&$payload): bool {
                $payload = $p;

                return true;
            });

        return $payload;
    }

    public function test_arrangement_payload_carries_the_mock_shapes(): void
    {
        $payload = $this->payloadOf($this->lecturerA);

        // generateWeekData contract: startDate + weeks (+ chipText).
        $this->assertSame('2026-08-31', $payload['semester']['startDate']);
        $this->assertSame(14, $payload['semester']['weeks']);
        $this->assertNotSame('', $payload['semester']['chipText']);

        // VenueDropdown contract: code/name/type/capacity, real room types mapped.
        $b100 = collect($payload['venues'])->firstWhere('code', 'B100');
        $this->assertSame('Tutorial', $b100['type']);
        $this->assertSame(35, $b100['capacity']);
        $b005 = collect($payload['venues'])->firstWhere('code', 'B005');
        $this->assertSame('Lab', $b005['type']);

        // Course shape (strip + venue filter): cohorts spaced, counts summed.
        $course = collect($payload['courses'])->firstWhere('code', 'BMIT1001');
        $this->assertSame('Software Engineering', $course['name']);
        $this->assertSame('L', $course['type']);
        $this->assertSame(['RSD3 (S1) G1'], $course['cohorts']);
        $this->assertSame([20], $course['cohortCounts']);
        $this->assertSame(20, $course['studentCount']);
    }

    public function test_arrangement_venue_slots_carry_the_mock_triple_states(): void
    {
        $this->createSlot(6, 3, '14:00:00', 'occupied');                      // → 1
        $pendingMine = $this->createSlot(6, 4, '09:00:00', 'pending');        // → 3
        $pendingOther = $this->createSlot(6, 5, '09:00:00', 'pending');       // → 4
        $sessionA = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->firstOrFail();
        $sessionB = ClassSession::query()->where('lecturer_id', $this->lecturerB->id)->firstOrFail();
        ReplacementRequest::create([
            'semester_id' => $this->semester->id, 'proposer_id' => $this->lecturerA->id,
            'class_session_id' => $sessionA->id, 'week_number' => 6,
            'replacement_time_slot_id' => $pendingMine->id, 'status' => 'pending', 'submitted_at' => now(),
        ]);
        ReplacementRequest::create([
            'semester_id' => $this->semester->id, 'proposer_id' => $this->lecturerB->id,
            'class_session_id' => $sessionB->id, 'week_number' => 6,
            'replacement_time_slot_id' => $pendingOther->id, 'status' => 'pending', 'submitted_at' => now(),
        ]);

        $payload = $this->payloadOf($this->lecturerA);
        $week5 = $payload['venueSlotsByWeek'][5]['B100'] ?? []; // week 6 → JS index 5

        $this->assertContains([3, 12, 1], $week5, 'occupied → the mock\'s status 1 (14:00 = hour 12)');
        $this->assertContains([4, 2, 3], $week5, 'my pending → status 3');
        $this->assertContains([5, 2, 4], $week5, 'others\' pending → status 4 (Reserved by Others)');
    }

    public function test_arrangement_conflict_events_feed_the_slot_picker(): void
    {
        $sessionA = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->firstOrFail();
        ClassException::create(['class_session_id' => $sessionA->id, 'week_number' => 3, 'reason' => 'annual_leave']);

        $payload = $this->payloadOf($this->lecturerA);
        $events = collect($payload['conflictEvents']);

        // PH on week 5 → 'conflict' occurrence, 30-min indices 4–7 (10:00–12:00).
        $ph = $events->first(fn (array $e): bool => $e['event']['status'] === 'conflict');
        $this->assertNotNull($ph);
        $this->assertSame(4, $ph['week']); // week 5 → 0-based index 4
        $this->assertSame(1, $ph['event']['di']);
        $this->assertSame(4, $ph['event']['start']);
        $this->assertSame(7, $ph['event']['end']);
        $this->assertSame('B100', $ph['event']['venue']);
        $this->assertSame('BMIT1001', $ph['event']['code']);

        // FR 2.16 exception → 'cancelled' occurrence (the picker's Cancelled badge).
        $cancelled = $events->first(fn (array $e): bool => $e['event']['status'] === 'cancelled');
        $this->assertNotNull($cancelled);
        $this->assertSame(2, $cancelled['week']);
    }

    public function test_submit_blocks_creates_requests_and_audits(): void
    {
        $this->createSlot(6, 3, '14:00:00'); // bookable anchor (Thursday 14:00 = hour 12)

        $testable = Livewire::actingAs($this->lecturerA)->test(ReplacementArrangement::class);
        $testable->call('submitBlocks',
            json_encode([['venue' => 'B100', 'week' => 5, 'day' => 3, 'startHour' => 12, 'endHour' => 16]]),
            'BMIT1001',
            json_encode(['day' => 1, 'start' => 4, 'venue' => 'B100']),
        );
        $results = \Livewire\invade($testable)->lastState->getEffects()['returns'][0] ?? null;

        $this->assertNotNull($results, 'submitBlocks must return per-block results');
        $this->assertTrue($results[0]['ok'], 'FR 2.9: the block books through the real pipeline');

        $sessionA = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->firstOrFail();
        $request = ReplacementRequest::query()
            ->where('proposer_id', $this->lecturerA->id)
            ->where('class_session_id', $sessionA->id)
            ->firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame(6, (int) $request->week_number);

        $slot = TimeSlot::query()->where('week_number', 6)->where('day_of_week', 3)->firstOrFail();
        $this->assertSame('pending', $slot->status, 'FR 4.8: the anchor slot is reserved');

        $audit = AuditLog::query()->where('replacement_request_id', $request->id)->firstOrFail();
        $this->assertSame('submitted', $audit->action);
    }

    public function test_submit_blocks_reports_conflict_for_a_taken_slot(): void
    {
        $this->createSlot(6, 3, '14:00:00', 'occupied'); // already booked

        $testable = Livewire::actingAs($this->lecturerA)->test(ReplacementArrangement::class);
        $testable->call('submitBlocks',
            json_encode([['venue' => 'B100', 'week' => 5, 'day' => 3, 'startHour' => 12, 'endHour' => 16]]),
            'BMIT1001',
            json_encode(['day' => 1, 'start' => 4, 'venue' => 'B100']),
        );
        $results = \Livewire\invade($testable)->lastState->getEffects()['returns'][0] ?? null;

        $this->assertFalse($results[0]['ok'], 'FR 4.10: the loser gets a conflict, not a 500');
        $this->assertNotSame('', (string) $results[0]['reason']);
        $this->assertSame(0, ReplacementRequest::query()->count(), 'D5: no request row survives a conflict');
    }

    public function test_submit_blocks_rejects_foreign_classes(): void
    {
        // B owns BMIT2002 — A submitting under it must be refused (FR 2.11).
        $testable = Livewire::actingAs($this->lecturerA)->test(ReplacementArrangement::class);
        $testable->call('submitBlocks',
            json_encode([['venue' => 'B100', 'week' => 5, 'day' => 3, 'startHour' => 12, 'endHour' => 16]]),
            'BMIT2002',
            json_encode(['day' => 1, 'start' => 4, 'venue' => 'B100']),
        );
        $results = \Livewire\invade($testable)->lastState->getEffects()['returns'][0] ?? null;

        $this->assertFalse($results[0]['ok']);
        $this->assertSame(0, ReplacementRequest::query()->count());
    }

    public function test_grid_busy_vectors_and_ineligible_venues_follow_d4_rule_1(): void
    {
        // Extra venues to exercise engine Vector 4 (type + headcount):
        // lecture hall L-only, tiny room (capacity below headcount), P-lab.
        $hall = Venue::create(['room_code' => 'C100', 'room_name' => 'Hall C100', 'room_type' => 'lecture_hall', 'allowed_session_types' => 'L', 'capacity' => 80]);
        $tiny = Venue::create(['room_code' => 'C050', 'room_name' => 'Tiny C050', 'room_type' => 'tutorial', 'allowed_session_types' => 'L,T,P', 'capacity' => 5]);
        $lab = Venue::create(['room_code' => 'C200', 'room_name' => 'Lab C200', 'room_type' => 'lab', 'allowed_session_types' => 'P', 'capacity' => 30]);

        $cohortA = Cohort::query()->firstOrFail(); // student_count 20
        $moduleA = Module::query()->where('module_code', 'BMIT1001')->firstOrFail();
        $moduleC = Module::create(['module_code' => 'BMIT3003', 'module_name' => 'Other Module', 'allowed_session_types' => 'L,T,P']);

        // The subject under test becomes a practical (P) — labs only.
        $sessionA = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->firstOrFail();
        $sessionA->update(['session_type' => 'P', 'venue_id' => $lab->id]);

        // The cohort is already in class Wednesday 09:00–11:00 (another lecturer).
        $cohortClass = ClassSession::create([
            'semester_id' => $this->semester->id, 'module_id' => $moduleC->id,
            'lecturer_id' => $this->lecturerB->id, 'day_of_week' => 2,
            'start_time' => '09:00:00', 'end_time' => '11:00:00',
            'venue_id' => $hall->id, 'session_type' => 'L',
        ]);
        $cohortClass->cohorts()->attach([$cohortA->id]);

        // The lecturer's own second class Tuesday 14:00–16:00 (a different cohort).
        $ownClass = ClassSession::create([
            'semester_id' => $this->semester->id, 'module_id' => $moduleC->id,
            'lecturer_id' => $this->lecturerA->id, 'day_of_week' => 1,
            'start_time' => '14:00:00', 'end_time' => '16:00:00',
            'venue_id' => $hall->id, 'session_type' => 'T',
        ]);
        $ownClass->cohorts()->attach([Cohort::skip(1)->firstOrFail()->id]);

        $payload = $this->payloadOf($this->lecturerA);

        // The lecturer's own freedom ('' key, course-independent).
        $own = $payload['busyByWeek'][''];
        $this->assertContains([1, 4, 8], $own[0], 'own Mon 10:00–12:00 class → [day 1, idx 4, idx 8)');
        $this->assertContains([1, 12, 16], $own[0], 'own Tue 14:00–16:00 class');

        // The subject's vectors add its cohort's Wednesday class.
        $subject = $payload['busyByWeek']['BMIT1001'];
        $this->assertContains([2, 2, 6], $subject[0], 'cohort Wed 09:00–11:00 class');
        $this->assertContains([1, 12, 16], $subject[0], 'lecturer vector merges in');

        // Engine Vector 4: a P class for 20 students cannot use the L-only
        // hall or the 5-seat room; the P-lab stays bookable.
        $this->assertSame(['B005', 'B100', 'C050', 'C100'], $payload['blockedVenues']['BMIT1001']);
        $this->assertSame([], $payload['blockedVenues']['']);

        // A class cancelled in the evaluated week frees its cells (engine
        // exception exclusion) — the replacement may reuse that time.
        ClassException::create(['class_session_id' => $cohortClass->id, 'week_number' => 7, 'reason' => 'public_holiday']);
        $payload = $this->payloadOf($this->lecturerA);
        $this->assertNotContains([2, 2, 6], $payload['busyByWeek']['BMIT1001'][6], 'week 7 (idx 6): cancelled cohort class frees its cells');
    }
}
