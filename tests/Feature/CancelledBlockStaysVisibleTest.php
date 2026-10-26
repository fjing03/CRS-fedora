<?php

namespace Tests\Feature;

use App\Livewire\MyTimetable;
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
 * cancelled-blocks-stay-visible (user law 2026-10-27): a cancelled class
 * NEVER leaves the timetable — the original slot renders the red
 * `event-conflict` block until the PL approves the proposed replacement.
 * The discriminator is READ-TIME: an exception'd pair renders cancelled
 * IFF no approved replacement request covers it. No check-value regime,
 * no data migration (the demo sheet died in the review rounds).
 */
class CancelledBlockStaysVisibleTest extends TestCase
{
    use RefreshDatabase;

    private User $lecturerA;

    private Semester $semester;

    private Venue $venue;

    private Module $module;

    private Cohort $cohortA;

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

        $this->lecturerA = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer A']);
        Lecturer::create(['user_id' => $this->lecturerA->id, 'staff_id' => '6001', 'dept_id' => $dept->id, 'is_pl' => false]);

        $this->venue = Venue::create([
            'room_code' => 'B100', 'room_name' => 'Tutorial Room B100',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T', 'capacity' => 35,
        ]);
        $this->module = Module::create([
            'module_code' => 'BMIT1001', 'module_name' => 'Software Engineering',
            'allowed_session_types' => 'L,T',
        ]);
    }

    private function createSession(string $start = '10:00:00', string $end = '12:00:00'): ClassSession
    {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $this->lecturerA->id,
            'day_of_week' => 2,
            'start_time' => $start,
            'end_time' => $end,
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach([$this->cohortA->id]);

        return $session;
    }

    /** law ① — the cancelled-only week renders the red block with the label + fact. */
    public function test_cancelled_exception_renders_the_block(): void
    {
        $session = $this->createSession();
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => 5,
            'reason' => 'annual_leave',
        ]);

        $component = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class);
        $week5 = $component->viewData('eventsByWeek')[4];

        $this->assertCount(1, $week5);
        $this->assertSame('cancelled', $week5[0]['status']);
        $this->assertSame('Annual Leave', $week5[0]['cancelledReason']);   // ReasonLabel — the bare label
        $this->assertSame('exception', $week5[0]['occurrenceFact']['kind']);   // the popup's Replace-Now handoff stays
        $this->assertSame('Cancelled — Annual Leave', $week5[0]['occurrenceFact']['reason']);   // the COMPOUND fact shape (D-P4, pre-existing)
    }

    /** laws ②/③ — the cancelled + pending renders BOTH blocks (the empty week stays populated). */
    public function test_pending_proposal_keeps_the_cancelled_block_plus_the_amber(): void
    {
        $session = $this->createSession();
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => 5,
            'reason' => 'official_event',
        ]);

        // the proposed makeup lands W8 (different-week shape)
        $slot = TimeSlot::create([
            'semester_id' => $this->semester->id, 'class_session_id' => null, 'week_number' => 8,
            'day_of_week' => 4, 'start_time' => '14:00:00', 'end_time' => '14:30:00',
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

        $component = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class);
        $events = $component->viewData('eventsByWeek');
        $week5 = $events[4];

        $this->assertCount(1, $week5);   // the makeup rides its OWN week 8 (w5 keeps the cancelled block)
        $this->assertSame('cancelled', $week5[0]['status']);
        $this->assertTrue($week5[0]['proposed']);   // "(Proposed ⏳)"
        $this->assertNull($week5[0]['occurrenceFact']);   // the handled pair — the fact yields null

        $week8 = $events[7];
        $pendingEvents = array_values(array_filter($week8, fn ($e) => $e['status'] === 'pending'));
        $this->assertCount(1, $pendingEvents);
        $this->assertSame(4, $pendingEvents[0]['di']);   // the makeup's own day
    }

    /** law ④ — the approved composite: the original vanishes, the landing renders. */
    public function test_approved_release_clears_the_original_week(): void
    {
        $session = $this->createSession();
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => 5,
            'reason' => 'official_event',   // legacy stamps included — the READ-TIME derivation decides
        ]);
        $slot = TimeSlot::create([
            'semester_id' => $this->semester->id, 'class_session_id' => null, 'week_number' => 8,
            'day_of_week' => 4, 'start_time' => '14:00:00', 'end_time' => '14:30:00',
            'venue_id' => $this->venue->id, 'status' => 'occupied', 'version' => 1,
        ]);
        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerA->id,
            'class_session_id' => $session->id,
            'week_number' => 5,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'approved',
            'submitted_at' => now(),
            'decided_at' => now(),
        ]);

        $component = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class);
        $events = $component->viewData('eventsByWeek');

        $this->assertCount(0, $events[4]);   // law ④ — the original week renders NOTHING
        $this->assertNotEmpty($events[7]);   // the landing week keeps the approved replacement
    }

    /** the legacy receipt: `official_event` WITHOUT an approved pair renders red-cancelled. */
    public function test_legacy_official_event_without_approval_still_renders_cancelled(): void
    {
        $session = $this->createSession();
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => 5,
            'reason' => 'official_event',
        ]);

        $component = Livewire::actingAs($this->lecturerA)->test(MyTimetable::class);
        $week5 = $component->viewData('eventsByWeek')[4];

        $this->assertCount(1, $week5);
        $this->assertSame('cancelled', $week5[0]['status']);
        $this->assertSame('Official Event', $week5[0]['cancelledReason']);
    }
}
