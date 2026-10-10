<?php

namespace Tests\Feature;

use App\Livewire\ReplacementArrangement;
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
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * replacement-flow-wiring spec (task 2.3): arrangement prefill (FR 2.5),
 * 30-minute grid with table-B cell states (FR 4.11, D4), contiguous block
 * selection (§3), submit through the OCC action (FR 2.9/4.8–4.10), honest
 * "No available slots" (FR 4.4/4.6), ownership scoping (FR 2.11).
 */
final class ReplacementArrangementTest extends TestCase
{
    use RefreshDatabase;

    private User $lecturerA;

    private User $lecturerB;

    private Cohort $cohort;

    private Semester $semester;

    private Venue $venue;

    private Module $module;

    private ClassSession $session;

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

        $this->cohort = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2023',
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

        // The original class: Mon 10:00–12:00 (slot index 4), 2h = 4 half-hour cells.
        $this->session = $this->createSession($this->lecturerA);
    }

    private function createSession(User $lecturer): ClassSession
    {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => 1,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach([$this->cohort->id]);

        return $session;
    }

    /**
     * A half-hour slot at the test venue (week 6, Wednesday).
     */
    private function createSlot(string $start, string $status = 'available', ?int $sessionId = null): TimeSlot
    {
        return TimeSlot::create([
            'semester_id' => $this->semester->id,
            'class_session_id' => $sessionId,
            'week_number' => 6,
            'day_of_week' => 3,
            'start_time' => $start,
            'end_time' => date('H:i:s', strtotime($start) + 1800),
            'venue_id' => $this->venue->id,
            'status' => $status,
        ]);
    }

    private function mountForLecturerA(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::withQueryParams(['code' => 'BMIT1001', 'day' => '1', 'start' => '4', 'originalVenue' => 'B100', 'week' => '6'])
            ->actingAs($this->lecturerA)
            ->test(ReplacementArrangement::class);
    }

    public function test_arrangement_prefills_from_go_to_replacement_params(): void
    {
        $this->mountForLecturerA()
            ->assertSee('BMIT1001')
            ->assertSee('Software Engineering')
            ->assertSee('B100 — Tutorial Room B100')
            ->assertSee('Original Venue');
    }

    public function test_arrangement_grid_derives_table_B_states(): void
    {
        $this->createSlot('09:00:00', 'occupied');
        $pendingOther = $this->createSlot('09:30:00', 'pending');
        $pendingMine = $this->createSlot('10:00:00', 'pending');

        $sessionB = $this->createSession($this->lecturerB);
        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerB->id,
            'class_session_id' => $sessionB->id,
            'week_number' => 6,
            'replacement_time_slot_id' => $pendingOther->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerA->id,
            'class_session_id' => $this->session->id,
            'week_number' => 5,
            'replacement_time_slot_id' => $pendingMine->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // A free 2-hour run: four adjacent half-hour cells (addendum 2b axis).
        $this->createSlot('14:00:00');
        $this->createSlot('14:30:00');
        $this->createSlot('15:00:00');
        $this->createSlot('15:30:00');

        $this->mountForLecturerA()
            ->assertSeeHtml('cell-occupied')
            ->assertSeeHtml('cell-reserved')
            ->assertSeeHtml('cell-pending')
            ->assertSeeHtml('cell-available');
    }

    public function test_arrangement_click_selects_contiguous_block(): void
    {
        $first = $this->createSlot('14:00:00');
        $this->createSlot('14:30:00');
        $this->createSlot('15:00:00');
        $this->createSlot('15:30:00');

        $this->mountForLecturerA()
            ->call('selectSlot', (int) $first->id)
            ->assertSeeHtml('cell-selected')
            ->assertSee('Your Current Selection')
            ->assertSee('2:00 PM – 4:00 PM');
    }

    public function test_arrangement_submit_creates_pending_request_and_audits(): void
    {
        $anchor = $this->createSlot('14:00:00');
        $this->createSlot('14:30:00');
        $this->createSlot('15:00:00');
        $this->createSlot('15:30:00');

        $this->mountForLecturerA()
            ->call('selectSlot', (int) $anchor->id)
            ->call('openSubmit')
            ->set('remarks', 'club event')
            ->call('submit');

        $request = ReplacementRequest::query()
            ->where('proposer_id', $this->lecturerA->id)
            ->where('class_session_id', $this->session->id)
            ->firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame((int) $anchor->id, (int) $request->replacement_time_slot_id);
        $this->assertSame('club event', $request->remarks);

        $anchor->refresh();
        $this->assertSame('pending', $anchor->status, 'FR 4.8: the anchor slot is reserved');

        $audit = AuditLog::query()->where('replacement_request_id', $request->id)->firstOrFail();
        $this->assertSame('submitted', $audit->action);
    }

    public function test_arrangement_refuses_other_lecturers_session(): void
    {
        // B's class with its own module code — the params resolve to a session
        // A does NOT own; A must see the honest no-class state, never B's
        // session (FR 2.11), and must not silently fall back to an unrelated
        // own session.
        $moduleB = Module::create([
            'module_code' => 'BMIT2002', 'module_name' => 'Databases',
            'allowed_session_types' => 'L,T',
        ]);
        $sessionB = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $moduleB->id,
            'lecturer_id' => $this->lecturerB->id,
            'day_of_week' => 1,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $sessionB->cohorts()->attach([$this->cohort->id]);

        Livewire::withQueryParams(['code' => 'BMIT2002', 'day' => '1', 'start' => '4'])
            ->actingAs($this->lecturerA)
            ->test(ReplacementArrangement::class)
            ->assertSee('No class selected.')
            ->assertDontSee('BMIT2002');
    }

    public function test_arrangement_shows_no_available_slots_when_fully_booked(): void
    {
        // Week 6 at B100 has zero slot rows — nothing bookable, no crash.
        $this->mountForLecturerA()
            ->assertSee('No available slots');
    }
}
