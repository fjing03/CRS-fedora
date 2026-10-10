<?php

namespace Tests\Feature;

use App\Livewire\MyRequestHistory;
use App\Livewire\ReplacementHistory;
use App\Models\AuditLog;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * replacement-flow-wiring spec (task 2.4): both history pages are 100% the
 * frozen UI design — the components are data bridges emitting the mock's
 * exact row shapes (MockData.requests / MockData.replacementHistory) from
 * real rows, plus the REAL cancel (FR 2.10) behind the frozen confirm modals.
 * The student page is view-only with cohort scoping + the frozen
 * past/upcoming filter client-side (FR 1.4).
 */
final class ReplacementHistoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $lecturerA;

    private User $lecturerB;

    private User $studentA;

    private User $studentB;

    private Cohort $cohortA;

    private Cohort $cohortB;

    private Semester $semester;

    private Venue $venue;

    private ClassSession $sessionA;

    private ClassSession $sessionB;

    private int $slotSeq = 0;

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

        $this->cohortA = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 20,
        ]);
        $this->cohortB = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 2, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 18,
        ]);

        $this->lecturerA = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer A']);
        Lecturer::create(['user_id' => $this->lecturerA->id, 'staff_id' => '6001', 'dept_id' => $dept->id, 'is_pl' => false]);
        $this->lecturerB = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer B']);
        Lecturer::create(['user_id' => $this->lecturerB->id, 'staff_id' => '6002', 'dept_id' => $dept->id, 'is_pl' => false]);

        $this->studentA = User::factory()->create(['role' => 'student', 'name' => 'Student A']);
        Student::create(['user_id' => $this->studentA->id, 'student_id' => '25RSD0001', 'cohort_id' => $this->cohortA->id]);
        $this->studentB = User::factory()->create(['role' => 'student', 'name' => 'Student B']);
        Student::create(['user_id' => $this->studentB->id, 'student_id' => '25RSD0002', 'cohort_id' => $this->cohortB->id]);

        $this->venue = Venue::create([
            'room_code' => 'B100', 'room_name' => 'Tutorial Room B100',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T', 'capacity' => 35,
        ]);
        $moduleA = Module::create(['module_code' => 'BMIT1001', 'module_name' => 'Software Engineering', 'allowed_session_types' => 'L,T']);
        $moduleB = Module::create(['module_code' => 'BMIT2002', 'module_name' => 'Databases', 'allowed_session_types' => 'L,T']);

        $this->sessionA = $this->createSession($this->lecturerA, $moduleA, [$this->cohortA]);
        $this->sessionB = $this->createSession($this->lecturerB, $moduleB, [$this->cohortB]);
    }

    private function createSession(User $lecturer, Module $module, array $cohorts): ClassSession
    {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => 1, // Tuesday in this schema (0 = Monday)
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach(collect($cohorts)->pluck('id')->all());

        return $session;
    }

    private function createSlot(int $week, string $status = 'available'): TimeSlot
    {
        // Distinct (venue, day, time, week) per call — the no-double-book
        // unique index forbids two slots sharing a cell.
        $days = [3, 4, 5, 0, 1, 2];

        return TimeSlot::create([
            'semester_id' => $this->semester->id, 'class_session_id' => null,
            'week_number' => $week, 'day_of_week' => $days[$this->slotSeq++ % 6],
            'start_time' => '14:00:00', 'end_time' => '14:30:00',
            'venue_id' => $this->venue->id, 'status' => $status,
        ]);
    }

    private function createRequest(User $proposer, ClassSession $session, int $week, string $status): ReplacementRequest
    {
        return ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $proposer->id,
            'class_session_id' => $session->id,
            'week_number' => $week,
            'replacement_time_slot_id' => $this->createSlot($week, $status === 'pending' ? 'pending' : 'available')->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);
    }

    private function mrhPayload(User $lecturer): array
    {
        $payload = null;

        Livewire::actingAs($lecturer)->test(MyRequestHistory::class)
            ->assertViewHas('payload', function (array $p) use (&$payload): bool {
                $payload = $p;

                return true;
            });

        return $payload;
    }

    private function rhPayload(User $student): array
    {
        $payload = null;

        Livewire::actingAs($student)->test(ReplacementHistory::class)
            ->assertViewHas('payload', function (array $p) use (&$payload): bool {
                $payload = $p;

                return true;
            });

        return $payload;
    }

    public function test_my_request_history_emits_own_rows_in_the_mock_shape(): void
    {
        $mine = $this->createRequest($this->lecturerA, $this->sessionA, 7, 'pending');
        $theirs = $this->createRequest($this->lecturerB, $this->sessionB, 7, 'pending');

        $payload = $this->mrhPayload($this->lecturerA);

        $this->assertCount(1, $payload['requests'], 'FR 2.15/2.11: own requests only');
        $row = $payload['requests'][0];

        // The mock row contract (HtmlBuilder.classBlock / replacementBlock /
        // formatDateTime / DetailModal all read these keys).
        $this->assertSame($mine->id, $row['id']);
        $this->assertSame('Lecturer A', $row['requester']);
        $this->assertSame('Pending', $row['status']);
        $this->assertSame('BMIT1001', $row['courseCode']);
        $this->assertSame('Software Engineering', $row['courseName']);
        $this->assertSame('L', $row['classType']);
        $this->assertSame('2026-10-13', $row['classDate']); // week 7, day_of_week 1 (Tue)
        $this->assertSame('Tuesday', $row['classDay']);
        $this->assertSame('10:00', $row['timeStart']);
        $this->assertSame('12:00', $row['timeEnd']);
        $this->assertSame(2, $row['duration']);
        $this->assertSame('B100', $row['venue']);
        $this->assertSame(20, $row['totalStudents']);
        $this->assertSame(['RSD3 (S1) G1'], $row['cohorts']);
        $this->assertSame([20], $row['cohortCounts']);
        $this->assertSame('2026-10-15', $row['replacementDate']); // slot week 7, day 3 (Thu)
        $this->assertSame('14:00 – 14:30', $row['replacementTime']);
        $this->assertSame('B100', $row['replacementVenue']);
        $this->assertNull($row['reviewedBy']);
        $this->assertNotNull($row['requestedAt']);

        // The bridge must never carry the other lecturer's row.
        $this->assertFalse(collect($payload['requests'])->contains(
            fn (array $r): bool => $r['id'] === $theirs->id,
        ));
    }

    public function test_cancel_request_persists_and_frees_the_slot(): void
    {
        $request = $this->createRequest($this->lecturerA, $this->sessionA, 7, 'pending');
        $slot = $request->replacementTimeSlot;

        $testable = Livewire::actingAs($this->lecturerA)->test(MyRequestHistory::class);
        $testable->call('cancelRequest', (int) $request->id);
        $ok = \Livewire\invade($testable)->lastState->getEffects()['returns'][0] ?? null;

        $this->assertTrue($ok, 'FR 2.10: the frozen confirm modal really cancels');

        $request->refresh();
        $this->assertSame('cancelled', $request->status);
        $this->assertNotNull($request->decided_at);

        $slot->refresh();
        $this->assertSame('available', $slot->status, 'D6: the slot returns to available');
        $this->assertSame(2, (int) $slot->version, 'D6: release bumps version');

        $audit = AuditLog::query()->where('action', 'cancelled')->firstOrFail();
        $this->assertSame((int) $slot->id, (int) $audit->time_slot_id);
    }

    public function test_cancel_request_refuses_a_decided_request(): void
    {
        $request = $this->createRequest($this->lecturerA, $this->sessionA, 7, 'approved');

        $testable = Livewire::actingAs($this->lecturerA)->test(MyRequestHistory::class);
        $testable->call('cancelRequest', (int) $request->id);
        $ok = \Livewire\invade($testable)->lastState->getEffects()['returns'][0] ?? null;

        $this->assertFalse($ok, 'FR 2.10: only pending requests are cancellable');
        $this->assertSame('approved', $request->refresh()->status);
    }

    public function test_batch_cancel_returns_only_the_cancelled_ids(): void
    {
        $first = $this->createRequest($this->lecturerA, $this->sessionA, 7, 'pending');
        $second = $this->createRequest($this->lecturerA, $this->sessionA, 8, 'pending');
        $decided = $this->createRequest($this->lecturerA, $this->sessionA, 9, 'approved');

        $testable = Livewire::actingAs($this->lecturerA)->test(MyRequestHistory::class);
        $testable->call('cancelRequests', json_encode([$first->id, $second->id, $decided->id]));
        $okIds = \Livewire\invade($testable)->lastState->getEffects()['returns'][0] ?? [];

        $this->assertSame([(int) $first->id, (int) $second->id], $okIds, 'only the pending ones cancel');
        $this->assertSame('cancelled', $first->refresh()->status);
        $this->assertSame('cancelled', $second->refresh()->status);
        $this->assertSame('approved', $decided->refresh()->status);
    }

    public function test_replacement_history_emits_own_cohort_rows_in_the_mock_shape(): void
    {
        $pending = $this->createRequest($this->lecturerA, $this->sessionA, 7, 'pending');
        $approved = $this->createRequest($this->lecturerA, $this->sessionA, 8, 'approved');
        $this->createRequest($this->lecturerB, $this->sessionB, 7, 'approved'); // other cohort

        $payload = $this->rhPayload($this->studentA);

        $this->assertCount(2, $payload['replacementHistory'], 'FR 1.4: my cohort, pending + approved');
        $this->assertSame([], $payload['cohortTimetable']['rsd3g2Flags']);

        $byId = collect($payload['replacementHistory'])->keyBy('id');

        // Pending: the frozen semantic — no confirmed new slot.
        $p = $byId[$pending->id];
        $this->assertSame('pending', $p['status']);
        $this->assertNull($p['newDay']);
        $this->assertSame(6, $p['week']); // week 7 → 0-based index 6
        $this->assertSame('BMIT1001', $p['code']);
        $this->assertSame('Lecturer A', $p['lecturer']);
        $this->assertSame('10:00 – 12:00', $p['originalTime']);
        $this->assertSame('B100', $p['originalVenue']);

        // Approved: the confirmed new slot fills the frozen New Slot columns.
        $a = $byId[$approved->id];
        $this->assertSame('replacement', $a['status']);
        $this->assertSame(7, $a['week']);
        $this->assertSame('Friday', $a['newDay']); // slotSeq cycle: pending→day 3, approved→day 4
        $this->assertSame('14:00 – 14:30', $a['newTime']);
        $this->assertSame('B100', $a['newVenue']);
    }

    public function test_replacement_history_never_leaks_other_cohorts(): void
    {
        $this->createRequest($this->lecturerA, $this->sessionA, 7, 'approved');

        Livewire::actingAs($this->studentB)->test(ReplacementHistory::class)
            ->assertViewHas('payload', fn (array $payload): bool => $payload['replacementHistory'] === []);
    }

    public function test_replacement_history_is_view_only_for_students(): void
    {
        $this->createRequest($this->lecturerA, $this->sessionA, 7, 'approved');

        Livewire::actingAs($this->studentA)
            ->test(ReplacementHistory::class)
            ->assertDontSee('wire:click')
            ->assertDontSee('Cancel Request');
    }
}
