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
 * replacement-flow-wiring spec (task 2.4): own request history with
 * cancel-pending (FR 2.10/2.15) and the student's cohort-scoped, upcoming-only
 * replacement history, view-only (FR 1.4).
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

    private Module $moduleA;

    private Module $moduleB;

    private ClassSession $sessionA;

    private ClassSession $sessionB;

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
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2023',
        ]);
        $this->cohortB = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 2, 'academic_year' => '2025/26', 'intake' => 'June 2023',
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
        $this->moduleA = Module::create(['module_code' => 'BMIT1001', 'module_name' => 'Software Engineering', 'allowed_session_types' => 'L,T']);
        $this->moduleB = Module::create(['module_code' => 'BMIT2002', 'module_name' => 'Databases', 'allowed_session_types' => 'L,T']);

        $this->sessionA = $this->createSession($this->lecturerA, $this->moduleA, [$this->cohortA]);
        $this->sessionB = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB]);
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

    private int $slotSeq = 0;

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

    private function createRequest(User $proposer, ClassSession $session, int $week, string $status, ?string $remarks = null): ReplacementRequest
    {
        return ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $proposer->id,
            'class_session_id' => $session->id,
            'week_number' => $week,
            'replacement_time_slot_id' => $this->createSlot($week, $status === 'pending' ? 'pending' : 'available')->id,
            'status' => $status,
            'remarks' => $remarks,
            'submitted_at' => now(),
        ]);
    }

    public function test_my_request_history_lists_own_requests_only(): void
    {
        $this->createRequest($this->lecturerA, $this->sessionA, 7, 'pending', 'club event');
        $this->createRequest($this->lecturerB, $this->sessionB, 7, 'pending', 'databases swap');

        Livewire::actingAs($this->lecturerA)
            ->test(MyRequestHistory::class)
            ->assertSee('BMIT1001')
            ->assertSee('club event')
            ->assertSee('badge-pending')
            ->assertDontSee('databases swap')
            ->assertDontSee('BMIT2002');
    }

    public function test_cancel_pending_frees_the_slot_and_audits(): void
    {
        $request = $this->createRequest($this->lecturerA, $this->sessionA, 7, 'pending');
        $slot = $request->replacementTimeSlot;

        Livewire::actingAs($this->lecturerA)
            ->test(MyRequestHistory::class)
            ->call('cancelConfirm', (int) $request->id)
            ->assertSee('Confirm Cancellation')
            ->call('cancel');

        $request->refresh();
        $this->assertSame('cancelled', $request->status, 'FR 2.10: the request is cancelled');
        $this->assertNotNull($request->decided_at);

        $slot->refresh();
        $this->assertSame('available', $slot->status, 'FR 2.10: the slot returns to available');
        $this->assertSame(2, (int) $slot->version, 'D6: release bumps version');

        $audit = AuditLog::query()->where('action', 'cancelled')->firstOrFail();
        $this->assertSame((int) $slot->id, (int) $audit->time_slot_id);
    }

    public function test_replacement_history_scopes_to_own_cohort_and_upcoming_weeks(): void
    {
        // Cohort A's class replaced in week 7 (upcoming) — visible to student A.
        $upcoming = $this->createRequest($this->lecturerA, $this->sessionA, 7, 'approved');
        $upcoming->fill(['approver_id' => $this->lecturerB->id])->save();
        // Same class replaced into week 1 (already past) — hidden (FR 1.4).
        $past = $this->createRequest($this->lecturerA, $this->sessionA, 1, 'approved');
        $past->fill(['approver_id' => $this->lecturerB->id])->save();
        // Cohort B's class replaced — hidden from student A.
        $otherCohort = $this->createRequest($this->lecturerB, $this->sessionB, 7, 'approved');
        $otherCohort->fill(['approver_id' => $this->lecturerA->id])->save();

        Livewire::actingAs($this->studentA)
            ->test(ReplacementHistory::class)
            ->assertSee('BMIT1001')
            ->assertSee('Week 7')
            ->assertSee('badge-replacement')
            ->assertDontSee('BMIT2002')
            ->assertDontSee('Week 1 ·'); // the past-week row's slot cell
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
