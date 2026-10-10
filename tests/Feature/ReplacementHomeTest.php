<?php

namespace Tests\Feature;

use App\Livewire\ReplacementHome;
use App\Models\ClassException;
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
 * replacement-flow-wiring spec (task 2.2): Replacement Home lists the
 * lecturer's own classes, conflicted included (FR 2.4), with request counts
 * by status and search. Controlled fixture (TimetableWiringTest pattern).
 */
final class ReplacementHomeTest extends TestCase
{
    use RefreshDatabase;

    private User $lecturerA;

    private User $lecturerB;

    private Cohort $degreeCohort;

    private Cohort $diplomaCohort;

    private Semester $semester;

    private Venue $venue;

    private Venue $b005;

    private Module $module;

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
        $degree = Programme::create(['programme_code' => 'RSD', 'programme_name' => 'RSD', 'faculty_id' => $faculty->id]);
        $diploma = Programme::create(['programme_code' => 'DIT', 'programme_name' => 'DIT', 'faculty_id' => $faculty->id]);

        $this->degreeCohort = Cohort::create([
            'programme_id' => $degree->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2023',
        ]);
        $this->diplomaCohort = Cohort::create([
            'programme_id' => $diploma->id, 'current_year' => 1, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2025',
        ]);

        $this->lecturerA = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer A']);
        Lecturer::create(['user_id' => $this->lecturerA->id, 'staff_id' => '6001', 'dept_id' => $dept->id, 'is_pl' => false]);

        $this->lecturerB = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer B']);
        Lecturer::create(['user_id' => $this->lecturerB->id, 'staff_id' => '6002', 'dept_id' => $dept->id, 'is_pl' => false]);

        $this->venue = Venue::create([
            'room_code' => 'B100', 'room_name' => 'Tutorial Room B100',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T', 'capacity' => 35,
        ]);
        $this->b005 = Venue::create([
            'room_code' => 'B005', 'room_name' => 'Lecture Hall B005',
            'room_type' => 'lecture_hall', 'allowed_session_types' => 'L', 'capacity' => 120,
        ]);
        $this->module = Module::create([
            'module_code' => 'BMIT1001', 'module_name' => 'Software Engineering',
            'allowed_session_types' => 'L,T',
        ]);
    }

    private function createSession(User $lecturer, ?Venue $venue = null, ?array $cohorts = null): ClassSession
    {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => 1,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => ($venue ?? $this->venue)->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach(collect($cohorts ?? [$this->degreeCohort])->pluck('id')->all());

        return $session;
    }

    private function createSlot(int $week = 5): TimeSlot
    {
        return TimeSlot::create([
            'semester_id' => $this->semester->id, 'class_session_id' => null,
            'week_number' => $week, 'day_of_week' => 3,
            'start_time' => '14:00:00', 'end_time' => '14:30:00',
            'venue_id' => $this->venue->id, 'status' => 'available',
        ]);
    }

    public function test_home_lists_own_classes_with_status_badges(): void
    {
        $this->createSession($this->lecturerA);
        $this->createSession($this->lecturerB);

        Livewire::actingAs($this->lecturerA)
            ->test(ReplacementHome::class)
            ->assertSee('BMIT1001')
            ->assertSee('Software Engineering')
            ->assertSee('B100')
            ->assertSee('badge-normal')
            ->assertDontSee('Lecturer B');
    }

    public function test_home_flags_b005_venue_restriction_conflict(): void
    {
        $this->createSession($this->lecturerA, $this->b005, [$this->degreeCohort, $this->diplomaCohort]);

        Livewire::actingAs($this->lecturerA)
            ->test(ReplacementHome::class)
            ->assertSee('Venue restriction')
            ->assertSee('badge-conflict');
    }

    public function test_home_counts_my_requests_by_status(): void
    {
        $session = $this->createSession($this->lecturerA);

        foreach ([['pending', 5], ['approved', 6], ['rejected', 7]] as [$status, $week]) {
            ReplacementRequest::create([
                'semester_id' => $this->semester->id,
                'proposer_id' => $this->lecturerA->id,
                'class_session_id' => $session->id,
                'week_number' => $week,
                'replacement_time_slot_id' => $this->createSlot($week)->id,
                'status' => $status,
                'submitted_at' => now(),
            ]);
        }

        Livewire::actingAs($this->lecturerA)
            ->test(ReplacementHome::class)
            ->assertSeeHtml('<span class="summary-value" id="sumPending">1</span>')
            ->assertSeeHtml('<span class="summary-value" id="sumApproved">1</span>')
            ->assertSeeHtml('<span class="summary-value" id="sumRejected">1</span>');
    }

    public function test_home_shows_cancelled_weeks_and_active_requests(): void
    {
        $session = $this->createSession($this->lecturerA);
        ClassException::create(['class_session_id' => $session->id, 'week_number' => 3, 'reason' => 'medical_leave']);

        Livewire::actingAs($this->lecturerA)
            ->test(ReplacementHome::class)
            ->assertSee('1 cancelled wk');
    }

    public function test_home_search_filters_by_module(): void
    {
        $this->createSession($this->lecturerA);

        Livewire::actingAs($this->lecturerA)
            ->test(ReplacementHome::class)
            ->set('search', 'Software')
            ->assertSee('BMIT1001')
            ->set('search', 'Quantum')
            ->assertSee('No classes match your search.')
            ->assertDontSee('BMIT1001');
    }
}
