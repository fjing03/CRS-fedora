<?php

namespace Tests\Feature;

use App\Livewire\CohortTimetable;
use App\Livewire\MyTimetable;
use App\Livewire\StudentMyTimetable;
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
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * timetable-data-wiring spec: real-data rendering, per-role scoping,
 * request-status overlays, empty state.
 */
class TimetableWiringTest extends TestCase
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
        $prog = Programme::create(['programme_code' => 'RSD', 'programme_name' => 'RSD', 'faculty_id' => $faculty->id]);

        $this->cohortA = Cohort::create([
            'programme_id' => $prog->id,
            'current_year' => 3,
            'semester' => 1,
            'tutorial_group' => 1,
            'academic_year' => '2025/26',
            'intake' => 'June 2023',
        ]);
        $this->cohortB = Cohort::create([
            'programme_id' => $prog->id,
            'current_year' => 3,
            'semester' => 1,
            'tutorial_group' => 2,
            'academic_year' => '2025/26',
            'intake' => 'June 2023',
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
        $this->module = Module::create([
            'module_code' => 'BMIT1001', 'module_name' => 'Software Engineering',
            'allowed_session_types' => 'L,T',
        ]);
    }

    private function createSession(User $lecturer, string $start = '10:00:00', string $end = '12:00:00', int $day = 1): ClassSession
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
        $session->cohorts()->attach([$this->cohortA->id]);

        return $session;
    }

    public function test_lecturer_sees_own_sessions_from_the_database(): void
    {
        $this->createSession($this->lecturerA);
        $this->createSession($this->lecturerB);

        Livewire::actingAs($this->lecturerA)
            ->test(MyTimetable::class)
            ->assertSee('BMIT1001')
            ->assertSee('Lecturer A')
            ->assertSee('B100');
    }

    public function test_lecturer_does_not_see_other_lecturers_sessions(): void
    {
        $this->createSession($this->lecturerB);

        Livewire::actingAs($this->lecturerA)
            ->test(MyTimetable::class)
            ->assertDontSee('BMIT1001');
    }

    public function test_student_sees_only_own_cohort_sessions(): void
    {
        $this->createSession($this->lecturerA); // cohort A only

        // Cohort Timetable: student A sees the session, student B does not.
        Livewire::actingAs($this->studentA)
            ->test(CohortTimetable::class)
            ->assertSee('BMIT1001');

        Livewire::actingAs($this->studentB)
            ->test(CohortTimetable::class)
            ->assertDontSee('BMIT1001');

        // Student My Timetable: same scoping.
        Livewire::actingAs($this->studentA)
            ->test(StudentMyTimetable::class)
            ->assertSee('BMIT1001');

        Livewire::actingAs($this->studentB)
            ->test(StudentMyTimetable::class)
            ->assertDontSee('BMIT1001');
    }

    public function test_pending_request_marks_the_original_occurrence(): void
    {
        $session = $this->createSession($this->lecturerA);

        $slot = TimeSlot::create([
            'semester_id' => $this->semester->id, 'class_session_id' => null, 'week_number' => 5,
            'day_of_week' => 3, 'start_time' => '14:00:00', 'end_time' => '14:30:00',
            'venue_id' => $this->venue->id, 'status' => 'pending', 'version' => 1,
        ]);

        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerA->id,
            'class_session_id' => $session->id,
            'week_number' => 5,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Livewire::actingAs($this->lecturerA)
            ->test(MyTimetable::class)
            ->assertSee('"status":"pending"', false)
            ->assertSee('"requestId":1', false);
    }

    public function test_approved_request_moves_the_occurrence_to_the_new_slot(): void
    {
        $session = $this->createSession($this->lecturerA); // Monday-ish, day 1, B100

        $slot = TimeSlot::create([
            'semester_id' => $this->semester->id, 'class_session_id' => null, 'week_number' => 6,
            'day_of_week' => 4, 'start_time' => '14:00:00', 'end_time' => '14:30:00',
            'venue_id' => $this->venue->id, 'status' => 'pending', 'version' => 1,
        ]);

        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerA->id,
            'class_session_id' => $session->id,
            'week_number' => 6,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'approved',
            'submitted_at' => now(),
            'decided_at' => now(),
        ]);

        $component = Livewire::actingAs($this->lecturerA)
            ->test(MyTimetable::class);

        // Week 6 (index 5): original day-1 occurrence gone, replacement on day 4.
        $events = $component->viewData('eventsByWeek');
        $week6 = $events[5];
        $this->assertNotEmpty($week6);
        foreach ($week6 as $event) {
            $this->assertSame('replacement', $event['status']);
            $this->assertSame(4, $event['di']);
            $this->assertSame(12, $event['start']); // (14-8)*2
        }
    }

    public function test_cancelled_week_renders_the_empty_state(): void
    {
        $session = $this->createSession($this->lecturerA);
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => 3,
            'reason' => 'public_holiday',
        ]);

        // All of lecturer A's sessions are excluded in week 3 → empty state.
        $this->actingAs($this->lecturerA)
            ->get('/my-timetable-ui')
            ->assertOk()
            ->assertSee('All classes for this week have been cancelled.');
    }
}
